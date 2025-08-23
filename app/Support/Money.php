<?php

namespace App\Support;
final class Money {
    public function __construct(public string $currency, public string $amount)
    {
        
    }
}