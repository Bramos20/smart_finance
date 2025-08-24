<?php

namespace App\Domain\Payments;

use Illuminate\Support\Str;

class ProviderFactory {
    public function for(string $provider): PaymentProvider {
        $class = __NAMESPACE__ . '\\' . Str::studly($provider) . 'Provider';
        if (!class_exists($class)) {
            throw new \InvalidArgumentException("Provider {$provider} not supported");
        }
        return app($class);
    }
}