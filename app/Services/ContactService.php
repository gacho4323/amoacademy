<?php

namespace App\Services;

use App\Interfaces\ContactInterface;

class ContactService
{
    private ContactInterface $contactRepository;

    public function __construct(ContactInterface $contactRepository)
    {
        $this->contactRepository = $contactRepository;
    }

    public function sendContactMessage(array $data): void
    {
        $this->contactRepository->sendContactMessage($data);
    }
}