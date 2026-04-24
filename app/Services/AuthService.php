<?php

namespace App\Services;

use App\Http\Requests\FreeCourseRegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UpdateCredentialsRequest;
use App\Interfaces\AuthInterface;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthService
{

    /**
     * AuthService constructor.
     * @param AuthInterface $authRepository
     */
    public function __construct(protected AuthInterface $authRepository)
    {
    }

    /**
     * @param RegisterRequest $request
     * @return mixed
     */
    public function register(RegisterRequest $request): mixed
    {
        return $this->authRepository->register([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'user',
        ]);
    }

    /**
     * @param FreeCourseRegisterRequest $request
     * @return mixed
     */
    public function registerFreeCourse(FreeCourseRegisterRequest $request): mixed
    {
        $fullName = trim($request->first_name);
        $nameParts = preg_split('/\s+/', $fullName);

        $firstName = $nameParts[0] ?? '';
        $lastName = count($nameParts) > 1
            ? implode(' ', array_slice($nameParts, 1))
            : null;
        $name = $firstName . ($lastName ? ' ' . $lastName : '');

        if ($request->email) {
            if (User::where('email', $request->email)->exists()) {
                    return redirect('https://amoacademy.net/thank-you');
                }
        }

        return $this->authRepository->register([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => $name,
            'email' => $request->email,
            'consent' => $request->consent,
            'role' => 'user',
            'email_opt_in_date' => $request->consent ? now() : null,
            ]);
    }

    /**
     * @param LoginRequest $request
     * @return bool
     */
    public function login(LoginRequest $request): bool
    {
        return Auth::attempt($request->only('email', 'password'));
    }

    /**
     * @param Request $request
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
    }

    /**
     * @param UpdateCredentialsRequest $request
     * @return Authenticatable|null
     */
    public function updateCredentials(UpdateCredentialsRequest $request): ?Authenticatable
    {
        $user = Auth::user();
        $data = [];

        if ($request->filled('email') && $request->email !== $user->email) {
            $data['email'] = $request->email;
        }

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        if (empty($data)) {
            return $user;
        }

        return $this->authRepository->updateCredentials($user, $data);
    }

    /**
     * @param $socialUser
     * @param $provider
     * @return mixed
     */
    public function handleSocialAuth($socialUser, $provider): mixed
    {
        return $this->authRepository->handleSocialAuth($provider, $socialUser);
    }
}
