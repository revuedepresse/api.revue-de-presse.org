<?php
declare(strict_types=1);

namespace App\Twitter\Infrastructure\Http\Moderator;

use App\Twitter\Infrastructure\Http\Throttling\ApiLimitModeratorInterface;
use Psr\Log\LoggerInterface;

/**
 * @author revue-de-presse.org <thierrymarianne@users.noreply.github.com>
 */
class ApiLimitModerator implements ApiLimitModeratorInterface
{
    /**
     * @var LoggerInterface $logger
     */
    protected LoggerInterface $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * @param $seconds
     * @param array $parameters
     */
    public function waitFor($seconds, array $parameters = []): void
    {
        if ($this->logger !== null) {
            if ($seconds < 60) {
                $humanlyReadableWaitTime = $seconds.' more seconds';
            } else {
                $humanlyReadableWaitTime = floor($seconds / 60).' more minutes';
            }

            $message = 'API limit has been reached for token "{{ token }}...", '.
                'operations are currently frozen (waiting for {{ wait_time }} )';
            ;
            $parameters['{{ wait_time }}'] = $humanlyReadableWaitTime;
            $this->logger->info(strtr($message, $parameters));
        }

        if ($seconds > 0) {
            sleep($seconds);
        }
    }
}
