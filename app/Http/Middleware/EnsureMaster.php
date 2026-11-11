<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMaster
{
    public function handle(Request $request, Closure $next): Response
    {
        $master = trim((string) config('ccdae.master_email'));
        abort_if($master === '' || !hash_equals(mb_strtolower($master), mb_strtolower((string) $request->user()?->email)), 403);
        return $next($request);
    }
}
