<?php
declare (strict_types=1);

namespace App\Infrastructure\Twitter\Api\Mutator;

use Abraham\TwitterOAuth\TwitterOAuthException;
use App\Domain\Resource\MemberCollection;
use App\Domain\Resource\MemberIdentity;
use App\Infrastructure\DependencyInjection\Api\ApiAccessorTrait;
use App\Infrastructure\DependencyInjection\LoggerTrait;
use App\Infrastructure\DependencyInjection\Membership\MemberRepositoryTrait;
use App\Infrastructure\DependencyInjection\Subscription\MemberSubscriptionRepositoryTrait;
use App\Membership\Entity\MemberInterface;
use App\Operation\Collection\CollectionInterface;
use App\Twitter\Exception\UnavailableResourceException;

class FriendshipMutator implements FriendshipMutatorInterface
{
    use ApiAccessorTrait;
    use LoggerTrait;
    use MemberRepositoryTrait;
    use MemberSubscriptionRepositoryTrait;

    public function unfollowMembers(
        MemberCollection $memberCollection,
        MemberInterface $subscriber
    ): CollectionInterface {
        return $memberCollection->map(function(MemberIdentity $identity) use ($subscriber) {
            try {
                $this->apiAccessor->contactEndpoint(
                    $this->getDestroyFriendshipEndpointForMemberHavingId($identity->id())
                );

                $this->memberSubscriptionRepository->cancelMemberSubscription(
                    $subscriber,
                    $this->memberRepository->findOneBy([
                        'twitterID' => $identity->id()
                    ])
                );
            } catch (UnavailableResourceException|TwitterOAuthException $e) {
                $this->logger->error($e->getMessage());
            }

            return $identity;
        });
    }

    private function getDestroyFriendshipEndpoint(string $screenName): string
    {
        return strtr(
            $this->apiAccessor->getApiBaseUrl() . '/friendships/destroy.json?screen_name={{ screen_name }}',
            ['{{ screen_name }}' => $screenName]
        );
    }

    private function getDestroyFriendshipEndpointForMemberHavingId(string $id): string
    {
        return strtr(
            $this->apiAccessor->getApiBaseUrl() . '/friendships/destroy.json?user_id={{ user_id }}',
            ['{{ user_id }}' => $id]
        );
    }
}