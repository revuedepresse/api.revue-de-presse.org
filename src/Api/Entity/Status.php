<?php
declare(strict_types=1);

namespace App\Api\Entity;

use App\Status\Entity\StatusTrait;
use DateTimeInterface;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Api\Repository\StatusRepository")
 * @ORM\Table(
 *      name="weaving_status",
 *      uniqueConstraints={
 *          @ORM\UniqueConstraint(
 *              name="unique_hash", columns={"ust_hash"}),
 *      },
 *      options={"collate":"utf8mb4_general_ci", "charset":"utf8mb4"},
 *      indexes={
 *          @ORM\Index(name="hash", columns={"ust_hash"}),
 *          @ORM\Index(name="screen_name", columns={"ust_full_name"}),
 *          @ORM\Index(name="status_id", columns={"ust_status_id"}),
 *          @ORM\Index(name="indexed", columns={"ust_indexed"}),
 *          @ORM\Index(name="ust_created_at", columns={"ust_created_at"}),
 *          @ORM\Index(name="idx_published", columns={"is_published"})
 *      }
 * )
 */
class Status implements StatusInterface
{
    use StatusTrait;

    /**
     * @var integer
     *
     * @ORM\Column(name="ust_id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected ?int $id = null;

    /**
     * @var string
     *
     * @ORM\Column(name="ust_hash", type="string", length=40, nullable=true)
     */
    protected ?string $hash;

    /**
     * @ORM\Column(name="ust_full_name", type="string", length=32)
     */
    protected string $screenName;

    /**
     * @ORM\Column(name="ust_name", type="text")
     */
    protected string $name;

    /**
     * @ORM\Column(name="ust_text", type="text")
     */
    protected string $text;

    /**
     * @ORM\Column(name="ust_avatar", type="string", length=255)
     */
    protected string $userAvatar;

    /**
     * @ORM\Column(name="ust_access_token", type="string", length=255)
     */
    protected string $identifier;

    /**
     * @ORM\Column(name="ust_status_id", type="string", length=255, nullable=true)
     */
    protected ?string $statusId;

    /**
     * @ORM\Column(name="ust_api_document", type="text", nullable=true)
     */
    protected ?string $apiDocument;

    /**
     * @ORM\Column(name="ust_starred", type="boolean", options={"default": false})
     */
    protected bool $starred = false;

    /**
     * @ORM\Column(name="ust_indexed", type="boolean", options={"default": false})
     */
    protected bool $indexed;

    /**
     * @ORM\Column(name="is_published", type="boolean", options={"default": false})
     */
    protected bool $isPublished = false;

    public function markAsPublished(): self
    {
        $this->isPublished = true;

        return $this;
    }

    /**
     * @var DateTimeInterface|null
     *
     * @ORM\Column(name="ust_created_at", type="datetime")
     */
    protected DateTimeInterface $createdAt;

    /**
     * @ORM\Column(name="ust_updated_at", type="datetime", nullable=true)
     */
    protected ?DateTimeInterface $updatedAt;

    /**
     * @ORM\ManyToMany(
     *     targetEntity="Aggregate",
     *     inversedBy="userStreams",
     *     cascade={"persist"}
     * )
     * @ORM\JoinTable(name="weaving_status_aggregate",
     *      joinColumns={
     *          @ORM\JoinColumn(
     *              name="status_id",
     *              referencedColumnName="ust_id"
     *          )
     *      },
     *      inverseJoinColumns={
     *          @ORM\JoinColumn(
     *              name="aggregate_id",
     *              referencedColumnName="id"
     *          )
     *      }
     * )
     */
    protected Collection $aggregates;

    /**
     * @ORM\OneToMany(targetEntity="App\Popularity\Entity\StatusPopularity", mappedBy="status")
     */
    private Collection $popularity;

    /**
     * @return Collection
     */
    public function getPopularity(): Collection
    {
        return $this->popularity;
    }
}
