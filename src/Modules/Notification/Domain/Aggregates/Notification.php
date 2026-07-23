<?php

declare(strict_types=1);

namespace App\Modules\Notification\Domain\Aggregates;

use DateTimeImmutable;
use LogicException;

/**
 * A simple record, not a rich aggregate — its only real invariant is "read
 * once, stays read". No domain events: a notification's own creation is a
 * side effect of something else (a bid outbid, an auction won), never the
 * trigger for further domain behaviour itself.
 */
final class Notification
{
    /**
     * @param  array<string, mixed>  $data
     */
    private function __construct(
        private ?int $id,
        private readonly int $userId,
        private readonly string $type,
        private readonly array $data,
        private readonly DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $readAt,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function create(int $userId, string $type, array $data): self
    {
        return new self(
            id: null,
            userId: $userId,
            type: $type,
            data: $data,
            createdAt: new DateTimeImmutable(),
            readAt: null,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function reconstitute(
        int $id,
        int $userId,
        string $type,
        array $data,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $readAt,
    ): self {
        return new self($id, $userId, $type, $data, $createdAt, $readAt);
    }

    public function assignId(int $id): void
    {
        if ($this->id !== null) {
            throw new LogicException('Notification id is already assigned.');
        }

        $this->id = $id;
    }

    public function markAsRead(): void
    {
        $this->readAt ??= new DateTimeImmutable();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function type(): string
    {
        return $this->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function readAt(): ?DateTimeImmutable
    {
        return $this->readAt;
    }

    public function isRead(): bool
    {
        return $this->readAt !== null;
    }
}
