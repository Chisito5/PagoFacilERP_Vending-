<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorrelacionApiMiddleware
{
    public function handle(Request $toRequest, Closure $tfNext): Response
    {
        $tcCorrelationId = trim((string)$toRequest->header('X-Correlation-Id', ''));
        if ($tcCorrelationId === '') {
            $tcCorrelationId = (string)\Illuminate\Support\Str::uuid();
        }

        $tcRequestId = trim((string)$toRequest->header('X-Request-Id', ''));
        if ($tcRequestId === '') {
            $tcRequestId = (string)\Illuminate\Support\Str::uuid();
        }

        $toRequest->attributes->set('CorrelationId', $tcCorrelationId);
        $toRequest->attributes->set('RequestId', $tcRequestId);

        $toResponse = $tfNext($toRequest);
        $toResponse->headers->set('X-Correlation-Id', $tcCorrelationId);
        $toResponse->headers->set('X-Request-Id', $tcRequestId);

        return $toResponse;
    }
}

