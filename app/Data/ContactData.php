<?php

declare(strict_types=1);

namespace App\Data;

final readonly class ContactData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $phoneOriginal,
        public string $phoneCanonical,
    ) {}
}
