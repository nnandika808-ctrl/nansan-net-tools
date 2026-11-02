<?php
namespace Tests\Unit;
use App\Generators\OutputComparison;
use PHPUnit\Framework\TestCase;
class OutputComparisonTest extends TestCase
{
    public function test_exact_comparison_does_not_normalize_line_endings_or_spacing(): void
    {
        $this->assertFalse(OutputComparison::exact("a\r\nb","a\nb")['matched']);
        $result=OutputComparison::exact("a\nb","a\n b"); $this->assertSame(2,$result['diff'][0]['line']);
        $this->assertTrue(OutputComparison::exact('same','same')['matched']);
    }
    public function test_semantic_projection_is_explicitly_limited_to_tested_fields(): void
    {
        $result=OutputComparison::projection(['denominator'=>3],['denominator'=>3,'unverified'=>'other']);
        $this->assertTrue($result['projection_matched']); $this->assertSame(['denominator'],$result['scope']);
        $this->assertNotEmpty(OutputComparison::projection(['denominator'=>3],['denominator'=>2])['diff']);
    }
}
