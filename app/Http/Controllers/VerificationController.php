<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Throwable;

class VerificationController extends Controller
{
    /**
     * Web callback linked from the verification email. Verifies the signed
     * URL by hand (instead of the `signed` middleware) so expired or broken
     * links redirect to the storefront with a friendly notice.
     */
    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::find($id);

        if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash) || ! URL::hasValidSignature($request)) {
            return redirect('/my-account?verified=invalid');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect('/my-account?verified=1');
    }

    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Your email address is already verified.']);
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            Log::error('Unable to send the verification email.', ['user_id' => $user->id, 'exception' => $exception->getMessage()]);

            return response()->json(['message' => 'We could not send the verification email right now. Please try again shortly.'], 503);
        }

        return response()->json(['message' => 'A new verification link has been sent to '.$user->email.'.']);
    }
}
