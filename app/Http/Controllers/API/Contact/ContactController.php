<?php

namespace App\Http\Controllers\API\Contact;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContactRequest;
use App\Services\ContactService;
use Illuminate\Http\JsonResponse;

class ContactController extends Controller
{
    private ContactService $contactService;

    public function __construct(ContactService $contactService)
    {
        $this->contactService = $contactService;
    }

    public function store(ContactRequest $request): JsonResponse
    {
        try {
            $this->contactService->sendContactMessage($request->validated());

            return response()->json(['message' => 'Contact message sent successfully'], 200);
        } catch (\Exception $e) {
            \Log::error('Failed to send contact message: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'message' => 'Failed to send contact message',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}