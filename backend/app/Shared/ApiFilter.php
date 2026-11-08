<?php
namespace App\Shared;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class ApiFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if ($request->getMethod() === 'OPTIONS') {
            return service('response')->setStatusCode(204);
        }
        if (str_starts_with($request->getUri()->getPath(), '/api/') && strlen($request->getBody()) > 1048576) {
            return ApiController::error(new ApiException(413, 'Nội dung vượt quá 1 MB.', 'PAYLOAD_TOO_LARGE'));
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $origin = $request->getHeaderLine('Origin');
        $allowed = array_filter(array_map('trim', explode(',', getenv('CORS_ORIGINS') ?: 'http://localhost:5000,http://127.0.0.1:5000')));
        if ($origin !== '' && in_array($origin, $allowed, true)) {
            $response->setHeader('Access-Control-Allow-Origin', $origin)->setHeader('Vary', 'Origin');
            $response->setHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type');
            $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        }
        $response->setHeader('Cache-Control', 'no-store');
        $response->setHeader('X-Content-Type-Options', 'nosniff');
    }
}
