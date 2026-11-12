<?php

namespace App\Http\Middleware;

use App\Services\SiteSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Apply the website timezone on every request, before any DB write. */
class ApplySiteSettings
{
    public function __construct(private readonly SiteSettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->settings->applyRuntimeConfig();

        return $next($request);
    }
}
