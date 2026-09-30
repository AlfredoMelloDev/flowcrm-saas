<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class LeadAlreadyConvertedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This lead has already been converted.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}
