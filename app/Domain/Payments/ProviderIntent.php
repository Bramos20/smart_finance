<?php

namespace App\Domain\Payments;

final class ProviderIntent
{
    public function __construct(
        public string $provider,
        public string $type,
        public string $link
    ) {}
}

