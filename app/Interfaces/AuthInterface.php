<?php

namespace App\Interfaces;

interface AuthInterface
{
    public function register($request);
    public function registerFreeCourse($request);
    public function updateCredentials($user, $data);
    public function handleSocialAuth($provider, $socialUser);
}