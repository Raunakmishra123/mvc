<?php

namespace Tests\Unit;

use App\Services\SplitCalculator;
use Tests\TestCase;

class SplitCalculatorTest extends TestCase
{
    public function test_equal_split(): void
    {
        $shares = SplitCalculator::compute(100.0, 'equal', [1, 2, 3]);
        
        $this->assertCount(3, $shares);
        
        // 100 / 3 = 33.33 each, with remainder going to the lowest user ID (1)
        // 33.33 + 33.33 + 33.33 = 99.99, so user 1 gets 33.34
        $this->assertEquals(1, $shares[0]['user_id']);
        $this->assertEquals(33.34, $shares[0]['share_amount_inr']);
        
        $this->assertEquals(2, $shares[1]['user_id']);
        $this->assertEquals(33.33, $shares[1]['share_amount_inr']);
        
        $this->assertEquals(3, $shares[2]['user_id']);
        $this->assertEquals(33.33, $shares[2]['share_amount_inr']);
        
        $total = array_sum(array_column($shares, 'share_amount_inr'));
        $this->assertEquals(100.0, $total);
    }

    public function test_unequal_split(): void
    {
        $details = [1 => 50.0, 2 => 30.0, 3 => 20.0];
        $shares = SplitCalculator::compute(100.0, 'unequal', [1, 2, 3], $details);
        
        $this->assertCount(3, $shares);
        $this->assertEquals(50.0, $shares[0]['share_amount_inr']);
        $this->assertEquals(30.0, $shares[1]['share_amount_inr']);
        $this->assertEquals(20.0, $shares[2]['share_amount_inr']);
    }

    public function test_percentage_split_normalisation(): void
    {
        // Percentages sum to 110% instead of 100% (Anomaly A9)
        $details = [1 => 30.0, 2 => 30.0, 3 => 50.0]; // Sum = 110
        $shares = SplitCalculator::compute(1100.0, 'percentage', [1, 2, 3], $details);
        
        // Normalised:
        // User 1: 30 / 110 * 1100 = 300
        // User 2: 30 / 110 * 1100 = 300
        // User 3: 50 / 110 * 1100 = 500
        $this->assertEquals(300.0, $shares[0]['share_amount_inr']);
        $this->assertEquals(300.0, $shares[1]['share_amount_inr']);
        $this->assertEquals(500.0, $shares[2]['share_amount_inr']);
        
        $total = array_sum(array_column($shares, 'share_amount_inr'));
        $this->assertEquals(1100.0, $total);
    }

    public function test_share_split(): void
    {
        // Share weights: 1, 2, 2. Total = 5 shares.
        // Amount: 100.
        // User 1: 1/5 * 100 = 20
        // User 2: 2/5 * 100 = 40
        // User 3: 2/5 * 100 = 40
        $details = [1 => 1.0, 2 => 2.0, 3 => 2.0];
        $shares = SplitCalculator::compute(100.0, 'share', [1, 2, 3], $details);
        
        $this->assertEquals(20.0, $shares[0]['share_amount_inr']);
        $this->assertEquals(40.0, $shares[1]['share_amount_inr']);
        $this->assertEquals(40.0, $shares[2]['share_amount_inr']);
    }

    public function test_rounding_reconciliation_goes_to_payer(): void
    {
        // 100 / 3 = 33.33 each. Diff = +0.01.
        // If payer is user 3, the +0.01 should go to user 3.
        $shares = SplitCalculator::compute(100.0, 'equal', [1, 2, 3], [], 3);
        
        $this->assertEquals(33.33, $shares[0]['share_amount_inr']);
        $this->assertEquals(33.33, $shares[1]['share_amount_inr']);
        $this->assertEquals(33.34, $shares[2]['share_amount_inr']);
    }
}
