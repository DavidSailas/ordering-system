<?php

namespace App\Enums;

enum OrderStatus: string
{
    /** Placed but not paid yet: the kitchen and the runners do not see it until the cashier confirms payment. */
    case AwaitingPayment = 'awaiting_payment';
    case InProgress = 'in_progress';
    case ReadyToServe = 'ready_to_serve';
    case Completed = 'completed';
    case Canceled = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingPayment => 'Waiting for payment',
            self::InProgress => 'In Progress',
            self::ReadyToServe => 'Ready to Serve',
            self::Completed => 'Completed',
            self::Canceled => 'Canceled',
        };
    }

    /**
     * The status an order moves to when the cashier advances it.
     */
    public function next(): ?self
    {
        return match ($this) {
            self::InProgress => self::ReadyToServe,
            self::ReadyToServe => self::Completed,
            default => null,
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Canceled], true);
    }

    public function canTransitionTo(self $status): bool
    {
        if ($this->isFinal()) {
            return false;
        }

        return $status === self::Canceled || $status === $this->next();
    }

    /**
     * Statuses that still occupy a table.
     *
     * @return array<int, self>
     */
    public static function active(): array
    {
        return [self::InProgress, self::ReadyToServe];
    }
}
