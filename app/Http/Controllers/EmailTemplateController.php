<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use App\Notifications\HelpCenterMessageConfirmation;
use App\Notifications\HelpCenterMessageReceived;
use App\Notifications\OrderStatusUpdated;
use App\Notifications\ResetPasswordLink;
use App\Notifications\WelcomeEmail;
use App\Services\EmailTemplates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Throwable;

class EmailTemplateController extends Controller
{
    /** @var array<string, class-string> */
    protected const CLASSES = [
        'welcome' => WelcomeEmail::class,
        'password_reset' => ResetPasswordLink::class,
        'order_status' => OrderStatusUpdated::class,
        'help_received' => HelpCenterMessageReceived::class,
        'help_confirmation' => HelpCenterMessageConfirmation::class,
    ];

    public function index(): JsonResponse
    {
        $saved = EmailTemplate::query()->whereIn('key', array_keys(self::CLASSES))->get()->keyBy('key');
        $service = app(EmailTemplates::class);

        return response()->json([
            'templates' => collect(config('email-templates.templates', []))
                ->map(function (array $meta, string $key) use ($saved, $service): array {
                    [$defaults, $context] = self::CLASSES[$key]::sample()->parts('Aisha');

                    return [
                        'key' => $key,
                        'label' => $meta['label'],
                        'description' => $meta['description'] ?? '',
                        'variables' => $meta['variables'] ?? [],
                        'is_active' => $saved[$key]->is_active ?? true,
                        'overridden' => $saved->has($key) && $this->hasContent($saved[$key]),
                        'fields' => [
                            'subject' => $saved[$key]->subject ?? null,
                            'greeting' => $saved[$key]->greeting ?? null,
                            'body' => $saved[$key]->body ?? null,
                            'action_label' => $saved[$key]->action_label ?? null,
                            'salutation' => $saved[$key]->salutation ?? null,
                        ],
                        'defaults' => $service->resolve($key, $defaults, $context, true),
                        'preview' => $service->resolve($key, $defaults, $context),
                    ];
                })
                ->values()
                ->all(),
        ]);
    }

    public function update(Request $request, string $key): JsonResponse
    {
        $templates = config('email-templates.templates', []);
        abort_unless(isset($templates[$key]) && isset(self::CLASSES[$key]), 404);

        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'greeting' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:20000'],
            'action_label' => ['nullable', 'string', 'max:255'],
            'salutation' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        $template = EmailTemplate::updateOrCreate(
            ['key' => $key],
            array_merge(
                collect($validated)->only(['subject', 'greeting', 'body', 'action_label', 'salutation', 'is_active'])->all(),
                ['label' => $templates[$key]['label']]
            )
        );

        return response()->json([
            'key' => $template->key,
            'is_active' => $template->is_active,
            'overridden' => $this->hasContent($template),
            'fields' => [
                'subject' => $template->subject,
                'greeting' => $template->greeting,
                'body' => $template->body,
                'action_label' => $template->action_label,
                'salutation' => $template->salutation,
            ],
            'message' => 'Template saved.',
        ] + $this->renderedParts($key));
    }

    public function destroy(string $key): JsonResponse
    {
        EmailTemplate::query()->where('key', $key)->delete();

        return response()->json([
            'message' => 'Reverted to the built-in wording.',
            'overridden' => false,
            'fields' => ['subject' => null, 'greeting' => null, 'body' => null, 'action_label' => null, 'salutation' => null],
        ] + $this->renderedParts($key));
    }

    public function preview(string $key): JsonResponse
    {
        abort_unless(isset(self::CLASSES[$key]), 404);

        return response()->json($this->renderedParts($key));
    }

    /**
     * Send a real test email of the template's current wording to the given
     * address. Sample data is used (fake order, token, message) so nothing
     * customer-specific or live is exposed.
     */
    public function test(Request $request, string $key): JsonResponse
    {
        abort_unless(isset(self::CLASSES[$key]), 404);

        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);

        try {
            Notification::route('mail', $validated['email'])->notify(self::CLASSES[$key]::sample());
        } catch (Throwable $exception) {
            return response()->json(['message' => 'Could not send the test email: '.$exception->getMessage()], 502);
        }

        return response()->json(['message' => 'A sample '.$this->label($key).' test email is on its way to '.$validated['email'].'.']);
    }

    /**
     * The resolved parts for a template with sample context, reflecting any
     * active override, plus the untouched built-in wording the editor shows
     * as placeholders so blanks always mean "fall back to default".
     *
     * @return array{preview: array<string, mixed>, defaults: array<string, mixed>}
     */
    protected function renderedParts(string $key): array
    {
        [$defaults, $context] = self::CLASSES[$key]::sample()->parts('Aisha');
        $service = app(EmailTemplates::class);

        return [
            'preview' => $service->resolve($key, $defaults, $context),
            'defaults' => $service->resolve($key, $defaults, $context, true),
        ];
    }

    protected function hasContent(EmailTemplate $template): bool
    {
        return collect(['subject', 'greeting', 'body', 'action_label', 'salutation'])
            ->contains(fn (string $field): bool => filled($template->{$field}));
    }

    protected function label(string $key): string
    {
        return config('email-templates.templates.'.$key.'.label', 'email');
    }
}
