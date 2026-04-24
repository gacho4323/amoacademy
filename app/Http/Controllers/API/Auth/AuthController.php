<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\FreeCourseRegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\SocialAuthRequest;
use App\Http\Requests\UpdateCredentialsRequest;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function register(RegisterRequest $request)
    {
        $user = $this->authService->register($request);
        $token = $user->createToken('token')->plainTextToken;
        return response(compact('user', 'token'), 201);
    }

    public function registerFreeCourse(FreeCourseRegisterRequest $request)
    {
        try {
            $user = $this->authService->registerFreeCourse($request);
            return response()->json([
                'message' => 'Registration successful!',
                'user' => $user,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Free course registration failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Registration failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function login(LoginRequest $request)
    {
        $check = $this->authService->login($request);
        if (!$check) {
            return response()->json(['message' => 'Invalid credentials!'], 401);
        }
        $user = Auth::user();
        $token = $user->createToken('token')->plainTextToken;
        return response()->json([
            'user' => $user,
            'token' => $token,
            'message' => 'Login successful!',
        ]);
    }

    public function logout()
    {
        $this->authService->logout(request());
        return response()->json(['message' => 'Logged out successfully!']);
    }

    public function user()
    {
        \Log::info('User endpoint called', [
            'auth_user_id' => Auth::id(),
            'token' => request()->bearerToken(),
        ]);
        $user = Auth::user();
        if (!$user) {
            \Log::warning('No authenticated user found', [
                'token' => request()->bearerToken(),
            ]);
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        \Log::info('User retrieved successfully', ['user_id' => $user->id, 'email' => $user->email]);
        return response()->json(['user' => $user]);
    }

    public function updateCredentials(UpdateCredentialsRequest $request)
    {
        try {
            $user = $this->authService->updateCredentials($request);
            return response()->json([
                'user' => $user,
                'message' => 'Credentials updated successfully!',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating credentials: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function notifications()
    {
        return response()->json(['notifications' => Auth::user()->notifications]);
    }

    public function redirectToProvider($provider)
    {
        if (!in_array($provider, ['google', 'facebook'])) {
            return response()->json(['message' => 'Invalid provider'], 400);
        }
        try {
            $url = Socialite::driver($provider)
                ->stateless(false)
                ->redirectUrl('https://amoacademy.net/auth/' . $provider . '/callback')
                ->redirect()
                ->getTargetUrl();
            return response()->json(['redirect_url' => $url], 200);
        } catch (\Exception $e) {
            Log::error("Social login redirect failed for {$provider}: {$e->getMessage()}");
            return response()->json(['message' => 'Failed to initiate social login'], 500);
        }
    }

    public function handleProviderCallback(Request $request, $provider)
    {
        \Log::info('OAuth callback started', [
            'provider' => $provider,
            'request' => $request->all(),
            'session' => session()->all(),
        ]);
        try {
            $socialUser = Socialite::driver($provider)
                ->stateless(false)
                ->redirectUrl('https://amoacademy.net/auth/' . $provider . '/callback')
                ->user();
            \Log::info('Socialite user retrieved', [
                'provider' => $provider,
                'social_user' => [
                    'id' => $socialUser->id,
                    'email' => $socialUser->email,
                    'name' => $socialUser->name,
                ],
            ]);
            $user = $this->authService->handleSocialAuth($socialUser, $provider);
            \Log::info('User processed', ['user_id' => $user->id, 'email' => $user->email]);
            $token = $user->createToken('auth_token')->plainTextToken;
            \Log::info('Token generated', ['token' => $token]);
            $type = $user->wasRecentlyCreated ? 'register' : 'login';
            $redirectUrl = 'https://amoacademy.net/login-success?token=' . urlencode($token) . '&type=' . urlencode($type);
            \Log::info('Redirecting to login-success', ['url' => $redirectUrl]);
            return redirect()->to($redirectUrl);
        } catch (\Exception $e) {
            \Log::error('OAuth callback failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'session' => session()->all(),
            ]);
            return redirect()->to('https://amoacademy.net/auth/error?message=' . urlencode('Authentication failed: ' . $e->getMessage()));
        }
    }
}