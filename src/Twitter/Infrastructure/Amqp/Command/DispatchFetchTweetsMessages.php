<?php
declare(strict_types=1);

namespace App\Twitter\Infrastructure\Amqp\Command;

use App\Twitter\Domain\Curation\CurationStrategyInterface;
use App\Twitter\Infrastructure\Amqp\Exception\SkippableOperationException;
use App\Twitter\Infrastructure\Amqp\Exception\UnexpectedOwnershipException;
use App\Twitter\Infrastructure\Api\Entity\Token;
use App\Twitter\Infrastructure\Api\Exception\InvalidSerializedTokenException;
use App\Twitter\Infrastructure\DependencyInjection\OwnershipAccessorTrait;
use App\Twitter\Infrastructure\DependencyInjection\Publication\PublicationMessageDispatcherTrait;
use App\Twitter\Infrastructure\DependencyInjection\TranslatorTrait;
use App\Twitter\Infrastructure\Exception\OverCapacityException;
use App\Twitter\Infrastructure\InputConverter\InputToCollectionStrategy;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DispatchFetchTweetsMessages extends AggregateAwareCommand
{
    private const ARGUMENT_SCREEN_NAME                  = CurationStrategyInterface::RULE_SCREEN_NAME;

    private const OPTION_BEFORE                         = CurationStrategyInterface::RULE_BEFORE;
    private const OPTION_CURSOR                         = CurationStrategyInterface::RULE_CURSOR;
    private const OPTION_FILTER_BY_TWEET_OWNER_USERNAME = CurationStrategyInterface::RULE_FILTER_BY_TWEET_OWNER_USERNAME;
    private const OPTION_IGNORE_WHISPERS                = CurationStrategyInterface::RULE_IGNORE_WHISPERS;
    private const OPTION_INCLUDE_OWNER                  = CurationStrategyInterface::RULE_INCLUDE_OWNER;
    private const OPTION_LIST                           = CurationStrategyInterface::RULE_LIST;
    private const OPTION_LISTS                          = CurationStrategyInterface::RULE_LISTS;

    private const OPTION_OAUTH_TOKEN                    = 'oauth_token';
    private const OPTION_OAUTH_SECRET                   = 'oauth_secret';

    use OwnershipAccessorTrait;
    use PublicationMessageDispatcherTrait;
    use TranslatorTrait;

    private CurationStrategyInterface $collectionStrategy;

    public function configure()
    {
        $this->setName('app:dispatch-messages-to-fetch-member-tweets')
            ->setDescription('Dispatch AMQP messages to fetch member tweets.')
            ->addOption(
                self::OPTION_OAUTH_TOKEN,
                null,
                InputOption::VALUE_OPTIONAL,
                'A token is required'
            )->addOption(
                self::OPTION_OAUTH_SECRET,
                null,
                InputOption::VALUE_OPTIONAL,
                'A secret is required'
            )->addOption(
                self::OPTION_LIST,
                null,
                InputOption::VALUE_OPTIONAL,
                'Single list to which production is restricted to'
            )
            ->addOption(
                self::OPTION_LISTS,
                'l',
                InputOption::VALUE_OPTIONAL,
                'List collection to which publication of messages is restricted to'
            )
            ->addOption(
                self::OPTION_CURSOR,
                'c',
                InputOption::VALUE_OPTIONAL,
                'Cursor from which ownership are to be fetched'
            )->addOption(
                self::OPTION_FILTER_BY_TWEET_OWNER_USERNAME,
                'fo',
                InputOption::VALUE_OPTIONAL,
                'Filter by Twitter member username'
            )->addOption(
                self::OPTION_BEFORE,
                null,
                InputOption::VALUE_OPTIONAL,
                'Date before which statuses should have been created'
            )->addOption(
                self::OPTION_INCLUDE_OWNER,
                null,
                InputOption::VALUE_NONE,
                'Should add owner to the list of accounts to be considered'
            )->addOption(
                self::OPTION_IGNORE_WHISPERS,
                'iw',
                InputOption::VALUE_NONE,
                'Should ignore whispers (publication from members having not published anything for a month)'
            )
            ->addArgument(
                self::ARGUMENT_SCREEN_NAME,
                InputArgument::REQUIRED,
                'A member screen name'
            );
    }

    /**
     * Instance of MemberIdentityProcessorInterface is responsible for dispatching AMQP messages
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input  = $input;
        $this->output = $output;

        try {
            $this->setUpDependencies();
        } catch (SkippableOperationException $exception) {
            $this->output->writeln($exception->getMessage());
        } catch (InvalidSerializedTokenException $exception) {
            $this->logger->info($exception->getMessage());

            return self::FAILURE;
        }

        $returnStatus = self::FAILURE;

        try {
            $this->publicationMessageDispatcher->dispatchPublicationMessages(
                InputToCollectionStrategy::convertInputToCollectionStrategy($input),
                Token::fromArray($this->getTokensFromInputOrFallback()),
                function ($message) {
                    $this->output->writeln($message);
                }
            );

            $returnStatus = self::SUCCESS;
        } catch (UnexpectedOwnershipException|OverCapacityException $exception) {
            $this->logger->error(
                $exception->getMessage(),
                ['exception' => $exception]
            );
        } catch (\Throwable $exception) {
            $this->logger->error(
                $exception->getMessage(),
                ['exception' => $exception]
            );
        }

        return $returnStatus;
    }

    /**
     * @throws InvalidSerializedTokenException
     */
    private function setUpDependencies(): void
    {
        $this->accessor->fromToken(
            Token::fromArray(
                $this->getTokensFromInputOrFallback()
            )
        );
    }
}