<?php

namespace Tests\Unit;

use App\Services\BalanceCalculator;
use Tests\TestCase;

class BalanceCalculatorTest extends TestCase
{
    public function test_settle_up_simple(): void
    {
        $balances = [
            1 => 100.0,
            2 => -60.0,
            3 => -40.0,
        ];

        $txns = BalanceCalculator::settleUp($balances);

        $this->assertCount(2, $txns);

        // Greedy matching:
        // Debtor 2 (-60) owes 60. Creditor 1 (+100) is owed 100.
        // Payment 1: User 2 pays User 1 ₹60.
        // Remaining debtor: User 3 (-40). Remaining creditor: User 1 (+40).
        // Payment 2: User 3 pays User 1 ₹40.
        
        $this->assertEquals(2, $txns[0]['from']);
        $this->assertEquals(1, $txns[0]['to']);
        $this->assertEquals(60.0, $txns[0]['amount']);

        $this->assertEquals(3, $txns[1]['from']);
        $this->assertEquals(1, $txns[1]['to']);
        $this->assertEquals(40.0, $txns[1]['amount']);
    }

    public function test_settle_up_complex(): void
    {
        $balances = [
            1 => 80.0,
            2 => 20.0,
            3 => -50.0,
            4 => -50.0,
        ];

        $txns = BalanceCalculator::settleUp($balances);

        // Debtors: 3 (50), 4 (50)
        // Creditors: 1 (80), 2 (20)
        // 1st pair: Debtor 3 (50) and Creditor 1 (80).
        // Transaction: 3 pays 1, amount = 50.
        // Creditor 1 remaining = 30.
        
        // 2nd pair: Debtor 4 (50) and Creditor 1 (30).
        // Transaction: 4 pays 1, amount = 30.
        // Debtor 4 remaining = 20.
        
        // 3rd pair: Debtor 4 (20) and Creditor 2 (20).
        // Transaction: 4 pays 2, amount = 20.
        
        $this->assertCount(3, $txns);

        $this->assertEquals(3, $txns[0]['from']);
        $this->assertEquals(1, $txns[0]['to']);
        $this->assertEquals(50.0, $txns[0]['amount']);

        $this->assertEquals(4, $txns[1]['from']);
        $this->assertEquals(1, $txns[1]['to']);
        $this->assertEquals(30.0, $txns[1]['amount']);

        $this->assertEquals(4, $txns[2]['from']);
        $this->assertEquals(2, $txns[2]['to']);
        $this->assertEquals(20.0, $txns[2]['amount']);
    }
}
