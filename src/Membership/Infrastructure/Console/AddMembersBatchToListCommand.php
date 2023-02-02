<?php

namespace App\Membership\Infrastructure\Console;

use App\Membership\Domain\Model\MemberInterface;
use App\Membership\Domain\Repository\EditListMembersInterface;
use App\Membership\Domain\Repository\NetworkRepositoryInterface;
use App\Membership\Infrastructure\DependencyInjection\MemberRepositoryTrait;
use App\Membership\Infrastructure\Entity\MemberInList;
use App\Membership\Infrastructure\Repository\EditListMembers;
use App\Twitter\Domain\Http\Client\ListAwareHttpClientInterface;
use App\Twitter\Domain\Http\Client\MembersBatchAwareHttpClientInterface;
use App\Twitter\Infrastructure\Console\AbstractCommand;
use App\Twitter\Infrastructure\DependencyInjection\Curation\Events\PublishersListCollectedEventRepositoryTrait;
use App\Twitter\Infrastructure\DependencyInjection\Http\HttpClientTrait;
use App\Twitter\Infrastructure\DependencyInjection\Http\TweetAwareHttpClientTrait;
use App\Twitter\Infrastructure\DependencyInjection\LoggerTrait;
use App\Twitter\Infrastructure\DependencyInjection\Publication\PublishersListRepositoryTrait;
use App\Twitter\Infrastructure\Http\Resource\PublishersList;
use App\Twitter\Infrastructure\Http\Selector\ListsBatchSelector;
use LogicException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AddMembersBatchToListCommand extends AbstractCommand
{
    use LoggerTrait;
    use HttpClientTrait;
    use MemberRepositoryTrait;
    use PublishersListRepositoryTrait;
    use TweetAwareHttpClientTrait;

    public const COMMAND_NAME = 'app:add-members-batch-to-list';

    public const OPTION_LIST_NAME = 'list';

    public const OPTION_PUBLISHERS_LIST_NAME = 'list-name';

    public const OPTION_SAVE_MEMBER_NETWORK = 'save-member-network';

    public const OPTION_MEMBER_LIST = 'member-ids-list';

    public const ARGUMENT_SCREEN_NAME = 'screen-name';

    private EditListMembersInterface $listRepository;

    private ListAwareHttpClientInterface $listAwareHttpClient;

    private MembersBatchAwareHttpClientInterface $membersBatchHttpClient;

    private NetworkRepositoryInterface $networkRepository;

    public function __construct(
        string                               $name,
        EditListMembers                      $ListSubscriptionRepository,
        NetworkRepositoryInterface           $networkRepository,
        MembersBatchAwareHttpClientInterface $membersListAccessor,
        ListAwareHttpClientInterface         $ownershipAccessor
    ) {
        $this->listRepository = $ListSubscriptionRepository;
        $this->networkRepository = $networkRepository;

        $this->listAwareHttpClient = $ownershipAccessor;
        $this->membersBatchHttpClient = $membersListAccessor;

        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setDescription('Add a member list to a publisher list.')
            ->addArgument(
                self::ARGUMENT_SCREEN_NAME,
                InputArgument::REQUIRED,
                'The screen name of a member owning lists'
            )
            ->addOption(
                self::OPTION_MEMBER_LIST,
                null,
                InputOption::VALUE_OPTIONAL,
                'A comma-separated member ids list '
            )->addOption(
                self::OPTION_LIST_NAME,
                null,
                InputOption::VALUE_OPTIONAL,
                'The optional name of a list containing members to be added to a Twitter list'
            )
            ->addOption(
                self::OPTION_PUBLISHERS_LIST_NAME,
                null,
                InputOption::VALUE_REQUIRED,
                'The name of a Twitter list'
            )
            ->addOption(
                self::OPTION_SAVE_MEMBER_NETWORK,
                null,
                InputOption::VALUE_NONE,
                'Synchronize subscription network beforehand.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        if ($this->providedWithInvalidOptions()) {
            $errorMessage = 'No list name, nor screen name has been provided.';
            $this->logger->critical($errorMessage);
            $this->output->writeln($errorMessage);

            return self::FAILURE;
        }

        try {
            $memberUserName = $this->input->getArgument(self::ARGUMENT_SCREEN_NAME);

            if (
                $this->input->hasOption(self::OPTION_SAVE_MEMBER_NETWORK) &&
                $this->input->getOption(self::OPTION_SAVE_MEMBER_NETWORK)
            ) {
                $this->networkRepository->saveNetwork([$memberUserName]);
            }

            $this->addMembersToList($this->findListToWhichMembersShouldBeAddedTo($memberUserName));
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
            $this->output->writeln($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function findListToWhichMembersShouldBeAddedTo($memberUserName): PublishersList
    {
        $ownershipsLists = $this->listAwareHttpClient->getMemberOwnerships(
            new ListsBatchSelector($memberUserName)
        );

        $publishersListName = $this->input->getOption(self::OPTION_PUBLISHERS_LIST_NAME);

        $filteredLists = [];

        while (
            empty($filteredLists) &&
            $ownershipsLists->nextPage() !== -1
        ) {
            $filteredLists = array_filter(
                $ownershipsLists->toArray(),
                static function (PublishersList $list) use ($publishersListName) {
                    return $list->name() === $publishersListName;
                }
            );

            if (count($filteredLists) === 0 && $ownershipsLists->nextPage() === 0) {
                $this->logger->error(
                    sprintf(
                        'Could not find list %s among {%s}.',
                        $publishersListName,
                        implode(', ', array_map(fn (PublishersList $p) => $p->name(), $ownershipsLists->toArray()))
                    )
                );

                break;
            }

            $ownershipsLists = $this->listAwareHttpClient->getMemberOwnerships(
                new ListsBatchSelector($memberUserName, $ownershipsLists->nextPage())
            );
        }

        if (count($filteredLists) !== 1) {
            throw new LogicException('There should be exactly one remaining list.');
        }

        return array_pop($filteredLists);
    }

    /**
     * @throws \Doctrine\ORM\Exception\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function addMembersToList(PublishersList $targetList): void {
        $memberIds = $this->getListOfMembers();

        if (count($memberIds) <= 100) {
            $this->membersBatchHttpClient->addUpTo100MembersAtOnceToList($memberIds, $targetList->id());
        }
        else {
            $this->membersBatchHttpClient->addMembersToListSequentially($memberIds, $targetList->id());
        }

        $members = $this->ensureMembersExist($memberIds);

        array_walk(
            $members,
            fn (MemberInterface $member) => $this->publishersListRepository->addMemberToList($member, $targetList)
        );

        $this->output->writeln('All members have been successfully added to the Twitter list.');
    }

    /**
     * @param $memberList
     * @return array
     */
    private function ensureMembersExist($memberList): array
    {
        return array_map(
            function (string $memberIdentifier) {

                if (is_numeric($memberIdentifier)) {
                    $member = $this->memberRepository->findOneBy(['twitterID' => $memberIdentifier]);
                } else {
                    $member = $this->memberRepository->findOneBy(['twitter_username' => $memberIdentifier]);
                }

                if (!($member instanceof MemberInterface)) {
                    $member = $this->tweetAwareHttpClient->ensureMemberHavingNameExists($memberIdentifier);
                }

                return $member;
            },
            $memberList
        );
    }

    private function getListOfMembers(): array
    {
        if ($this->input->hasOption(self::OPTION_LIST_NAME) &&
            $this->input->getOption(self::OPTION_LIST_NAME)) {

            try {
                $subscriptions = $this->listRepository->searchByName(
                    $this->input->getOption(self::OPTION_LIST_NAME)
                );
            } catch (\Exception $exception) {
                $this->logger->critical($exception->getMessage());
                $this->output->writeln($exception->getMessage());

                return [];
            }

            $memberList = [];
            array_walk(
                $subscriptions,
                static function (MemberInList $subscription) use (&$memberList) {
                    $memberId = $subscription->memberInList->twitterId();
                    $memberList[] = $memberId;
                }
            );

            return $memberList;
        }

        return explode(',', $this->input->getOption(self::OPTION_MEMBER_LIST));
    }

    /**
     * @return bool
     */
    private function providedWithInvalidOptions(): bool
    {
        return (!$this->input->hasArgument(self::ARGUMENT_SCREEN_NAME) &&
                !$this->input->hasOption(self::OPTION_LIST_NAME)) ||
            (!$this->input->getOption(self::OPTION_LIST_NAME) &&
                !$this->input->getArgument(self::ARGUMENT_SCREEN_NAME));
    }
}
