<?php

namespace App\Repositories;

use App\Interfaces\AuthInterface;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthRepository implements AuthInterface
{
    public function register($request)
    {
        return User::create($request);
    }

    public function registerFreeCourse($request)
    {
        return User::create($request);
    }

    public function updateCredentials($user, $data)
    {
        $user->update($data);
        return $user;
    }

    public function handleSocialAuth($provider, $socialUser)
    {
        $user = User::where('email', $socialUser->email)
            ->orWhere('provider_id', $socialUser->id)
            ->where('provider', $provider)
            ->first();

        if (!$user) {
            $user = User::create([
                'name' => $socialUser->name,
                'email' => $socialUser->email,
                'provider' => $provider,
                'provider_id' => $socialUser->id,
                'password' => Hash::make(uniqid()), // Random password for social users
            ]);
        } else {
            $user->update([
                'name' => $socialUser->name,
                'provider_id' => $socialUser->id,
                'provider' => $provider,
            ]);
        }

        return $user;
    }
}