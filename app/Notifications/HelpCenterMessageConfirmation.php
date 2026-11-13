<?php

namespace App\Notifications;

use App\Notifications\Concerns\RendersEmailTemplate;
use Illuminate\Notifications\Notification;

class HelpCenterMessageConfirmation extends Notification
{
    use RendersEmailTemplate;

    public const TEMPLATE_KEY = 'help_confirmation';

    /**
     * @param  array{first_name: string, last_name: string, email: string, mobile?: string|null, subject?: string|null, message: string}  $message
     */
    public function __construct(public readonly array $message) {}

    /**
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    public function parts(string $name = 'there'): array
    {
        $subject = (string) ($this->message['subject'] ?? '');

        return [[
            'subject' => 'We received your message'.($subject !== '' ? ' — '.$subject : ''),
            'greeting' => 'Thank you, '.$this->message['first_name'].'!',
            'lines' => [
                'Your message has reached the Nexus Peptides support team and a copy of your enquiry is below for your records.',
                '**Subject:** '.($subject !== '' ? $subject : 'None'),
                $this->message['message'],
                'One of our team will reply to this address, usually within one business day. There is nothing you need to do in the meantime.',
            ],
            'action' => null,
            'salutation' => '— Nexus Peptides | UAE Team',
        ], [
            'first_name' => $this->message['first_name'],
            'subject' => $subject,
            'message' => $this->message['message'],
            'support_email' => 'info@nexuspeptides.com',
        ]];
    }

    public static function sample(): static
    {
        return new static([
            'first_name' => 'Aisha',
            'last_name' => 'Al Marri',
            'email' => 'aisha@example.com',
            'mobile' => '+971501234567',
            'subject' => 'Question about BPC-157',
            'message' => 'Do you have this peptide in stock?',
        ]);
    }
}
