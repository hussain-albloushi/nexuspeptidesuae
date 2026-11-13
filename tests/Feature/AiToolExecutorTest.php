<?php

namespace Tests\Feature;

use App\Services\AI\Tools\ToolExecutor;
use App\Services\AI\Tools\ToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiToolExecutorTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_tool_is_rejected(): void
    {
        [$ok, $error] = ToolExecutor::validate('dropAllTables', []);

        $this->assertFalse($ok);
        $this->assertStringContainsString('Unknown tool', (string) $error);
    }

    public function test_missing_required_parameter_is_rejected(): void
    {
        [$ok, $error] = ToolExecutor::validate('getSalesSummary', ['start' => '2026-10-01']);

        $this->assertFalse($ok);
        $this->assertStringContainsString('end', (string) $error);
    }

    public function test_parameter_type_mismatch_is_rejected(): void
    {
        [$ok, $error] = ToolExecutor::validate('getTopSellingProducts', [
            'start' => '2026-10-01', 'end' => '2026-11-01', 'limit' => 'not-a-number',
        ]);

        $this->assertFalse($ok);
        $this->assertStringContainsString('limit', (string) $error);
    }

    public function test_range_over_five_years_is_rejected(): void
    {
        [$ok, $error] = ToolExecutor::validate('getSalesSummary', ['start' => '2010-01-01', 'end' => '2026-11-13']);

        $this->assertFalse($ok);
        $this->assertStringContainsString('5 years', (string) $error);
    }

    public function test_compare_periods_validates_both_windows(): void
    {
        [$ok, $error] = ToolExecutor::validate('compareSalesPeriods', [
            'start_a' => '2026-10-01', 'end_a' => '2026-11-01',
            'start_b' => '2020-01-01', 'end_b' => '2026-11-13',
        ]);

        $this->assertFalse($ok);
        $this->assertStringContainsString('5 years', (string) $error);

        [$ok] = ToolExecutor::validate('compareSalesPeriods', [
            'start_a' => '2026-10-14', 'end_a' => '2026-11-13',
            'start_b' => '2026-09-14', 'end_b' => '2026-10-13',
        ]);

        $this->assertTrue($ok);
    }

    public function test_start_after_end_is_rejected(): void
    {
        [$ok, $error] = ToolExecutor::validate('getSalesSummary', ['start' => '2026-11-13', 'end' => '2026-10-01']);

        $this->assertFalse($ok);
        $this->assertStringContainsString('start', (string) $error);
    }

    public function test_tool_definitions_serialise_as_a_json_array(): void
    {
        $tools = ToolRegistry::openAiTools();

        $this->assertTrue(array_is_list($tools));
        $this->assertCount(count(ToolRegistry::names()), $tools);
        $this->assertStringStartsWith('[', (string) json_encode($tools));
        $this->assertSame('getSalesSummary', $tools[0]['function']['name']);
    }

    public function test_valid_tool_call_executes(): void
    {
        $result = ToolExecutor::execute('getSalesSummary', ['start' => '2026-10-01', 'end' => '2026-11-13']);

        $this->assertSame('getSalesSummary', $result['tool']);
        $this->assertArrayHasKey('revenue', $result['result']);
        $this->assertArrayHasKey('orders', $result['result']);
    }
}
