<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;


class SensorController extends Controller
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
