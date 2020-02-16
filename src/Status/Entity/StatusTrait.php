<?php
declare(strict_types=1);

namespace App\Status\Entity;

use App\Api\Entity\Aggregate;
use App\Api\Entity\ArchivedStatus;
use App\Api\Entity\StatusInterface;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * @package App\Status\Entity
 */
trait StatusTrait
{
    /**
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param bool $starred
     *
     * @return $this
     */
    public function setStarred(bool $starred): self
    {
        $this->starred = $starred;

        return $this;
    }

    /**
     * @return bool
     */
    public function isStarred(): bool
    {
        return $this->starred;
    }

    /**
     * @param string $hash
     *
     * @return $this
     */
    public function setHash(string $hash = null): self
    {
        $this->hash = $hash;

        return $this;
    }

    /**
     * @return string
     */
    public function getHash(): string
    {
        return $this->hash;
    }

    /**
     * @param string $screenName
     *
     * @return $this
     */
    public function setScreenName(string $screenName): self
    {
        $this->screenName = $screenName;

        return $this;
    }

    /**
     * @return string
     */
    public function getScreenName(): string
    {
        return $this->screenName;
    }

    /**
     * @param string $name
     *
     * @return self
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $text
     *
     * @return $this
     */
    public function setText($text): self
    {
        $this->text = $text;

        return $this;
    }

    /**
     * @return string
     */
    public function getText(): string
    {
        return $this->text;
    }

    /**
     * @param string $userAvatar
     *
     * @return $this|StatusInterface
     */
    public function setUserAvatar(string $userAvatar): self
    {
        $this->userAvatar = $userAvatar;

        return $this;
    }

    /**
     * @return string
     */
    public function getUserAvatar(): string
    {
        return $this->userAvatar;
    }

    /**
     * @param string $identifier
     *
     * @return $this|StatusInterface
     */
    public function setIdentifier(string $identifier): self
    {
        $this->identifier = $identifier;

        return $this;
    }

    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * @param string $statusId
     *
     * @return $this
     */
    public function setStatusId(string $statusId = null): self
    {
        $this->statusId = $statusId;

        return $this;
    }

    /**
     * @return string
     */
    public function getStatusId(): string
    {
        return $this->statusId;
    }

    /**
     * @param string $apiDocument
     *
     * @return $this
     */
    public function setApiDocument(string $apiDocument): self
    {
        $this->apiDocument = $apiDocument;

        return $this;
    }

    /**
     * @return mixed|string|null
     */
    public function getApiDocument(): string
    {
        return $this->apiDocument;
    }

    /**
     * @param DateTimeInterface $createdAt
     *
     * @return $this
     */
    public function setCreatedAt(DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return DateTimeInterface
     */
    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * @param $updatedAt
     * @return $this
     */
    public function setUpdatedAt(DateTimeInterface $updatedAt = null): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return DateTimeInterface
     */
    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    /**
     * @param $indexed
     * @return $this
     */
    public function setIndexed(bool $indexed): self
    {
        $this->indexed = $indexed;

        return $this;
    }

    /**
     * @return bool
     */
    public function getIndexed(): bool
    {
        return $this->indexed;
    }

    public function __construct()
    {
        $this->aggregates = new ArrayCollection();
    }

    /**
     * @return ArrayCollection
     */
    public function getAggregates(): Collection
    {
        return $this->aggregates;
    }

    /**
     * @param Aggregate $aggregate
     *
     * @return $this
     */
    public function removeFrom(Aggregate $aggregate): self
    {
        $this->aggregates->remove($aggregate);

        return $this;
    }

    /**
     * @param Aggregate $aggregate
     *
     * @return ArrayCollection
     */
    public function addToAggregates(Aggregate $aggregate): ArrayCollection {
        $this->aggregates->add($aggregate);

        return $this->aggregates;
    }

}
