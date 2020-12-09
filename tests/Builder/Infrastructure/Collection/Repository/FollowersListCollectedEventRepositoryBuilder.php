<?php
declare (strict_types=1);

namespace App\Tests\Builder\Infrastructure\Collection\Repository;

use App\Infrastructure\Collection\Repository\FollowersListCollectedEventRepository;
use App\Infrastructure\Collection\Repository\ListCollectedEventRepositoryInterface;
use App\Infrastructure\Twitter\Api\Accessor\ListAccessorInterface;
use App\Tests\Builder\Twitter\Api\Accessor\FollowersListAccessorBuilder;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;

class FollowersListCollectedEventRepositoryBuilder extends TestCase
{
    /**
     * @return ListCollectedEventRepositoryInterface
     */
    public static function make(): ListCollectedEventRepositoryInterface
    {
        $testCase = new self();
        $prophecy = $testCase->prophesize(FollowersListCollectedEventRepository::class);
        $prophecy->aggregatedLists(
            Argument::type(ListAccessorInterface::class),
            Argument::type('string')
        )->will(function ($arguments) {
            $followersListAccessor = FollowersListAccessorBuilder::make();

            return $followersListAccessor->getListAtDefaultCursor($arguments[1]);
        });

        return $prophecy->reveal();
    }
}