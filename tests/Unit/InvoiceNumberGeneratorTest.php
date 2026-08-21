<?php

namespace Tests\Unit;

use App\Helpers\Helpers;
use App\Models\Invoice;
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class InvoiceNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2025, 6, 17));

        // Prevent the listener (so updateLastNumber() never writes disk)
        Invoice::flushEventListeners();
        Member::flushEventListeners();

        // Override settings in-memory so we always start at "FIT-1"
        Helpers::setTestSettingsOverride([
            'invoice' => ['prefix' => '', 'last_number' => ''],
        ]);
    }

    #[Test]
    #[TestDox('Step 1: Given no existing invoices → returns FIT-1')]
    public function noExistingInvoicesReturnsFit1(): void
    {
        $next = Helpers::generateLastNumber(
            'invoice',
            Invoice::class,
            '2025-06-17'
        );

        $this->assertSame(
            'FIT-1',
            $next,
            'When there are no invoices at all, the next number should be FIT-1'
        );
    }

    #[Test]
    #[TestDox('Step 2: Given two invoices in the fiscal year → returns FIT-3')]
    public function twoInRangeInvoicesReturnsFit3(): void
    {
        Invoice::factory()->create([
            'number' => 'FIT-1',
            'date'   => '2025-04-01',
        ]);
        Invoice::factory()->create([
            'number' => 'FIT-2',
            'date'   => '2025-05-01',
        ]);

        $next = Helpers::generateLastNumber(
            'invoice',
            Invoice::class,
            '2025-06-17'
        );

        $this->assertSame(
            'FIT-3',
            $next,
            'With two in-year invoices (FIT-1, FIT-2), the next should be FIT-3'
        );
    }

    #[Test]
    #[TestDox('Step 3: Given only out-of-range invoices → returns FIT-1')]
    public function outOfRangeInvoicesReturnsFit1(): void
    {
        // This one is dated before the FY start, so should be ignored
        Invoice::factory()->create([
            'number' => 'FIT-1',
            'date'   => '2024-03-15',
        ]);

        $next = Helpers::generateLastNumber(
            'invoice',
            Invoice::class,
            '2025-06-17'
        );

        $this->assertSame(
            'FIT-1',
            $next,
            'An invoice before the fiscal-year cutoff should not bump the counter'
        );
    }
}
