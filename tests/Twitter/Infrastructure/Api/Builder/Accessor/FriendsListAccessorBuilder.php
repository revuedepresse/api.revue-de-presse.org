<?php
declare (strict_types=1);

namespace App\Tests\Twitter\Infrastructure\Api\Builder\Accessor;

use App\Twitter\Infrastructure\Http\Accessor\FriendsListAccessor;
use App\Twitter\Infrastructure\Http\Accessor\ListAccessorInterface;
use App\Twitter\Domain\Http\ApiAccessorInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Log\NullLogger;

class FriendsListAccessorBuilder extends TestCase
{
    /**
     * @return ListAccessorInterface
     */
    public static function make(): ListAccessorInterface
    {
        $testCase = new self();

        /** @var ApiAccessorInterface $apiAccessor */
        $apiAccessor = $testCase->prophesize(ApiAccessorInterface::class);
        $apiAccessor->getApiBaseUrl()->willReturn('https://twitter.api');
        $apiAccessor->contactEndpoint(Argument::any())
            ->will(function ($arguments) {
                $endpoint = $arguments[0];

                if (strpos($endpoint, 'cursor=-1') !== false) {
                    $resourcePath = '../../../../../Resources/FriendsList-1-2.b64';
                } else if (strpos($endpoint, 'cursor=1558953799112594071') !== false) {
                    $resourcePath = '../../../../../Resources/FriendsList-2-2.b64';
                } else {
                    return [];
                }

                return unserialize(
                    base64_decode(
                        file_get_contents(__DIR__ . '/' .$resourcePath)
                    )
                );
            });

        return new FriendsListAccessor(
            $apiAccessor->reveal(),
            new NullLogger()
        );
    }
}
