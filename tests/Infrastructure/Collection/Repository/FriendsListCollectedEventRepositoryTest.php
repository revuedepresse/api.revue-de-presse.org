<?php
declare (strict_types=1);

namespace App\Tests\Infrastructure\Collection\Repository;

use App\Infrastructure\Curation\Repository\FriendsListCollectedEventRepository;
use App\Infrastructure\Twitter\Api\Selector\FriendsListSelector;
use App\Tests\Builder\Infrastructure\Twitter\Api\Accessor\FriendsListAccessorBuilder;
use Ramsey\Uuid\Rfc4122\UuidV4;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @group member_subscription
 */
class FriendsListCollectedEventRepositoryTest extends KernelTestCase
{
    private const SCREEN_NAME = 'mipsytipsy';

    private FriendsListCollectedEventRepository $repository;

    public function setUp(): void
    {
        self::$kernel = self::bootKernel();
        self::$container = self::$kernel->getContainer();
        $this->repository = self::$container->get('test.'.FriendsListCollectedEventRepository::class);

        $this->truncateEventStore();
    }

    /**
     * @test
     */
    public function it_should_collect_friends_list_of_a_member(): void
    {
        $accessor = FriendsListAccessorBuilder::make();

        $friendsList = $this->repository->collectedList(
            $accessor,
            new FriendsListSelector(
                UuidV4::uuid4(),
                self::SCREEN_NAME
            )
        );

        self::assertEquals(200, $friendsList->count());

        $memberFriendsListCollectedEvents = $this->repository->findBy(['screenName' => self::SCREEN_NAME]);
        self::assertCount(1, $memberFriendsListCollectedEvents);
    }

    protected function tearDown(): void
    {
        $this->truncateEventStore();
    }

    private function truncateEventStore(): void
    {
        $entityManager = self::$container->get('doctrine.orm.entity_manager');
        $connection = $entityManager->getConnection();
        $connection->executeQuery('TRUNCATE TABLE member_friends_list_collected_event');
    }
}