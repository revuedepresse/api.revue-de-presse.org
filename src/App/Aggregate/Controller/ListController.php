<?php

namespace App\Aggregate\Controller;

use App\Aggregate\Repository\TimelyStatusRepository;
use App\Security\Cors\CorsHeadersAwareTrait;
use Doctrine\ORM\NonUniqueResultException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use WeavingTheWeb\Bundle\ApiBundle\Repository\AggregateRepository;
use WTW\UserBundle\Repository\UserRepository;

class ListController
{
    use CorsHeadersAwareTrait;

    /**
     * @var AggregateRepository
     */
    public $aggregateRepository;

    /**
     * @var UserRepository
     */
    public $memberRepository;

    /**
     * @var TimelyStatusRepository
     */
    public $timelyStatusRepository;

    /**
     * @var string
     */
    public $environment;

    /**
     * @var string
     */
    public $allowedOrigin;

    /**
     * @var LoggerInterface
     */
    public $logger;

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getAggregates(Request $request)
    {
        return $this->getCollection(
            $request,
            $counter = function (SearchParams $searchParams) {
                return $this->aggregateRepository->countTotalPages($searchParams);
            },
            $finder = function (SearchParams $searchParams) {
                return $this->aggregateRepository->findAggregates($searchParams);
            }
        );
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getMembers(Request $request)
    {
        return $this->getCollection(
            $request,
            $counter = function (SearchParams $searchParams) {
                return $this->memberRepository->countTotalPages($searchParams);
            },
            $finder = function (SearchParams $searchParams) {
                return $this->memberRepository->findMembers($searchParams);
            },
            ['aggregateId' => 'int']
        );
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getStatuses(Request $request)
    {
        return $this->getCollection(
            $request,
            $counter = function (SearchParams $searchParams) {
                return $this->timelyStatusRepository->countTotalPages($searchParams);
            },
            $finder = function (SearchParams $searchParams) {
                return $this->timelyStatusRepository->findStatuses($searchParams);
            },
            ['memberName' => 'string']
        );
    }

    /**
     * @param Request  $request
     * @param callable $counter
     * @param callable $finder
     * @param array    $params
     * @return JsonResponse
     */
    private function getCollection(
        Request $request,
        callable $counter,
        callable $finder,
        array $params = []
    ): JsonResponse {
        if ($request->isMethod('OPTIONS')) {
            return $this->getCorsOptionsResponse(
                $this->environment,
                $this->allowedOrigin
            );
        }

        $searchParams = SearchParams::fromRequest($request, $params);

        try {
            $totalPages = $counter($searchParams);
        } catch (NonUniqueResultException $exception) {
            $this->logger->critical($exception->getMessage());

            return new JsonResponse('Sorry, an unexpected error has occurred', 501);
        }

        $totalPagesHeader = ['x-total-pages' => $totalPages];
        $pageIndexHeader = ['x-page-index' => $searchParams->getPageIndex()];

        if ($searchParams->getPageIndex() > $totalPages) {
            $response = new JsonResponse([]);
            $response->headers->add($totalPagesHeader);
            $response->headers->add($pageIndexHeader);

            return $response;
        }

        $aggregates = $finder($searchParams);

        $response = new JsonResponse($aggregates);
        $response->headers->add($totalPagesHeader);
        $response->headers->add($pageIndexHeader);

        return $response;
    }
}
