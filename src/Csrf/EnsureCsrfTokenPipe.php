<?php

namespace Handlr\Csrf;

use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Pipes\Pipe;

class EnsureCsrfTokenPipe implements Pipe
{
    public function __construct(private readonly CsrfService $csrf)
    {
    }

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        $this->csrf->ensureToken();

        $response = $next($request, $response, $args);

        $token = $this->csrf->ensureToken();

        // Cookie transport — all browser tabs share the same cookie, preventing
        // multi-tab desync that occurs with the in-memory JS variable approach.
        setcookie('XSRF-TOKEN', $token, [
            'path' => '/',
            'httponly' => false, // JS must read this to send it as a header
            'samesite' => 'Lax',
            'secure' => !empty($_SERVER['HTTPS']),
        ]);

        return $response->withHeader('X-CSRF-Token', $token);
    }
}
