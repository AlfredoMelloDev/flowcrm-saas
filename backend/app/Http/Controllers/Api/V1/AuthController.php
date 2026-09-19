<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\RegisterCompany;
use App\Actions\Auth\TerminateSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterCompany $registerCompany): JsonResponse
    {
        $user = $registerCompany->handle(
            $request->validated('company'),
            $request->validated('user'),
        );

        Auth::login($user);
        $request->session()->regenerate();

        return (new UserResource($user->load('company')))
            ->additional(['message' => 'Company and user registered successfully.'])
            ->response()
            ->setStatusCode(201);
    }

    public function login(LoginRequest $request, TerminateSession $terminateSession): JsonResponse
    {
        if (! Auth::attempt($request->validated())) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $user = $request->user()->load('company');

        if (! $user->isUsable()) {
            $terminateSession->handle($request);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        return (new UserResource($user))
            ->additional(['message' => 'Login successful.'])
            ->response();
    }

    public function logout(Request $request, TerminateSession $terminateSession): JsonResponse
    {
        $terminateSession->handle($request);

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): JsonResponse
    {
        return (new UserResource($request->user()->load('company')))
            ->response();
    }
}
