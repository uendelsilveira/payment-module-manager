<?php

namespace UendelSilveira\PaymentModuleManager\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use UendelSilveira\PaymentModuleManager\Models\Transaction;
use UendelSilveira\PaymentModuleManager\Tests\TestCase;

class TransactionSummaryReportTest extends TestCase
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
    public function it_can_get_a_summary_of_transactions(): void
    {
        $response = $this->getJson(route('reports.transactions.summary'));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'total_transactions',
                    'total_amount',
                    'successful_transactions',
                    'failed_transactions',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);

        $responseData = $response->json('data');

        $this->assertEquals(4, $responseData['total_transactions']);
        $this->assertEquals(425.00, $responseData['total_amount'], '', 0.001); // Using delta for float comparison
        $this->assertEquals(2, $responseData['successful_transactions']);
        $this->assertEquals(1, $responseData['failed_transactions']);
    }

    /** @test */
    public function it_can_get_a_summary_of_transactions_with_date_filters(): void
    {
        $response = $this->getJson(route('reports.transactions.summary', [
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
        ]));

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $responseData = $response->json('data');

        $this->assertEquals(3, $responseData['total_transactions']);
        $this->assertEquals(350.00, $responseData['total_amount'], '', 0.001); // Using delta for float comparison
        $this->assertEquals(2, $responseData['successful_transactions']);
        $this->assertEquals(0, $responseData['failed_transactions']);
    }

    /** @test */
    public function it_returns_validation_error_for_invalid_dates_in_summary_report(): void
    {
        $response = $this->getJson(route('reports.transactions.summary', [
            'start_date' => '2025-01-31',
            'end_date' => '2025-01-01',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }
}
