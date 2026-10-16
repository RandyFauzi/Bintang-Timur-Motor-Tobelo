<?php

namespace App\Http\Middleware;

use App\Services\ActivityRecorder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class RecordActivity
{
    public function __construct(private ActivityRecorder $recorder) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $request->attributes->set('audit.initial_errors', $request->hasSession() ? $request->session()->get('errors') : null);
        $this->recorder->begin($request->user());
        $status = 500;
        try {
            $response = $next($request);
            $status = $response->getStatusCode();
            if ($request->routeIs('activity-logs.*')) {
                $response->headers->set('Cache-Control', 'private, no-store');
            }

            return $response;
        } catch (Throwable $exception) {
            $status = $exception instanceof ValidationException ? 422 : ($exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500);
            throw $exception;
        } finally {
            $this->recorder->finish($request, $status, $startedAt);
        }
    }
}
