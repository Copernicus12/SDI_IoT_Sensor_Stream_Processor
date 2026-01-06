<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Deprecated: PHP API disabled. The API is now served by the Spring Boot backend.
 */
class AlertController extends Controller
{
    private function gone(): JsonResponse
    {
        return response()->json(['success' => false, 'error' => 'API moved to Java backend'], 410);
    }

    public function __call($name, $arguments): JsonResponse
    {
        return $this->gone();
    }
}
 
