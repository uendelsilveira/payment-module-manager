<?php

/*
 By Uendel Silveira
 Developer Web
 IDE: PhpStorm
 Created: 04/11/2025 16:09:38
*/

namespace UendelSilveira\PaymentModuleManager\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use UendelSilveira\PaymentModuleManager\Models\Transaction;
use UendelSilveira\PaymentModuleManager\Tests\TestCase;

class TransactionsByMethodReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Criar algumas transações para os testes
        Transaction::factory()->create([
            'amount' => 100.00,
            'status' => 'approved',
            'metadata' => ['payment_method_id' => 'credit_card'],
            'created_at' => '2025-01-10 10:00:00',
        ]);
        Transaction::factory()->create([
            'amount' => 50.00,
            'status' => 'approved',
            'metadata' => ['payment_method_id' => 'pix'],
            'created_at' => '2025-01-15 11:00:00',
        ]);
        Transaction::factory()->create([
            'amount' => 200.00,
            'status' => 'pending',
            'metadata' => ['payment_method_id' => 'boleto'],
            'created_at' => '2025-01-20 12:00:00',
        ]);
        Transaction::factory()->create([
            'amount' => 75.00,
            'status' => 'failed',
            'metadata' => ['payment_method_id' => 'credit_card'],
            'created_at' => '2025-02-01 13:00:00',
        ]);
    }

    /** @test */
    public function it_can_get_transactions_grouped_by_method(): void
    {
        $response = $this->getJson(route('reports.transactions.methods'));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    [
                        'payment_method_id',
                        'total_transactions',
                        'total_amount',
                    ],
                ],
            ])
            ->assertJson(['success' => true])
            ->assertJsonCount(3, 'data')
            ->assertJsonFragment([
                'payment_method_id' => 'credit_card',
                'total_transactions' => 2,
                'total_amount' => 175.00,
            ])
            ->assertJsonFragment([
                'payment_method_id' => 'pix',
                'total_transactions' => 1,
                'total_amount' => 50.00,
            ])
            ->assertJsonFragment([
                'payment_method_id' => 'boleto',
                'total_transactions' => 1,
                'total_amount' => 200.00,
            ]);
    }

    /** @test */
    public function it_can_get_transactions_grouped_by_method_with_date_filters(): void
    {
        $response = $this->getJson(route('reports.transactions.methods', [
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
        ]));

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonFragment([
                'payment_method_id' => 'credit_card',
                'total_transactions' => 1,
                'total_amount' => 100.00,
            ])
            ->assertJsonFragment([
                'payment_method_id' => 'pix',
                'total_transactions' => 1,
                'total_amount' => 50.00,
            ])
            ->assertJsonFragment([
                'payment_method_id' => 'boleto',
                'total_transactions' => 1,
                'total_amount' => 200.00,
            ]);
    }

    /** @test */
    public function it_returns_validation_error_for_invalid_dates_in_methods_report(): void
    {
        $response = $this->getJson(route('reports.transactions.methods', [
            'start_date' => '2025-01-31',
            'end_date' => '2025-01-01',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }
}
