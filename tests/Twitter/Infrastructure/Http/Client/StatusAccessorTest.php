<?php
declare(strict_types=1);

namespace App\Tests\Twitter\Infrastructure\Http\Client\Client;

use App\Twitter\Infrastructure\Http\Client\TweetAwareHttpClient;
use App\Twitter\Infrastructure\Curation\CurationSelectors;
use App\Twitter\Domain\Publication\Repository\StatusRepositoryInterface;
use App\Twitter\Infrastructure\Amqp\Message\FetchTweetInterface;
use App\Twitter\Domain\Http\Client\TweetAwareHttpClientInterface;
use App\Twitter\Domain\Publication\Repository\ExtremumAwareInterface;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @group status_accessor
 */
class StatusAccessorTest extends KernelTestCase
{
    use ProphecyTrait;

    private TweetAwareHttpClientInterface $accessor;

    /**
     * @return object|void|null
     */
    protected function setUp(): void
    {
        self::$kernel    = self::bootKernel();

        $this->accessor = static::getContainer()->get(TweetAwareHttpClient::class);
    }
    /**
     * @test
     *
     * @throws
     */
    public function it_should_update_extremum_for_ascending_order_finding(): void
    {
        $selectors = CurationSelectors::fromArray(
            [
                FetchTweetInterface::BEFORE       => '2010-01-01',
                FetchTweetInterface::TWITTER_LIST_ID => 1
            ]
        );

        $statusRepository = $this->prophesizeStatusRepositoryForAscendingOrderFinding();
        $this->accessor->setStatusRepository($statusRepository->reveal());

        $options = $this->accessor->updateExtremum(
            $selectors,
            [
                FetchTweetInterface::SCREEN_NAME => 'pierrec'
            ],
            false
        );

        self::assertArrayHasKey('max_id', $options);
        self::assertEquals(200, $options['max_id']);
    }

    /**
     * @test
     *
     * @throws
     */
    public function it_should_update_extremum_for_descending_order_finding(): void
    {
        $selectors = CurationSelectors::fromArray(
            [
                FetchTweetInterface::TWITTER_LIST_ID => 1
            ]
        );

        $statusRepository = $this->prophesizeStatusRepositoryForDescendingOrderFinding();
        $this->accessor->setStatusRepository($statusRepository->reveal());

        $options = $this->accessor->updateExtremum(
            $selectors,
            [
                FetchTweetInterface::SCREEN_NAME => 'pierrec'
            ],
            false
        );

        self::assertArrayHasKey('since_id', $options);
        self::assertEquals(201, $options['since_id']);
    }

    /**
     * @return ObjectProphecy
     */
    private function prophesizeStatusRepositoryForAscendingOrderFinding(): ObjectProphecy
    {
        $statusRepository = $this->prophesize(
            StatusRepositoryInterface::class
        );
        $statusRepository->findNextExtremum(
            'pierrec',
            ExtremumAwareInterface::FINDING_IN_ASCENDING_ORDER,
            '2010-01-01'
        )->willReturn(['statusId' => '201']);

        return $statusRepository;
    }

    /**
     * @return ObjectProphecy
     */
    private function prophesizeStatusRepositoryForDescendingOrderFinding(): ObjectProphecy
    {
        $statusRepository = $this->prophesize(
            StatusRepositoryInterface::class
        );
        $statusRepository->findLocalMaximum(
            'pierrec',
            null
        )->willReturn(['statusId' => '200']);

        return $statusRepository;
    }
}