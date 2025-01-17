<?php
declare(strict_types=1);

namespace App\Membership\Domain\Entity\Legacy;

use App\Twitter\Infrastructure\Http\Entity\Token;
use App\Membership\Domain\Entity\MemberInterface;
use App\Membership\Domain\Model\Member as MemberModel;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Selectable;
use Doctrine\ORM\Mapping as ORM;
use const JSON_THROW_ON_ERROR;

/**
 *
 * @ORM\Table(
 *      name="weaving_user",
 *      uniqueConstraints={
 *          @ORM\UniqueConstraint(name="unique_twitter_id", columns={"usr_twitter_id"}),
 *      },
 *      indexes={
 *          @ORM\Index(name="membership", columns={
 *              "usr_id",
 *              "usr_twitter_id",
 *              "usr_twitter_username",
 *              "not_found",
 *              "protected",
 *              "suspended",
 *              "total_subscribees",
 *              "total_subscriptions"
 *          })
 *      }
 * )
 * @ORM\Entity(repositoryClass="App\Twitter\Infrastructure\Repository\Membership\MemberRepository")
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="usr_position_in_hierarchy", type="integer")
 * @ORM\DiscriminatorMap({"1" = "Member"})
 * @package App\Membership\Domain\Entity
 */
class Member extends MemberModel
{
    /**
     * @ORM\Column(name="usr_api_key", type="string", nullable=true)
     */
    public ?string $apiKey = null;

    /**
     * @ORM\Column(name="max_status_id", type="string", length=255, nullable=true)
     */
    public ?string $maxStatusId;

    /**
     * @ORM\Column(name="min_status_id", type="string", length=255, nullable=true)
     */
    public ?string $minStatusId;

    public function getMinStatusId(): int
    {
        return (int) $this->minStatusId;
    }

    /**
     * @ORM\Column(name="max_like_id", type="string", length=255, nullable=true)
     */
    public ?string $maxLikeId;

    /**
     * @ORM\Column(name="min_like_id", type="string", length=255, nullable=true)
     */
    public ?string $minLikeId;

    /**
     * @var integer
     *
     * @ORM\Column(name="total_statuses", type="integer", options={"default": 0})
     */
    public int $totalStatuses = 0;

    /**
     * @var integer
     *
     * @ORM\Column(name="total_likes", type="integer", options={"default": 0})
     */
    public $totalLikes = 0;

    /**
     * @ORM\Column(name="description", type="text", nullable=true)
     */
    public ?string $description = '';

    /**
     * @ORM\Column(name="url", type="text", nullable=true)
     */
    public ?string $url = null;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="last_status_publication_date", type="datetime", nullable=true)
     */
    public ?DateTime $lastStatusPublicationDate = null;

    /**
     * @var integer
     * @ORM\Column(name="total_subscribees", type="integer", options={"default": 0})
     */
    public $totalSubscribees = 0;

    /**
     * @var integer
     * @ORM\Column(name="total_subscriptions", type="integer", options={"default": 0})
     */
    public $totalSubscriptions = 0;

    /**
     * @var integer
     *
     * @ORM\Id
     * @ORM\Column(name="usr_id", type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected ?int $id;

    /**
     * @var string
     *
     * @ORM\Column(name="usr_twitter_id", type="string", length=255, nullable=true)
     */
    protected ?string $twitterID;

    /**
     * @var string
     *
     * @ORM\Column(name="usr_twitter_username", type="string", nullable=true)
     */
    protected ?string $twitter_username;

    /**
     * @var string
     *
     * @ORM\Column(name="usr_avatar", type="integer", nullable=true)
     */
    protected ?string $avatar;

    /**
     * @var string
     *
     * @ORM\Column(name="usr_full_name", type="string", length=255, nullable=true)
     */
    protected ?string $fullName;

    /**
     * @var boolean
     *
     * @ORM\Column(name="usr_status", type="boolean", nullable=false)
     */
    protected bool $enabled;

    /**
     * @var string
     *
     * @ORM\Column(name="usr_user_name", type="string", length=255, nullable=true)
     */
    protected ?string $username;

    /**
     * @var string
     *
     * @ORM\Column(name="usr_username_canonical", type="string", length=255, nullable=true)
     */
    protected ?string $usernameCanonical;

    /**
     * @var string
     *
     * @ORM\Column(name="usr_email", type="string", length=255)
     */
    protected string $email;

    /**
     * @var string
     * @ORM\Column(name="usr_email_canonical", type="string", length=255, nullable=true)
     */
    protected ?string $emailCanonical;

    /**
     * @var int
     */
    protected int $positionInHierarchy;

    /**
     * @ORM\ManyToMany(
     *      targetEntity="\App\Twitter\Infrastructure\Http\Entity\Token",
     *      inversedBy="users",
     *      fetch="EAGER"
     * )
     * @ORM\JoinTable(name="weaving_user_token",
     *      joinColumns={
     *          @ORM\JoinColumn(name="user_id", referencedColumnName="usr_id")
     *      },
     *      inverseJoinColumns={
     *          @ORM\JoinColumn(name="token_id", referencedColumnName="id")
     *      }
     * )
     */
    protected Selectable $tokens;

    /**
     * Protected status according to Twitter
     *
     * @var boolean
     * @ORM\Column(name="protected", type="boolean", options={"default": false}, nullable=true)
     */
    protected ?bool $protected = false;

    /**
     * Suspended status according to Twitter
     *
     * @ORM\Column(name="suspended", type="boolean", options={"default": false})
     */
    protected bool $suspended = false;

    /**
     * NotFound status according to Twitter
     *
     * @ORM\Column(name="not_found", type="boolean", options={"default": false})
     */
    protected bool $notFound = false;

    private bool $locked;

    /**
     * @ORM\OneToMany(
     *      targetEntity="\App\Twitter\Domain\Curation\Entity\PublicationBatchCollectedEvent",
     *      mappedBy="member"
     * )
     */
    protected Collection $publicationBatchCollectedEvents;

    public function __construct()
    {
        parent::__construct();

        $this->tokens = new ArrayCollection();
    }

    /**
     * Add tokens
     *
     * @param Token $tokens
     *
     * @return MemberInterface
     */
    public function addToken(Token $tokens)
    {
        $this->tokens[] = $tokens;

        return $this;
    }

    /**
     * @return false|string
     */
    public function encodeAsJson(): string
    {
        $jsonEncodedMember = json_encode(
            [
                'id'          => $this->id,
                'username'    => $this->twitter_username,
                'description' => $this->description,
                'url'         => $this->url,
            ],
            JSON_THROW_ON_ERROR
        );

        if (!$jsonEncodedMember) {
            return json_encode(
                [
                    'id'          => 0,
                    'username'    => '',
                    'description' => '',
                    'url'         => '',
                ],
                JSON_THROW_ON_ERROR
            );
        }

        return $jsonEncodedMember;
    }

    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    public function getAvatar(): string
    {
        return $this->avatar;
    }

    public function setAvatar(string $avatar): self
    {
        $this->avatar = $avatar;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getEnabled(): bool
    {
        return $this->enabled;
    }

    public function getExpired()
    {
        return $this->expired;
    }

    public function getFullName(): string
    {
        if ($this->fullName === null) {
            return '';
        }

        return $this->fullName;
    }

    /**
     * @deprecated in favor of ->setName
     *
     */
    public function setFullName(string $fullName): MemberInterface
    {
        return $this->setName($fullName);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLocked(): bool
    {
        return $this->locked;
    }

    public function setLocked($locked): self
    {
        $this->locked = $locked;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getTokens(): Selectable
    {
        return $this->tokens;
    }

    public function getTwitterID(): ?string
    {
        return $this->twitterID;
    }

    public function setTwitterID(string $twitterId): MemberInterface
    {
        $this->twitterID = $twitterId;

        return $this;
    }

    public function getTwitterUsername(): ?string
    {
        return $this->twitter_username;
    }

    /**
     * @deprecated in favor of ->setScreenName
     */
    public function setTwitterUsername(string $twitterUsername): MemberInterface
    {
        $this->twitter_username = $twitterUsername;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    public function hasBeenDeclaredAsNotFound(): bool
    {
        return $this->notFound;
    }

    public function hasNotBeenDeclaredAsNotFound(): bool
    {
        return !$this->hasBeenDeclaredAsNotFound();
    }

    /**
     * @throws \Exception
     */
    public function isAWhisperer(): bool
    {
        $oneMonthAgo = new DateTime('now', new \DateTimeZone('UTC'));
        $oneMonthAgo->modify('-1 month');

        return !is_null($this->lastStatusPublicationDate)
            && ($this->lastStatusPublicationDate < $oneMonthAgo);
    }

    /**
     * @deprecated in favor of ->hasBeenDeclaredAsNotFound
     */
    public function isNotFound(): bool
    {
        return $this->hasBeenDeclaredAsNotFound();
    }

    public function setNotFound(bool $notFound): MemberInterface
    {
        $this->notFound = $notFound;

        return $this;
    }

    public function isNotProtected(): bool
    {
        return !$this->isProtected();
    }

    public function isNotSuspended(): bool
    {
        return !$this->isSuspended();
    }

    public function isProtected(): bool
    {
        return $this->protected;
    }

    public function setProtected(bool $protected): self
    {
        $this->protected = $protected;

        return $this;
    }

    public function isSuspended(): bool
    {
        return $this->suspended;
    }

    public function setSuspended(bool $suspended): self
    {
        $this->suspended = $suspended;

        return $this;
    }

    public function removeToken(Token $tokens)
    {
        $this->tokens->removeElement($tokens);
    }

    public function setConfirmationToken($confirmationToken)
    {
        $this->confirmationToken = $confirmationToken;

        return $this;
    }

    public function setExpired(bool $expired): self
    {
        $this->expired = $expired;

        return $this;
    }

    public function setName(string $name): MemberInterface
    {
        $this->fullName = $name;

        return $this;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function setScreenName(string $screenName): MemberInterface
    {
        $this->twitter_username = $screenName;

        return $this;
    }

    public function setTotalStatus($totalStatus): self
    {
        $this->totalStatuses = $totalStatus;

        return $this;
    }

    public function totalStatus(): int
    {
        return $this->totalStatuses;
    }
}
