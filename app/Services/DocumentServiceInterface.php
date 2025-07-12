<?php

namespace App\Services;

use App\Models\Invoice;

interface DocumentServiceInterface
{
    public function create(array $data);
    public function update(array $data, Invoice $invoice);

    public function cancel(Invoice $invoice): Invoice;
}
