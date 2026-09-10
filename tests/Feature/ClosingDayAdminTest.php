<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ClosingDay;
use Tests\TestCase;

final class ClosingDayAdminTest extends TestCase
{
    private const string TOKEN = 'test-admin-token';

    protected function setUp(): void
    {
        parent::setUp();

        // Set explicitly rather than read from the environment, so the suite
        // behaves the same wherever it runs.
        config(['scheduling.admin_token' => self::TOKEN]);
    }

    public function test_an_admin_can_close_a_day_and_it_disappears_from_availability(): void
    {
        $this->getJson('/api/availability?date=2026-09-14')->assertJsonCount(16, 'data');

        $this->asAdmin()
            ->postJson('/api/closing-days', ['date' => '2026-09-14', 'reason' => 'Stocktaking'])
            ->assertCreated()
            ->assertJsonPath('data.date', '2026-09-14')
            ->assertJsonPath('data.reason', 'Stocktaking');

        $this->getJson('/api/availability?date=2026-09-14')->assertJsonCount(0, 'data');
    }

    public function test_reopening_a_day_brings_its_slots_back(): void
    {
        $id = $this->asAdmin()
            ->postJson('/api/closing-days', ['date' => '2026-09-14'])
            ->assertCreated()
            ->json('data.id');

        $this->asAdmin()->deleteJson("/api/closing-days/{$id}")->assertNoContent();

        $this->getJson('/api/availability?date=2026-09-14')->assertJsonCount(16, 'data');
    }

    public function test_closing_the_same_day_twice_updates_the_existing_row(): void
    {
        $this->asAdmin()->postJson('/api/closing-days', ['date' => '2026-09-14', 'reason' => 'First'])
            ->assertCreated();

        $this->asAdmin()->postJson('/api/closing-days', ['date' => '2026-09-14', 'reason' => 'Second'])
            ->assertOk()
            ->assertJsonPath('data.reason', 'Second');

        $this->assertSame(1, ClosingDay::query()->count());
    }

    public function test_closing_days_are_publicly_readable(): void
    {
        $this->closeLocationOn('2026-09-14', 'Stocktaking');

        $this->getJson('/api/closing-days')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.date', '2026-09-14');
    }

    public function test_writing_requires_the_admin_token(): void
    {
        $this->postJson('/api/closing-days', ['date' => '2026-09-14'])->assertUnauthorized();

        $this->withHeader('X-Admin-Token', 'wrong')
            ->postJson('/api/closing-days', ['date' => '2026-09-14'])
            ->assertUnauthorized();

        $this->assertSame(0, ClosingDay::query()->count());
    }

    public function test_the_endpoints_are_disabled_when_no_token_is_configured(): void
    {
        config(['scheduling.admin_token' => null]);

        $this->asAdmin()
            ->postJson('/api/closing-days', ['date' => '2026-09-14'])
            ->assertStatus(503);
    }

    private function asAdmin(): static
    {
        return $this->withHeader('X-Admin-Token', self::TOKEN);
    }
}
