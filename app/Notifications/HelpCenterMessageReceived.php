<?php

namespace App\Notifications;

use App\Notifications\Concerns\RendersEmailTemplate;
use Illuminate\Notifications\Notification;

class HelpCenterMessageReceived extends Notification
{
    use RendersEmailTemplate;

    public const TEMPLATE_KEY = 'help_received';

    /**
     * @param  array{first_name: string, last_name: string, email: string, mobile?: string|null, subject?: string|null, message: string}  $message
     */
    public function __construct(public readonly array $message) {}

    /**
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    public function parts(string $name = 'there'): array
    {
        $sender = trim($this->message['first_name'].' '.$this->message['last_name']);
        $subject = (string) ($this->message['subject'] ?? '');
        $mobile = (string) ($this->message['mobile'] ?? '');

        return [[
            'subject' => 'New Help Center message'.($subject !== '' ? ': '.$subject : ''),
            'greeting' => 'New message from '.$sender,
            'lines' => [
                'A visitor sent the following message through the Help Center form.',
                '**Name:** '.$sender,
                '**Email:** '.$this->message['email'],
                '**Mobile:** '.($mobile !== '' ? $mobile : 'Not provided'),
                '**Subject:** '.($subject !== '' ? $subject : 'None'),
                '**Message:**',
                $this->message['message'],
                'Reply to '.$this->message['email'].' to answer this enquiry.',
            ],
            'action' => null,
            'salutation' => '— Nexus Peptides | UAE Team',
        ], [
            'customer_name' => $sender,
            'customer_email' => $this->message['email'],
            'customer_mobile' => $mobile,
            'subject' => $subject,
            'message' => $this->message['message'],
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
