<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\WelcomeEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );

        try {
            $user->notify(new WelcomeEmail($verificationUrl));
        } catch (Throwable $exception) {
            Log::error('Unable to send the welcome email.', ['user_id' => $user->id, 'exception' => $exception->getMessage()]);
        }

        return response()->json([
            'user' => $user->refresh(),
            'message' => 'Account created. Please verify your email before signing in.',
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user = Auth::user();
        if ($user->role === 'customer' && $user->customer_status !== 'active') {
            Auth::logout();

            return response()->json(['message' => 'Your customer account is '.$user->customer_status.'. Please contact support.'], 403);
        }
        if ($user->role === 'customer' && ! $user->hasVerifiedEmail()) {
            Auth::logout();

            return response()->json(['message' => 'Please verify your email address before signing in.'], 403);
        }
        $user->last_activity_at = now();
        $user->saveQuietly();
        $token = $user->createToken('auth-token');

        return response()->json([
            'user' => $user,
            'access_token' => $token->plainTextToken,
            'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    public function me()
    {
        return response()->json(Auth::user());
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        if ($data['email'] !== $user->email) {
            // A new address must be proven again before it counts as verified.
            $user->email_verified_at = null;
        }

        $user->update($data);

        return response()->json($user->fresh());
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('The current password is incorrect.'),
            ]);
        }

        $request->user()->update(['password' => $data['password']]);

        return response()->json(['message' => 'Password updated.']);
    }

    public function users()
    {
        return response()->json(User::all()->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->role])->toArray());
    }
}
