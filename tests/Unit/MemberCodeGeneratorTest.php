<?php

namespace Tests\Unit;

use App\Helpers\Helpers;
use App\Models\Member;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class MemberCodeGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2025, 6, 17));

        // Prevent the Member::saving listener (so updateLastNumber() never writes disk)
        Member::flushEventListeners();

        // Override settings in-memory so we always start at "FIT-1"
        Helpers::setTestSettingsOverride([
            'member' => ['prefix' => '', 'last_number' => ''],
        ]);
    }

    #[Test]
    #[TestDox('Step 1: Given no existing members → returns FIT-1')]
    public function noExistingMembersReturnsFit1(): void
    {
        $next = Helpers::generateLastNumber(
            'member',
            Member::class,
            null,
            'code'
        );

        $this->assertSame(
            'FIT-1',
            $next,
            'When there are no members at all, the next number should be FIT-1'
        );
    }

    #[Test]
    #[TestDox('Step 2: Given two members in the fiscal year → returns FIT-3')]
    public function twoInRangeMembersReturnsFit3(): void
    {
        Member::factory()->create([
            'code' => 'FIT-1'
        ]);
        Member::factory()->create([
            'code' => 'FIT-2'
        ]);

        $next = Helpers::generateLastNumber(
            'member',
            Member::class,
            null,
            'code'
        );

        $this->assertSame(
            'FIT-3',
            $next,
            'With two in-year members (FIT-1, FIT-2), the next should be FIT-3'
        );
    }

    #[Test]
    #[TestDox('Step 3: Given only out-of-range members → returns FIT-1')]
    public function outOfRangeMembersReturnsFit1(): void
    {
        // This one is dated before the FY start, so should be ignored
        Member::factory()->create([
            'code' => 'FIT-1',
            'created_at' => Carbon::create(2025, 3, 31, 23, 59, 59),
        ]);

        $next = Helpers::generateLastNumber(
            'member',
            Member::class,
            null,
            'code'
        );

        $this->assertSame(
            'FIT-1',
            $next,
            'An member before the fiscal-year cutoff should not bump the counter'
        );
    }
}
