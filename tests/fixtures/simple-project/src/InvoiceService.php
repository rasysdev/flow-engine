<?php

namespace Fixture;

final class InvoiceService
{
    public function __construct(
        private Calculator $calculator = new Calculator(),
    ) {
    }

    public function totalWithTax(int $subtotal, int $tax): int
    {
        return $this->calculator->sum($subtotal, $tax);
    }

    public function doubleTotal(int $subtotal, int $tax): int
    {
        return $this->calculator->multiply($this->totalWithTax($subtotal, $tax), 2);
    }
}
