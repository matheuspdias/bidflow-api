<?php

declare(strict_types=1);

namespace App\Shared\Domain\Contracts;

/**
 * The cross-module view of "who is the current user" — implemented by an
 * adapter in Modules\User (see UserIdentityAdapter, Fase 2) so that other
 * modules (e.g. Auction, checking who is placing a bid) never depend on
 * Modules\User internals directly.
 */
interface UserIdentity
{
    public function id(): int;

    public function isBlocked(): bool;
}
