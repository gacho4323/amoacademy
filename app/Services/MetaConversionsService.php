<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaConversionsService
{
    protected $pixelId;
    protected $accessToken;
    protected $apiEndpoint;

    public function __construct()
    {
        $this->pixelId = config('services.meta.pixel_id');
        $this->accessToken = config('services.meta.access_token');
        $this->apiEndpoint = config('services.meta.api_endpoint');
    }

    public function sendEvent(array $eventData)
    {
        $payload = [
            'data' => [
                [
                    'event_name' => $eventData['event_name'], // e.g., 'Purchase', 'Lead'
                    'event_time' => time(), // Current timestamp in seconds
                    'action_source' => 'website', // Source of the event
                    'event_source_url' => $eventData['event_source_url'] ?? url()->current(),
                    'user_data' => [
                        'em' => $this->hash($eventData['user_data']['email'] ?? null), // Hashed email
                        'ph' => $this->hash($eventData['user_data']['phone'] ?? null), // Hashed phone
                        'client_ip_address' => request()->ip(),
                        'client_user_agent' => request()->userAgent(),
                        'fbc' => $eventData['user_data']['fbc'] ?? null, // Click ID from fbclid
                        'fbp' => $eventData['user_data']['fbp'] ?? null, // Browser ID from _fbp cookie
                    ],
                    'custom_data' => $eventData['custom_data'] ?? [], // e.g., ['value' => 100, 'currency' => 'USD']
                ],
            ],
            'access_token' => $this->accessToken,
        ];

        try {
            $response = Http::post("{$this->apiEndpoint}/{$this->pixelId}/events", $payload);

            if ($response->successful()) {
                Log::info('Meta Conversion API event sent successfully', ['response' => $response->json()]);
                return $response->json();
            } else {
                Log::error('Failed to send Meta Conversion API event', ['error' => $response->json()]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Exception while sending Meta Conversion API event', ['exception' => $e->getMessage()]);
            return false;
        }
    }

    protected function hash($data)
    {
        return $data ? hash('sha256', trim(strtolower($data))) : null;
    }
}