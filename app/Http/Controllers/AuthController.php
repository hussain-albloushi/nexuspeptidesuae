<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\WelcomeEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
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

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            // Registration must not fail just because the mailer is down.
            Log::error('Unable to send the verification email.', ['user_id' => $user->id, 'exception' => $exception->getMessage()]);
        }

        try {
            $user->notify(new WelcomeEmail);
        } catch (Throwable $exception) {
            Log::error('Unable to send the welcome email.', ['user_id' => $user->id, 'exception' => $exception->getMessage()]);
        }

        $token = $user->createToken('auth-token');

        return response()->json([
            'user' => $user->refresh(),
            'access_token' => $token->plainTextToken,
            'token_type' => 'Bearer',
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

    public function users()
    {
        return response()->json(User::all()->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->role])->toArray());
    }
}
