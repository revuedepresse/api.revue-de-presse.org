<?php
declare (strict_types=1);

namespace App\Twitter\Infrastructure\Http\Accessor;

use App\Twitter\Infrastructure\Http\Resource\FollowersList;
use App\Twitter\Infrastructure\Http\Resource\ResourceList;
use App\Twitter\Infrastructure\Http\Selector\ListSelector;
use App\Twitter\Domain\Http\ApiAccessorInterface;
use Closure;
use Psr\Log\LoggerInterface;
use Throwable;

class FollowersListAccessor implements ListAccessorInterface
{
    private ApiAccessorInterface $accessor;
    private LoggerInterface $logger;

    public function __construct(
        ApiAccessorInterface $accessor,
        LoggerInterface $logger
    ) {
        $this->accessor = $accessor;
        $this->logger = $logger;
    }

    public function getListAtCursor(
        ListSelector $selector,
        Closure $onFinishCollection = null
    ): ResourceList {
        try {
            $followersListEndpoint = $this->getFollowersListEndpoint();

            $endpoint = strtr(
                $followersListEndpoint,
                [
                    '{{ screen_name }}' => $selector->screenName(),
                    '{{ cursor }}' => $selector->cursor(),
                ]
            );

            $followersList = (array) $this->accessor->contactEndpoint($endpoint);

            if (is_callable($onFinishCollection)) {
                $onFinishCollection($followersList);
            }

            return FollowersList::fromResponse($followersList);
        } catch (Throwable $exception) {
            $this->logger->error(
                $exception->getMessage(),
                ['screen_name' => $selector->screenName()]
            );

            throw $exception;
        }
    }

    private function getFollowersListEndpoint(): string {
        return implode([
            $this->accessor->getApiBaseUrl(),
            '/followers/list.json?',
            'count=200',
            '&skip_status=false',
            '&cursor={{ cursor }}',
            '&screen_name={{ screen_name }}'
        ]);
    }
}
