<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // An expired/invalid CSRF token (419) happens when the session
        // lapses while a big form (e.g. the workout plan builder) sits
        // open. Send the user back with their input intact and a clear
        // message instead of the bare "Page Expired" screen.
        // NOTE: by the time render callbacks run, Laravel's Handler has
        // already mapped TokenMismatchException to Symfony HttpException
        // with status 419 (see prepareException), so we must match THAT
        // type here — a callback on TokenMismatchException never fires.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            // An expired/invalid CSRF token (419) happens when the session
            // lapses while a big form (e.g. the workout plan builder) sits
            // open. Send the user back with their input intact and a clear
            // message instead of the bare "Page Expired" screen.

            if ($request->expectsJson()) {
                return response()->json(['message' => __('Your session expired. Please refresh and try again.')], 419);
            }

            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->withErrors(['session' => __('Your session expired — nothing was saved. Please press save again.')]);
        });
    })->create();
