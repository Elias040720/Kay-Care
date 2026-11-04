<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Services\Checkout\CartPricer;
use PHPUnit\Framework\TestCase;

class OrderStatusTest extends TestCase
{
    public function test_final_states_have_no_transitions(): void
    {
        $this->assertTrue(OrderStatus::Delivered->isFinal());
        $this->assertTrue(OrderStatus::Cancelled->isFinal());
        $this->assertFalse(OrderStatus::Pending->isFinal());
    }

    public function test_only_forward_steps_and_cancellation_are_allowed(): void
    {
        $this->assertTrue(OrderStatus::Pending->canTransitionTo(OrderStatus::Confirmed));
        $this->assertTrue(OrderStatus::Ready->canTransitionTo(OrderStatus::Cancelled));
        $this->assertFalse(OrderStatus::Pending->canTransitionTo(OrderStatus::Delivered));
        $this->assertFalse(OrderStatus::Confirmed->canTransitionTo(OrderStatus::Pending));
    }

    public function test_pricer_merges_lines_and_computes_included_tax(): void
    {
        $pricer = new CartPricer;

        $this->assertSame([3 => 3, 7 => 1], $pricer->normalize([
            ['product_id' => 7, 'quantity' => 1],
            ['product_id' => 3, 'quantity' => 1],
            ['product_id' => '3', 'quantity' => '2'],
        ]));

        $totals = $pricer->totals([
            ['line_total_cents' => 11800, 'quantity' => 2],
        ]);
        $this->assertSame(['items_count' => 2, 'subtotal_cents' => 10000, 'tax_cents' => 1800, 'total_cents' => 11800], $totals);
    }
}
