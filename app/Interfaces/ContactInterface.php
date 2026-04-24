<?php

namespace App\Interfaces;

interface ContactInterface
{
    public function sendContactMessage(array $data): void;
}