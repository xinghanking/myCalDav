<?php

namespace App\Http\Middleware;

use App\Models\Db\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\HttpKernel\Log\Logger;

use function Laravel\Prompts\select;

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
                return response()->json(['error: Unauthorized'], 401)->header('WWW-Authenticate', 'Basic realm="Restricted Area"');
            }
            $authorization = base64_decode(substr($authorization, 6));
            [$username, $password] = explode(':', $authorization, 2);
            $info = User::getInstance()->getInfoByUserAndPass($username, md5($authorization));
            if (empty($info)) {
                return response()->json(['error: Unauthorized'], 401)->header('WWW-Authenticate', 'Unauthorized');
            }
            session(['uid' => $info['id'], 'username' => $info['username'], 'email' => $info['email']]);
        }
        $requestInfo = [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'headers' => $request->headers->all(),
            'body' => $request->getContent()
        ];
        Log::info('requestInfo', $requestInfo);
        $response = $next($request);
        $responseInfo = [
            'status_code' => $response->getStatusCode(),
            'headers' => $response->headers->all(),
            'body' => $response->getContent()
        ];
        Log::info('responseInfo', $responseInfo);
        return $response;
    }
}
