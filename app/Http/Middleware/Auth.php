<?php

namespace App\Http\Middleware;

use App\Models\Db\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Auth
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if(!session()->has('uid')) {
            $authorization = $request->header('Authorization');
            if (empty($authorization) || !str_contains($authorization, 'Basic ')) {
                return response()->json(['error: Unauthorized'], Response::HTTP_UNAUTHORIZED)->header('WWW-Authenticate', 'Basic realm="Restricted Area"');
            }
            $authorization = base64_decode(substr($authorization, 6));
            [$username, $password] = explode(':', $authorization, 2);
            $info = User::getInstance()->getInfoByUserAndPass($username, md5($authorization));
            if (empty($info)) {
                return response()->json(['error: Unauthorized'], Response::HTTP_UNAUTHORIZED)->header('WWW-Authenticate', 'Unauthorized');
            }
            session(['uid' => $info['id'], 'username' => $info['username'], 'email' => $info['email']]);
        }
        if(!in_array($request->getMethod(), ['OPTIONS', 'PROPFIND']) && !str_starts_with($request->getRequestUri(), '/' . session('username') . '/calendars/')) {
            return response('',  Response::HTTP_FORBIDDEN);
        }
        return $next($request);
    }
}