<?php
declare(strict_types=1);

namespace App\Infrastructure\Security\Authentication;

use App\PublicationList\Controller\Exception\InvalidRequestException;
use App\Api\Entity\Token;
use App\Infrastructure\DependencyInjection\TokenRepositoryTrait;
use Symfony\Component\HttpFoundation\JsonResponse;

trait AuthenticationTokenValidationTrait
{
    use TokenRepositoryTrait;

    /**
     * @param $corsHeaders
     *
     * @return Token
     * @throws InvalidRequestException
     */
    private function guardAgainstInvalidAuthenticationToken($corsHeaders): Token
    {
        $token = $this->tokenRepository->findFirstUnfrozenToken();
        if (!($token instanceof Token)) {
            $exceptionMessage = 'Could not process your request at the moment';
            $jsonResponse = new JsonResponse(
                $exceptionMessage,
                503,
                $corsHeaders
            );

            InvalidRequestException::guardAgainstInvalidRequest($jsonResponse, $exceptionMessage);
        }

        return $token;
    }
}
