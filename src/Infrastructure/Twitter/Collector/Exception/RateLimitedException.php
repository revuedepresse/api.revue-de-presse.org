<?php
declare(strict_types=1);

namespace App\Infrastructure\Twitter\Collector\Exception;

class RateLimitedException extends SkipCollectException
{
}
