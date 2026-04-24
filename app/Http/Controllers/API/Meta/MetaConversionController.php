<?php

namespace App\Http\Controllers\API\Meta;

use App\Services\MetaConversionsService;
use Illuminate\Http\Request;

class MetaConversionController extends Controller
{
    protected $metaService;

    public function __construct(MetaConversionsService $metaService)
    {
        $this->metaService = $metaService;
    }

    public function sendEvent(Request $request)
    {
        $validated = $request->validate([
            'event_name' => 'required|string',
            'user_data.email' => 'nullable|email',
            'user_data.phone' => 'nullable|string',
            'user_data.fbc' => 'nullable|string',
            'user_data.fbp' => 'nullable|string',
            'custom_data' => 'nullable|array',
            'event_source_url' => 'nullable|url',
        ]);

        $result = $this->metaService->sendEvent($validated);

        if ($result) {
            return response()->json(['success' => true, 'data' => $result], 200);
        }

        return response()->json(['success' => false, 'message' => 'Failed to send event'], 500);
    }
}