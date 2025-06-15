<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\InvoiceType;

class InvoiceControllerTest extends TestCase
{
    public function test_resolves_invoice_creation_service_for_sale()
    {
        $payload = [
            'user_id' => 5,
            'invoice_type_id' => 3,
            'invoice_type_code' => 'sale',
            'invoice_number' => 'INV-001',
            'due_date' => '2025-07-15',
            'total_amount' => 1500,
            'status' => 'draft',
            'company_id' => 1,
            'created_by' => 5,
            'items' => [
                [
                    'product_id' => 42,
                    'name' => 'Laptop',
                    'quantity' => 2,
                    'unit_price' => 600,
                    'discount' => 0,
                    'total' => 1200,
                    'company_id' => 1,
                ],
            ],
        ];

        $response = $this->postJson('/api/invoices', $payload);

        $response->assertStatus(201);
        $response->assertJsonFragment(['type' => 'sale']);
    }

    public function test_resolves_invoice_type_code_if_not_provided()
    {
        $invoiceType = InvoiceType::factory()->create(['code' => 'purchase']);

        $payload = [
            'user_id' => 5,
            'invoice_type_id' => $invoiceType->id,
            'invoice_number' => 'INV-002',
            'due_date' => '2025-07-15',
            'total_amount' => 2000,
            'status' => 'draft',
            'company_id' => 1,
            'created_by' => 5,
            'items' => [
                [
                    'product_id' => 42,
                    'name' => 'Monitor',
                    'quantity' => 1,
                    'unit_price' => 2000,
                    'discount' => 0,
                    'total' => 2000,
                    'company_id' => 1,
                ],
            ],
        ];

        $response = $this->postJson('/api/invoices', $payload);

        $response->assertStatus(201);
        $response->assertJsonFragment(['type' => 'purchase']);
    }

    public function test_invoice_creation_with_valid_data()
    {
        $payload = [
            'user_id' => 5,
            'invoice_type_id' => 3,
            'invoice_type_code' => 'sale',
            'invoice_number' => 'INV-001',
            'due_date' => '2025-07-15',
            'total_amount' => 1500,
            'status' => 'draft',
            'company_id' => 1,
            'created_by' => 5,
            'items' => [
                [
                    'product_id' => 42,
                    'name' => 'Laptop',
                    'quantity' => 2,
                    'unit_price' => 600,
                    'discount' => 0,
                    'total' => 1200,
                    'company_id' => 1,
                ],
            ],
        ];

        $response = $this->postJson('/api/invoices', $payload);

        $response->assertStatus(201);
        $response->assertJson([
            'status' => 'success',
            'message' => 'تم إنشاء المستند بنجاح',
        ]);

        $this->assertDatabaseHas('invoices', [
            'invoice_number' => 'INV-001',
            'total_amount' => 1500,
        ]);

        $this->assertDatabaseHas('invoice_items', [
            'product_id' => 42,
            'quantity' => 2,
            'unit_price' => 600,
        ]);
    }
}
