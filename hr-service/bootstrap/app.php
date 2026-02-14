<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException as DatabaseQueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $exception, $request) {
                
                if ($exception instanceof ValidationException) {
                    return response()->json([
                        "status" => false, 
                        'message' => $exception->getMessage(),
                        'errors' => $exception->errors(),
                    ], 422);
                }

                // Model Not Found Exception
                if ($exception instanceof ModelNotFoundException) {
                    return response()->json([
                        'error' => 'Resource not found',
                        'message' => $exception->getMessage(),
                    ], 404);
                }

                // Authorization Exception
                if ($exception instanceof AuthorizationException) {
                    return response()->json([
                        'error' => 'Unauthorized',
                        'message' => $exception->getMessage(),
                    ], 403);
                }

                // Authentication Exception
                if ($exception instanceof AuthenticationException) {
                    return response()->json([
                        'error' => 'Unauthenticated',
                        'message' => "You are not logged in. Please log in to continue.",
                    ], 401);
                }

                // Method Not Allowed Exception
                if ($exception instanceof MethodNotAllowedHttpException) {
                    return response()->json([
                        'error' => 'Method Not Allowed',
                        'message' => $exception->getMessage(),
                    ], 405);
                }

                // General HTTP Exception
                if ($exception instanceof HttpException) {
                    return response()->json(['error' => $exception->getMessage()], $exception->getStatusCode());
                }

                // Database Query Exception
                if ($exception instanceof DatabaseQueryException) {
                    return response()->json(['error' => $exception->getMessage()], 500);
                }

                // Log the exception
                // event_log($exception->getMessage(), 'error', "{$exception->getFile()}:{$exception->getLine()}");
                return response()->json(['error' => "{$exception->getFile()}:{$exception->getLine()}", 'message' =>  $exception->getMessage()], 500);
        });
    })->create();
