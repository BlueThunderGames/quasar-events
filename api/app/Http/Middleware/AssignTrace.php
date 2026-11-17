<?php

namespace App\Http\Middleware;

use App\Observability\Trace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AssignTrace
{
    public function __construct(private Trace $trace) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->trace->begin($request);

        $response = $this->trace->span('http.request', function () use ($next, $request) {
            $this->trace->add('http_method', $request->getMethod());
            $this->trace->add('http_route', '/'.$request->path());

            $response = $next($request);

            $this->trace->add('http_status', $response->getStatusCode());

            return $response;
        });

        return $this->trace->stamp($response);
    }
}
