<?php

namespace App\Domain\Payments;

use App\Support\Money;

final class ProviderEvent {
    public function __construct(
        public string $provider,
        public string $status,
        public Money $amount,
        public string $reference,
        public array $meta = []
    ){}
}
