<?php

namespace App\Services;

use App\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Throwable;

/**
 * Resolves the admin-editable wording for each transactional email.
 *
 * Every notification supplies its built-in "default" parts; if an active
 * email_templates row overrides any field, that override wins, otherwise the
 * default renders unchanged. This keeps customisation strictly opt-in and
 * lets the code stay the source of truth for anything sensitive (button URLs
 * such as the password-reset token are never overridable).
 *
 * @phpstan-type DefaultsArray array{
 *   subject: string,
 *   greeting?: string|null,
 *   lines: array<int, string>,
 *   action?: array{label: string, url: string}|null,
 *   salutation?: string|null
 * }
 */
class EmailTemplates
{
    /**
     * Build the mail message for a template, applying any admin override and
     * substituting {variable} placeholders with the supplied context.
     *
     * @param  DefaultsArray  $defaults
     * @param  array<string, string>  $context
     */
    public function build(string $key, array $defaults, array $context): MailMessage
    {
        $parts = $this->resolve($key, $defaults, $context);

        $mail = (new MailMessage)->subject($parts['subject']);

        if ($parts['greeting'] !== null && $parts['greeting'] !== '') {
            $mail->greeting($parts['greeting']);
        }

        foreach ($parts['lines'] as $line) {
            $mail->line($line);
        }

        if ($parts['action'] !== null) {
            $mail->action($parts['action']['label'], $parts['action']['url']);
        }

        if ($parts['salutation'] !== null && $parts['salutation'] !== '') {
            $mail->salutation($parts['salutation']);
        }

        return $mail;
    }

    /**
     * The fully-resolved, placeholder-substituted parts. Used for both sending
     * (via build) and for the admin live preview, so they never drift apart.
     *
     * @param  DefaultsArray  $defaults
     * @param  array<string, string>  $context
     * @return array{subject: string, greeting: string|null, lines: array<int, string>, action: array{label: string, url: string}|null, salutation: string|null}
     */
    public function resolve(string $key, array $defaults, array $context, bool $ignoreOverride = false): array
    {
        $override = $ignoreOverride ? null : $this->activeOverride($key);

        $subject = $this->pick($override?->subject, $defaults['subject']);
        $greeting = $this->pick($override?->greeting, $defaults['greeting'] ?? null);
        $salutation = $this->pick($override?->salutation, $defaults['salutation'] ?? null);

        $lines = ($override !== null && filled($override->body))
            ? $this->splitBody($override->body)
            : $defaults['lines'];

        $action = $defaults['action'] ?? null;
        if ($action !== null) {
            $action = [
                'label' => $this->pick($override?->action_label, $action['label']),
                'url' => $action['url'], // Always code-generated; never overridable.
            ];
        }

        return [
            'subject' => $this->substitute($subject, $context),
            'greeting' => $greeting === null ? null : $this->substitute($greeting, $context),
            'lines' => array_map(fn (string $line): string => $this->substitute($line, $context), $lines),
            'action' => $action === null ? null : [
                'label' => $this->substitute($action['label'], $context),
                'url' => $this->substitute($action['url'], $context),
            ],
            'salutation' => $salutation === null ? null : $this->substitute($salutation, $context),
        ];
    }

    /** @return Model|null  The active override row, or null when none/customisation is off. */
    protected function activeOverride(string $key): ?EmailTemplate
    {
        try {
            return EmailTemplate::query()
                ->where('key', $key)
                ->where('is_active', true)
                ->first();
        } catch (Throwable) {
            // The table may not exist yet (fresh install, mid-migration); fall back to defaults.
            return null;
        }
    }

    /** Prefer the override when it holds text, otherwise the default. */
    protected function pick(?string $override, ?string $default): ?string
    {
        return $override === null || $override === '' ? $default : $override;
    }

    /** @return array<int, string> */
    protected function splitBody(string $body): array
    {
        $lines = explode("\n", str_replace("\r", '', $body));

        // Drop a single trailing blank line so a newline at the end of the
        // textarea does not add an empty paragraph to the email.
        if (count($lines) > 1 && trim((string) end($lines)) === '') {
            array_pop($lines);
        }

        return $lines;
    }

    /** Replace {token} placeholders that exist in the context; leave unknowns intact. */
    protected function substitute(?string $text, array $context): string
    {
        return preg_replace_callback(
            '/\{\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\}/',
            fn (array $m): string => array_key_exists($m[1], $context) ? (string) $context[$m[1]] : $m[0],
            (string) $text
        );
    }
}
