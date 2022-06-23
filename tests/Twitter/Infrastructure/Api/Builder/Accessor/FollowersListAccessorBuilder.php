<?php
declare (strict_types=1);

namespace App\Tests\Twitter\Infrastructure\Api\Builder\Accessor;

use App\Twitter\Infrastructure\Api\Accessor\FollowersListAccessor;
use App\Twitter\Domain\Api\Accessor\ListAccessorInterface;
use App\Twitter\Domain\Api\Accessor\ApiAccessorInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophet;
use Psr\Log\NullLogger;

class FollowersListAccessorBuilder extends TestCase
{
    use ProphecyTrait;

    private $prophet;

    public function __construct()
    {
        $this->prophet = $this->getProphet();
    }

    public function prophet(): Prophet
    {
        return $this->prophet;
    }

    /**
     * @return ListAccessorInterface
     */
    public static function build(): ListAccessorInterface
    {
        $testCase = new self();

        /** @var ApiAccessorInterface $apiAccessor */
        $apiAccessor = $testCase->prophet()->prophesize(ApiAccessorInterface::class);
        $apiAccessor->getApiBaseUrl()->willReturn('https://twitter.api');
        $apiAccessor->contactEndpoint(Argument::any())
            ->will(function ($arguments) {
                $endpoint = $arguments[0];

                if (strpos($endpoint, 'cursor=-1') !== false) {
                    $resourcePath = '../../../../../Resources/FollowersList-1-2.b64';
                } else if (strpos($endpoint, 'cursor=1645049967345751374') !== false) {
                    $resourcePath = '../../../../../Resources/FollowersList-2-2.b64';
                } else {
                    return [];
                }

                return unserialize(
                    base64_decode(
                        file_get_contents(__DIR__ . '/' .$resourcePath)
                    )
                );
            });

        return new FollowersListAccessor(
            $apiAccessor->reveal(),
            new NullLogger()
        );
    }
}
