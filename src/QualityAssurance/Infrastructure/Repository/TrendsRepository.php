<?php
declare (strict_types=1);

namespace App\QualityAssurance\Infrastructure\Repository;

use App\Ownership\Domain\Exception\UnknownListException;
use App\Ownership\Domain\Repository\MembersListRepositoryInterface;
use App\Ownership\Domain\Entity\MembersListInterface;
use App\QualityAssurance\Domain\Repository\TrendsRepositoryInterface;
use App\Twitter\Infrastructure\Publication\Repository\HighlightRepository;
use DateTimeInterface;
use Kreait\Firebase\Database;
use Kreait\Firebase\Database\Snapshot;
use Kreait\Firebase\Factory;
use Psr\Log\LoggerInterface;

class TrendsRepository implements TrendsRepositoryInterface
{
    private string $serviceAccountConfig;
    private string $databaseUri;

    private LoggerInterface $logger;

    private MembersListRepositoryInterface $listRepository;

    private string $defaultPublishersList;

    public function __construct(
        string $serviceAccountConfig,
        string $databaseUri,
        string $defaultPublishersList,
        MembersListRepositoryInterface $publishersListRepository,
        LoggerInterface $logger
    )
    {
        $this->serviceAccountConfig = $serviceAccountConfig;
        $this->databaseUri = $databaseUri;
        $this->defaultPublishersList = $defaultPublishersList;
        $this->listRepository = $publishersListRepository;
        $this->logger = $logger;
    }

    private function getFirebaseDatabase(): Database
    {
        return (new Factory)
            ->withServiceAccount($this->serviceAccountConfig)
            ->withDatabaseUri($this->databaseUri)
            ->createDatabase();
    }

    private function getTweetDocumentSnapshot(
        string $tweetId,
        DateTimeInterface $date
    ): Snapshot {
        $database = $this->getFirebaseDatabase();

        $publishersList = $this->listRepository->findOneBy(['name' => $this->defaultPublishersList]);

        if (!($publishersList instanceof MembersListInterface)) {
            UnknownListException::throws();
        }

        $path = '/'.implode(
            '/',
            [
                'highlights',
                $publishersList->publicId(),
                $date->format('Y-m-d'),
                'status',
                $tweetId,
                'json'
            ]
        );
        $this->logger->info(sprintf('About to access Firebase Path: "%s"', $path));
        $reference = $database->getReference($path);

        return $reference
            ->getSnapshot();
    }

    private function getTweetSnapshot(
        string $tweetId,
        DateTimeInterface $date
    ): Snapshot {
        $database = $this->getFirebaseDatabase();

        $publishersList = $this->listRepository->findOneBy(['name' => $this->defaultPublishersList]);

        if (!($publishersList instanceof MembersListInterface)) {
            UnknownListException::throws();
        }

        $path = '/'.implode(
            '/',
            [
                'highlights',
                $publishersList->publicId(),
                $date->format('Y-m-d'),
                'status',
                $tweetId
            ]
        );
        $this->logger->info(sprintf('About to access Firebase Path: "%s"', $path));
        $reference = $database->getReference($path);

        return $reference->getSnapshot();
    }

    /**
     * @throws \Kreait\Firebase\Exception\DatabaseException
     */
    public function updateTweetDocument(
        string $tweetId,
        \DateTimeInterface $date,
        string $document
    ): void {
        $snapshot = $this->getTweetDocumentSnapshot(
            $tweetId,
            $date
         );

        try {
            $snapshot->getReference()->set($document);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            throw $e;
        }
    }

    /**
     * @throws \Kreait\Firebase\Exception\DatabaseException
     */
    public function removeTweetFromTrends(
        string $tweetId,
        DateTimeInterface $createdAt
    ): void {
        $snapshot = $this->getTweetSnapshot(
            $tweetId,
            $createdAt
        );

        try {
            $snapshot->getReference()->set(null);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());

            throw $e;
        }
    }
}
