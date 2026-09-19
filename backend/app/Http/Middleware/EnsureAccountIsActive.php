<?php

namespace App\Http\Middleware;

use App\Actions\Auth\TerminateSession;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function __construct(
        private readonly TerminateSession $terminateSession,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isUsable()) {
            $this->terminateSession->handle($request);

            throw new AuthenticationException;
        }

        return $next($request);
    }
}
