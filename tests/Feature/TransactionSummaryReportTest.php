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

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_get_a_summary_of_transactions(): void
    {
        $testResponse = $this->getJson(route('reports.transactions.summary'));

        $testResponse->assertOk()
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

        $responseData = $testResponse->json('data');

        $this->assertEquals(4, $responseData['total_transactions']);
        $this->assertEquals(425.00, $responseData['total_amount'], ''); // Using delta for float comparison
        $this->assertEquals(2, $responseData['successful_transactions']);
        $this->assertEquals(1, $responseData['failed_transactions']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_get_a_summary_of_transactions_with_date_filters(): void
    {
        $testResponse = $this->getJson(route('reports.transactions.summary', [
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
        ]));

        $testResponse->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $responseData = $testResponse->json('data');

        $this->assertEquals(3, $responseData['total_transactions']);
        $this->assertEquals(350.00, $responseData['total_amount'], ''); // Using delta for float comparison
        $this->assertEquals(2, $responseData['successful_transactions']);
        $this->assertEquals(0, $responseData['failed_transactions']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_validation_error_for_invalid_dates_in_summary_report(): void
    {
        $testResponse = $this->getJson(route('reports.transactions.summary', [
            'start_date' => '2025-01-31',
            'end_date' => '2025-01-01',
        ]));

        $testResponse->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }
}
