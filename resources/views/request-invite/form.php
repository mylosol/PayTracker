<?php
/**
 * @var string      $csrfToken
 * @var string      $base
 * @var string|null $flash
 * @var array{name?:string,email?:string,terminal?:string,referral?:string} $old
 */
layout('layouts/app');
?>
<div class="max-w-xl mx-auto">
    <div class="card">
        <h1 class="m-0">Request an invite</h1>
        <p class="text-brand-muted mt-2 mb-0">
            PayTracker is invite-only, but if you drive for the fleet and
            want in, tell us a bit about yourself and we'll send you a
            code. Nothing you enter here is public.
        </p>
    </div>

    <?php if ($flash !== null): ?>
        <div class="flash-err" role="alert"><?= e($flash) ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="post" action="<?= e($base) ?>/request-invite" novalidate autocomplete="on" class="space-y-5">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

            <div>
                <label for="ri-name" class="field-label">Your name</label>
                <input id="ri-name" name="name" type="text" required maxlength="120"
                       autocomplete="name"
                       value="<?= e((string) ($old['name'] ?? '')) ?>"
                       class="field">
            </div>

            <div>
                <label for="ri-email" class="field-label">Email</label>
                <input id="ri-email" name="email" type="email" required maxlength="254"
                       autocomplete="email" inputmode="email"
                       value="<?= e((string) ($old['email'] ?? '')) ?>"
                       class="field">
                <span class="field-hint">
                    We'll email your invite code here — pick an address
                    you actually check.
                </span>
            </div>

            <div>
                <label for="ri-terminal" class="field-label">Which terminal do you drive out of?</label>
                <input id="ri-terminal" name="terminal" type="text" required maxlength="120"
                       value="<?= e((string) ($old['terminal'] ?? '')) ?>"
                       autocomplete="off" spellcheck="true"
                       class="field">
            </div>

            <div>
                <label for="ri-referral" class="field-label">Who told you about PayTracker? <span class="text-brand-muted font-normal">(optional)</span></label>
                <textarea id="ri-referral" name="referral" rows="3" maxlength="500"
                          class="field"><?= e((string) ($old['referral'] ?? '')) ?></textarea>
            </div>

            <?php // Honeypot — hidden from humans, catches naive bots. ?>
            <div style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
                <label for="ri-website">Website (leave blank)</label>
                <input id="ri-website" name="website" type="text" tabindex="-1" autocomplete="off">
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center gap-3 pt-2">
                <button type="submit" class="btn-primary">Send request</button>
                <a href="<?= e($base) ?>/" class="text-sm">Cancel</a>
            </div>
        </form>
    </div>
</div>
