<?php

namespace App\Domain\Payments;
use App\Support\Money;


final class ProviderIntent {
    public function __construct(public string $provider, public string $type,
    public string $link){}
}
final class ProviderEvent {
    public function __construct(
        public string $provider,
        public string $status,
        public Money $amount,
        public string $reference,
        public array $meta = []
    ){}
}