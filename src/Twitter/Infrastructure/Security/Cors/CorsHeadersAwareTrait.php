<?php
declare(strict_types=1);

namespace App\Twitter\Infrastructure\Security\Cors;

use Symfony\Component\HttpFoundation\JsonResponse;

trait CorsHeadersAwareTrait
{
    private string $environment;

    private string $allowedOrigin;

    public function __construct($allowedOrigin, $environment)
    {
        $this->allowedOrigin = $allowedOrigin;
        $this->environment = $environment;
    }

    /**
     * @param string $environment
     * @param string $allowedOrigin
     * @return array
     */
    private function getAccessControlOriginHeaders(
        string $environment,
        string $allowedOrigin
    ): array {
        if ($environment === 'prod') {
            return ['Access-Control-Allow-Origin' => $allowedOrigin];
        }

        return ['Access-Control-Allow-Origin' => '*'];
    }

    /**
     * @param string $environment
     * @param string $allowedOrigin
     * @return JsonResponse
     */
    private function getCorsOptionsResponse(
        string $environment,
        string $allowedOrigin
    ) {
        $allowedHeaders = implode(
            ', ',
            [
                'Keep-Alive',
                'User-Agent',
                'X-Requested-With',
                'If-Modified-Since',
                'Cache-Control',
                'Content-Type',
                'x-auth-token',
                'x-auth-admin-token',
                'x-total-pages',
                'x-page-index',
                'x-decompressed-content-length'
            ]
        );

        $headers = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Headers' => $allowedHeaders,
            'Access-Control-Expose-Headers' => $allowedHeaders,
        ];
        if ($environment === 'prod') {
            $headers = [
                'Access-Control-Allow-Origin' => $allowedOrigin,
                'Access-Control-Allow-Headers' => $allowedHeaders,
                // @see https://stackoverflow.com/a/37931084/282073
                'Access-Control-Expose-Headers' => $allowedHeaders,
            ];
        }

        return new JsonResponse(
            [],
            200,
            $headers
        );
    }
}
