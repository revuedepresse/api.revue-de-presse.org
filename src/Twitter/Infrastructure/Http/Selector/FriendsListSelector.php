<?php
declare (strict_types=1);

namespace App\Twitter\Infrastructure\Http\Selector;

use Ramsey\Uuid\UuidInterface;

class FriendsListSelector implements ListSelector
{
    /**
     * @var UuidInterface
     */
    private UuidInterface $correlationId;
    private string $screenName;
    private string $cursor;

    public function __construct(
        UuidInterface $correlationId,
        string $screenName,
        string $cursor = '-1'
    ) {
        $this->correlationId = $correlationId;
        $this->screenName = $screenName;
        $this->cursor = $cursor;
    }

    public function correlationId(): UuidInterface
    {
        return $this->correlationId;
    }

    public function screenName(): string
    {
        return $this->screenName;
    }

    public function cursor(): string
    {
        return $this->cursor;
    }

    public function isDefaultCursor(): bool
    {
        return $this->cursor === self::DEFAULT_CURSOR;
    }
}
