<?php

namespace App\Notifications\Concerns;

use App\Services\EmailTemplates;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Shared plumbing for transactional emails whose wording an admin can edit.
 *
 * Each notification defines a TEMPLATE_KEY and a parts() method returning its
 * built-in default wording plus the {placeholder} context. toMail() then
 * delegates to the EmailTemplates service, which overrides any part the admin
 * has customised (and substitutes placeholders). Nothing needs a customised
 * template to render — the built-in default is the fallback.
 */
trait RendersEmailTemplate
{
    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        [$defaults, $context] = $this->parts(($notifiable->name ?? null) ?: 'there');

        return app(EmailTemplates::class)->build(static::TEMPLATE_KEY, $defaults, $context);
    }

    /**
     * @param  string  $name  Customer display name used for greeting defaults.
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    abstract public function parts(string $name = 'there'): array;
}
