<?php

namespace App\Http\Controllers;

use App\Http\Requests\BesideWebhookRequest;
use App\Services\BesideWebhookService;
use Illuminate\Http\JsonResponse;

class BesideWebhookController extends Controller
{
    public function __invoke(BesideWebhookRequest $request, BesideWebhookService $service): JsonResponse
    {
        $result = $service->receive($request->validated());

        return response()->json([
            'accepted' => true,
            'created' => $result['created'],
            'updated' => $result['updated'],
            'interaction_id' => $result['interaction']->id,
            'match_status' => $result['interaction']->match_status->value,
        ], $result['created'] ? 201 : 200);
    }
}
