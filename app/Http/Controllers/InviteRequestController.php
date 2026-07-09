<?php

declare(strict_types=1);

namespace PayTracker\Http\Controllers;

use PayTracker\Http\Request;
use PayTracker\Http\Response;
use PayTracker\Models\AuditLog;
use PayTracker\Models\Terminal;
use PayTracker\Security\Csrf;
use PayTracker\Security\Session;
use PayTracker\Services\MailService;
use PayTracker\Support\Config;

/**
 * InviteRequestController — public "please invite me" form.
 *
 * PayTracker is invite-only, but plenty of the drivers who used the
 * legacy site never made an account there either. This form gives
 * them a way to ask an admin for a code without exposing an email
 * address on the marketing page (where scrapers would harvest it).
 *
 * Flow:
 *   GET  /request-invite  — render the form (name, email, terminal,
 *                           optional referral note).
 *   POST /request-invite  — validate + email an admin + audit + show
 *                           a generic confirmation page.
 *
 * Anti-abuse:
 *   - CSRF on submit (same Csrf class the rest of the app uses).
 *   - Honeypot field ("website") — a hidden text input human users
 *     never touch. Any submission that fills it is silently accepted
 *     with a 200 and dropped without emailing anyone. Bots see the
 *     same UX as humans, which is the point.
 *   - Server-side email format validation, plus rejection of
 *     obvious-spam markers in the name field.
 *   - No storage of the raw request in a DB table — the audit_log
 *     row + the email to admin@ are enough. Deleting a user's data
 *     is `DELETE FROM audit_log WHERE ...` if they ever ask.
 */
final class InviteRequestController extends Controller
{
    /** Terminal option we render when the driver's terminal isn't listed. */
    private const OTHER_TERMINAL = 'Other / Not listed';

    public function __construct(
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly Terminal $terminals,
        private readonly AuditLog $audit,
        private readonly MailService $mail,
        private readonly Config $config,
    ) {
    }

    /** GET /request-invite — render the form. */
    public function show(Request $request): Response
    {
        $this->session->start();
        return $this->view('request-invite/form', [
            'base'       => $request->basePath(),
            'csrfToken'  => $this->csrf->token(),
            'terminals'  => $this->terminals->all(),
            'flash'      => $this->popFlash(),
            'old'        => (array) ($this->session->get('_ri_old') ?? []),
            'otherLabel' => self::OTHER_TERMINAL,
        ]);
    }

    /** POST /request-invite — validate and email admin@. */
    public function submit(Request $request): Response
    {
        $this->session->start();
        if (! $this->csrf->verify($request->input('_csrf'))) {
            $this->session->put('_flash', 'Your session expired. Please try again.');
            return $this->redirect($request->basePath() . '/request-invite');
        }

        $name     = trim((string) $request->input('name', ''));
        $email    = trim((string) $request->input('email', ''));
        $terminal = trim((string) $request->input('terminal', ''));
        $referral = trim((string) $request->input('referral', ''));
        $honeypot = trim((string) $request->input('website', ''));

        // Bot trap: legit users never see the "website" field. Silently
        // land them on the "sent" page so the bot can't distinguish
        // rejection from acceptance.
        if ($honeypot !== '') {
            return $this->view('request-invite/sent', [
                'base'  => $request->basePath(),
                'email' => $email,
            ]);
        }

        // Cheap input caps — a form that lets a bot POST 10MB of spam
        // is a spam amplifier. We accept a generous ceiling per field
        // and truncate; anything over the ceiling is malicious traffic.
        $name     = mb_substr($name,     0, 120);
        $email    = mb_substr($email,    0, 254);
        $terminal = mb_substr($terminal, 0, 120);
        $referral = mb_substr($referral, 0, 500);

        $errors = [];
        if ($name === '') {
            $errors[] = 'Your name is required.';
        }
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }
        if ($terminal === '') {
            $errors[] = 'Please pick your terminal.';
        }
        // Common spam smell: URLs in the name field.
        if (preg_match('~https?://|<a\s|bit\.ly|\.ru\b~i', $name)) {
            $errors[] = 'Your name looks unusual — please enter your real name.';
        }

        if ($errors !== []) {
            $this->session->put('_flash', implode(' ', $errors));
            $this->session->put('_ri_old', [
                'name'     => $name,
                'email'    => $email,
                'terminal' => $terminal,
                'referral' => $referral,
            ]);
            return $this->redirect($request->basePath() . '/request-invite');
        }

        // Terminal whitelist: accept anything in the current active
        // list OR the "Other" sentinel. Reject invented values so a
        // scripted POST can't slip HTML through the terminal field.
        $known = $this->terminals->all();
        if ($terminal !== self::OTHER_TERMINAL && ! in_array($terminal, $known, true)) {
            $terminal = self::OTHER_TERMINAL;
        }

        // Send to the admin inbox. If Resend is offline we still audit
        // the request so an admin can find it on-site tomorrow.
        $adminTo = (string) $this->config->get(
            'services.admin_notifications.to',
            'admin@paytracker.xyz'
        );
        $emailStatus = 'mail-disabled';
        $messageId   = null;
        if ($this->mail->isConfigured()) {
            $messageId = $this->mail->send(
                $adminTo,
                'PayTracker invite request from ' . $name,
                $this->buildAdminEmailHtml($name, $email, $terminal, $referral, $request)
            );
            $emailStatus = $messageId !== null
                ? sprintf('emailed (msg %s)', substr($messageId, 0, 12))
                : 'email-send-failed';
        }

        $this->audit->record(
            AuditLog::ACTION_INVITE_REQUESTED,
            ipAddress: $this->clientIp(),
            metadata: [
                'name'         => $name,
                'email'        => $email,
                'terminal'     => $terminal,
                'referral'     => $referral,
                'email_status' => $emailStatus,
                'user_agent'   => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200),
            ],
        );

        // Clear the sticky-old payload so a resubmit starts blank.
        $this->session->forget('_ri_old');

        return $this->view('request-invite/sent', [
            'base'  => $request->basePath(),
            'email' => $email,
        ]);
    }

    private function buildAdminEmailHtml(
        string $name,
        string $email,
        string $terminal,
        string $referral,
        Request $request,
    ): string {
        $enc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $safeName     = $enc($name);
        $safeEmail    = $enc($email);
        $safeTerminal = $enc($terminal);
        $safeUrl      = $enc('https://preview.paytracker.xyz' . $request->basePath() . '/admin/invites/new');
        $referralBlock = $referral === ''
            ? '<p style="color:#64748b;font-style:italic;">(No referral note provided.)</p>'
            : '<p><strong>Referral / notes:</strong><br>' . nl2br($enc($referral)) . '</p>';
        return <<<HTML
<div style="font:16px/1.5 -apple-system,Segoe UI,sans-serif;color:#101418;max-width:640px;">
    <p>Someone requested a PayTracker invite:</p>
    <table cellspacing="0" cellpadding="8" style="border-collapse:collapse;margin:1rem 0;">
        <tr><td style="color:#475569;">Name</td><td><strong>{$safeName}</strong></td></tr>
        <tr><td style="color:#475569;">Email</td><td><a href="mailto:{$safeEmail}">{$safeEmail}</a></td></tr>
        <tr><td style="color:#475569;">Terminal</td><td>{$safeTerminal}</td></tr>
    </table>
    {$referralBlock}
    <p style="margin-top:1.5rem;">
        <a href="{$safeUrl}"
           style="display:inline-block;background:#F97316;color:#fff;text-decoration:none;padding:.6rem 1.4rem;border-radius:6px;">
            Mint an invite code
        </a>
    </p>
    <hr style="border:none;border-top:1px solid #e2e8f0;margin:1.5rem 0;">
    <p style="font-size:13px;color:#64748b;">
        Sent from the public /request-invite form. Full request is
        also recorded in the audit log.
    </p>
</div>
HTML;
    }

    private function clientIp(): ?string
    {
        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;
        if (is_string($xff) && $xff !== '') {
            $first = trim(explode(',', $xff)[0]);
            if ($first !== '') {
                return substr($first, 0, 45);
            }
        }
        $remote = $_SERVER['REMOTE_ADDR'] ?? null;
        return is_string($remote) && $remote !== '' ? substr($remote, 0, 45) : null;
    }

    private function popFlash(): ?string
    {
        $flash = $this->session->get('_flash');
        $this->session->forget('_flash');
        return is_string($flash) ? $flash : null;
    }
}
