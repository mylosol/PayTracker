<?php

declare(strict_types=1);

namespace PayTracker\Services;

use PayTracker\Logging\Logger;

/**
 * Thin Resend (https://resend.com) API client.
 *
 * Why a hand-rolled HTTP call rather than the official Resend PHP SDK:
 *   - Deploy is rsync from CI to DreamHost shared hosting. Adding the
 *     SDK pulls Guzzle + a transitive tree we don't otherwise need.
 *     The Resend REST endpoint is one POST with a JSON body — a 60-line
 *     cURL wrapper costs less than a composer require.
 *
 * Behaviour rules:
 *   - Never log the API key. The cURL error path scrubs the
 *     Authorization header before any error_log.
 *   - Empty api_key means "email is disabled" (e.g. a developer
 *     environment that hasn't set RESEND_API). send() returns
 *     null with a soft error_log, so the calling controller can
 *     still show the reset URL on-screen.
 *   - Transport errors (cURL fail, 5xx response) DO NOT throw.
 *     Email delivery is fire-and-forget from the admin panel's
 *     perspective: a Resend outage shouldn't fail the password-
 *     reset action; the URL is always shown in the flash banner
 *     as a fallback the admin can copy/paste.
 *   - 4xx responses (bad sender, invalid recipient) log the body
 *     so an admin can diagnose, but still return null so the
 *     caller's flow stays unbroken.
 */
final class MailService
{
    private const ENDPOINT = 'https://api.resend.com/emails';

    /**
     * @param string $apiKey  Resend API key (RESEND_API). Empty disables email.
     * @param string $from    Verified sender address. Plain or "Name <addr>".
     * @param int    $timeout HTTP timeout in seconds.
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly string $from,
        private readonly Logger $logger,
        private readonly int $timeout = 10,
    ) {
    }

    /**
     * True if the service is configured to actually send. The admin
     * panel reads this to decide whether the "email sent" flash
     * suffix is honest or aspirational.
     */
    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->from !== '';
    }

    /**
     * Send a single transactional email. Returns the Resend message
     * id on success or null on any failure (logged).
     *
     * @param string $to      Plain recipient address. We do NOT support a
     *                        display name on the recipient side — admins
     *                        send to a single address; multi-recipient
     *                        notifications are out of scope.
     * @param string $subject Plain-text subject. Resend escapes for us.
     * @param string $html    HTML body. Plain text is generated from a
     *                        strip_tags fallback so clients that prefer
     *                        text/plain still render readable content.
     */
    public function send(string $to, string $subject, string $html): ?string
    {
        if (! $this->isConfigured()) {
            $this->logger->info('MailService disabled (RESEND_API not set); skipping send.');
            return null;
        }
        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->logger->warning('MailService: invalid recipient, refusing to send.', ['to' => $to]);
            return null;
        }

        $payload = [
            'from'    => $this->from,
            'to'      => [$to],
            'subject' => $subject,
            'html'    => $html,
            'text'    => $this->htmlToText($html),
        ];

        $ch = curl_init(self::ENDPOINT);
        if ($ch === false) {
            $this->logger->error('MailService: curl_init failed.');
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => max(2, (int) ($this->timeout / 2)),
        ]);

        $body   = curl_exec($ch);
        $errno  = curl_errno($ch);
        $error  = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || ! is_string($body)) {
            // Transport failure. Resend itself may be fine; we just
            // couldn't reach them. Logged but not thrown — the admin
            // still sees the URL in the flash banner.
            $this->logger->error('MailService: transport failure.', [
                'errno'  => $errno,
                'error'  => $error,
                'status' => $status,
            ]);
            return null;
        }

        if ($status < 200 || $status >= 300) {
            // Resend returned a structured error. Log the body so an
            // admin can diagnose (bad sender domain, invalid
            // recipient, etc.) without us needing to ship in a debug
            // build. We do NOT log the Authorization header anywhere.
            $this->logger->error('MailService: Resend API returned non-2xx.', [
                'status' => $status,
                'body'   => substr($body, 0, 1024),
            ]);
            return null;
        }

        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            $this->logger->warning('MailService: Resend success body was not JSON.', [
                'body' => substr($body, 0, 256),
            ]);
            return null;
        }
        return isset($decoded['id']) && is_string($decoded['id']) ? $decoded['id'] : null;
    }

    /**
     * Produce a plain-text fallback from the HTML body. Resend lets
     * clients pick text/plain over text/html; supplying a readable
     * version improves deliverability vs. just letting Resend
     * auto-generate one from the HTML.
     */
    private function htmlToText(string $html): string
    {
        // Collapse common block tags into newlines, then strip.
        $text = (string) preg_replace('#<\s*/?(p|br|div|tr|li|h[1-6])[^>]*>#i', "\n", $html);
        $text = strip_tags($text);
        $text = (string) preg_replace("/[\r\n]{3,}/", "\n\n", $text);
        return trim($text);
    }
}
