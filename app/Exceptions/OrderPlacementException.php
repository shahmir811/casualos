<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by OrderPlacementService when an order cannot be placed for a
 * business reason (as opposed to a bug).
 *
 * The service deliberately does NOT know how to respond to the caller — the
 * public Blade order form redirects back with a flash flag, while the mobile
 * app API returns a JSON error body. Each caller catches this and maps
 * ->reason() onto its own response format. Keep the reason constants stable:
 * the React Native app switches on them.
 */
class OrderPlacementException extends RuntimeException
{
    public const CATALOGUE_CLOSED  = 'catalogue_closed';
    public const NO_QUANTITY       = 'no_quantity';
    public const CUSTOMER_NOT_FOUND = 'customer_not_found';
    public const DUPLICATE_ORDER    = 'duplicate_order';

    protected string $reason;

    public function __construct(string $reason, string $message)
    {
        parent::__construct($message);

        $this->reason = $reason;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public static function catalogueClosed(): self
    {
        return new self(
            self::CATALOGUE_CLOSED,
            'This catalogue is closed and is no longer accepting orders.'
        );
    }

    public static function noQuantity(): self
    {
        return new self(
            self::NO_QUANTITY,
            'Please enter at least one piece quantity to place an order.'
        );
    }

    public static function customerNotFound(string $email): self
    {
        return new self(
            self::CUSTOMER_NOT_FOUND,
            "No customer is registered with the email address {$email}."
        );
    }

    public static function duplicateOrder(): self
    {
        return new self(
            self::DUPLICATE_ORDER,
            'An order has already been placed on this catalogue.'
        );
    }
}
