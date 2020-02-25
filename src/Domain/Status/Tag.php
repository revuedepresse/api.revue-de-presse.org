<?php
declare(strict_types=1);

namespace App\Domain\Status;

use App\Api\Entity\Aggregate;

class Tag
{
    /**
     * @var Aggregate
     */
    private Aggregate $tag;

    private function __construct(Aggregate $tag)
    {
        $this->tag = $tag;
    }

    public function fromAggregate(Aggregate $tag): self
    {
        return new self($tag);
    }

    public function name(): string
    {
        return $this->tag->getName();
    }

    public function tag(): Aggregate
    {
        return $this->tag;
    }
}