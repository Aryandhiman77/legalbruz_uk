<?php

namespace App\Support;

final readonly class GeneratedPaymentInvoice
{
    public function __construct(
        public string $number,
        public string $filename,
        public string $contents,
    ) {}
}
