<?php

namespace App\Http\Controllers;

use App\Models\Content;
use App\Notifications\HelpCenterMessageConfirmation;
use App\Notifications\HelpCenterMessageReceived;
use App\Services\GoogleRecaptcha;
use App\Services\SiteSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Throwable;

class ContactController extends Controller
{
    public const RECAPTCHA_ACTION = 'help_center_message';

    public function __construct(private readonly GoogleRecaptcha $recaptcha) {}

    /**
     * Relay a Help Center message to the support inbox and copy the
     * customer, so the visitor gets both an on-screen confirmation and an
     * email receipt of what they sent.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:32'],
            'subject' => ['nullable', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
            'recaptcha_token' => ['nullable', 'string', 'max:4000'],
        ]);

        if ($this->recaptcha->enabled() && ! $this->recaptcha->verify($data['recaptcha_token'] ?? null, self::RECAPTCHA_ACTION)) {
            throw ValidationException::withMessages([
                'recaptcha' => 'We could not confirm you are human. Please try again.',
            ]);
        }

        $message = collect($data)->except('recaptcha_token')->all();
        $supportEmail = $this->supportEmail();

        try {
            Notification::route('mail', $supportEmail)->notify(new HelpCenterMessageReceived($message));
            Notification::route('mail', $message['email'])->notify(new HelpCenterMessageConfirmation($message));
        } catch (Throwable $exception) {
            Log::error('Help Center message could not be emailed.', ['exception' => $exception->getMessage()]);

            return response()->json([
                'message' => 'Your message could not be delivered right now. Please try again shortly or email us directly at '.$supportEmail.'.',
            ], 502);
        }

        return response()->json([
            'message' => 'Your message has been sent to our support team. A copy is on its way to your inbox.',
        ]);
    }

    /**
     * Delivery inbox resolution, most specific first: the dedicated
     * contact_delivery_email setting (so the public supportEmail shown on
     * the site can stay a brand alias while mail reaches a real inbox),
     * then the published site supportEmail, then the configured sender.
     */
    protected function supportEmail(): string
    {
        $dedicated = app(SiteSettings::class)->get('contact_delivery_email');
        if (filled($dedicated)) {
            return $dedicated;
        }

        $site = Content::query()
            ->where('key', 'site')
            ->where('type', 'group')
            ->where('is_active', true)
            ->first()?->data ?? [];

        return $site['supportEmail'] ?? config('mail.from.address', 'info@nexuspeptides.com');
    }
}
