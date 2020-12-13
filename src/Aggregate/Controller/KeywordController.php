<?php


namespace App\Aggregate\Controller;

use App\Infrastructure\Cache\RedisCache;
use App\Infrastructure\Security\Cors\CorsHeadersAwareTrait;
use App\Infrastructure\Publication\Repository\KeywordRepository;
use Doctrine\DBAL\DBALException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class KeywordController
{
    use CorsHeadersAwareTrait;

    /**
     * @var RedisCache
     */
    public RedisCache $redisCache;

    /**
     * @var KeywordRepository
     */
    public KeywordRepository $keywordRepository;

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws DBALException
     */
    public function getKeywords(Request $request)
    {
        if ($request->isMethod('OPTIONS')) {
            return $this->getCorsOptionsResponse(
                $this->environment,
                $this->allowedOrigin
            );
        }

        $startDate = new \DateTime($request->query->get('startDate'));
        $endDate = new \DateTime($request->query->get('endDate'));

        $keywords = $this->keywordRepository->getKeywords($startDate, $endDate);

        return new JsonResponse($keywords);
    }
}
