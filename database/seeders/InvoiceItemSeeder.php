<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\InvoiceItem;
use App\Models\Invoice;
use App\Models\Product;

class InvoiceItemSeeder extends Seeder
{
    public function run(): void
    {
        $invoices = Invoice::all();
        $products = Product::all();
        foreach ($invoices as $invoice) {
            foreach ($products as $product) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'quantity' => 2,
                    'unit_price' => 100,
                    'discount' => 0,
                    'total' => 200,
                ]);
            }
        }
    }
}
