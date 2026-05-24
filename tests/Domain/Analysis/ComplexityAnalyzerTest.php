<?php

namespace Tests\Domain\Analysis;

use FlowEngine\Domain\Analysis\ComplexityAnalyzer;
use FlowEngine\Domain\Flow\Flow;
use FlowEngine\Domain\Flow\Node;
use PHPUnit\Framework\TestCase;

final class ComplexityAnalyzerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/flow-engine-test-' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tempDir . '/*.php'));
        rmdir($this->tempDir);
    }

    public function test_calculates_simple_method_complexity(): void
    {
        $file = $this->createTempFile('<?php
class Simple {
    public function method() {
        return 42;
    }
}
');

        $node = new Node('Simple', 'method', $file, 3);
        $flow = new Flow([$node], []);
        
        $analyzer = new ComplexityAnalyzer($flow);
        $complexity = $analyzer->calculateMethodComplexity($file, 'Simple', 'method');
        
        $this->assertEquals(1, $complexity); // Base complexity
    }

    public function test_calculates_if_statement_complexity(): void
    {
        $file = $this->createTempFile('<?php
class IfTest {
    public function method($x) {
        if ($x > 0) {
            return true;
        }
        return false;
    }
}
');

        $node = new Node('IfTest', 'method', $file, 3);
        $flow = new Flow([$node], []);
        
        $analyzer = new ComplexityAnalyzer($flow);
        $complexity = $analyzer->calculateMethodComplexity($file, 'IfTest', 'method');
        
        $this->assertEquals(2, $complexity); // 1 + 1 if
    }

    public function test_calculates_loop_complexity(): void
    {
        $file = $this->createTempFile('<?php
class LoopTest {
    public function method($items) {
        foreach ($items as $item) {
            if ($item) {
                return true;
            }
        }
        return false;
    }
}
');

        $node = new Node('LoopTest', 'method', $file, 3);
        $flow = new Flow([$node], []);
        
        $analyzer = new ComplexityAnalyzer($flow);
        $complexity = $analyzer->calculateMethodComplexity($file, 'LoopTest', 'method');
        
        $this->assertEquals(3, $complexity); // 1 + 1 foreach + 1 if
    }

    public function test_calculates_switch_complexity(): void
    {
        $file = $this->createTempFile('<?php
class SwitchTest {
    public function method($x) {
        switch ($x) {
            case 1:
                return "one";
            case 2:
                return "two";
            default:
                return "other";
        }
    }
}
');

        $node = new Node('SwitchTest', 'method', $file, 3);
        $flow = new Flow([$node], []);
        
        $analyzer = new ComplexityAnalyzer($flow);
        $complexity = $analyzer->calculateMethodComplexity($file, 'SwitchTest', 'method');
        
        $this->assertEquals(4, $complexity); // 1 + 3 cases
    }

    public function test_calculates_ternary_complexity(): void
    {
        $file = $this->createTempFile('<?php
class TernaryTest {
    public function method($x) {
        return $x ? "yes" : "no";
    }
}
');

        $node = new Node('TernaryTest', 'method', $file, 3);
        $flow = new Flow([$node], []);
        
        $analyzer = new ComplexityAnalyzer($flow);
        $complexity = $analyzer->calculateMethodComplexity($file, 'TernaryTest', 'method');
        
        $this->assertEquals(2, $complexity); // 1 + 1 ternary
    }

    public function test_calculates_logical_operators_complexity(): void
    {
        $file = $this->createTempFile('<?php
class LogicalTest {
    public function method($a, $b, $c) {
        if ($a && $b || $c) {
            return true;
        }
        return false;
    }
}
');

        $node = new Node('LogicalTest', 'method', $file, 3);
        $flow = new Flow([$node], []);
        
        $analyzer = new ComplexityAnalyzer($flow);
        $complexity = $analyzer->calculateMethodComplexity($file, 'LogicalTest', 'method');
        
        $this->assertGreaterThanOrEqual(3, $complexity); // 1 + if + && + ||
    }

    public function test_determines_complexity_level(): void
    {
        $this->assertEquals('LOW', ComplexityAnalyzer::getComplexityLevel(5));
        $this->assertEquals('MEDIUM', ComplexityAnalyzer::getComplexityLevel(15));
        $this->assertEquals('HIGH', ComplexityAnalyzer::getComplexityLevel(25));
        $this->assertEquals('CRITICAL', ComplexityAnalyzer::getComplexityLevel(60));
    }

    public function test_finds_complex_methods(): void
    {
        $file = $this->createTempFile('<?php
class ComplexClass {
    public function simple() {
        return 1;
    }
    
    public function veryComplex($x, $y, $z) {
        // Complexity = 1 (base)
        if ($x) {                           // +1 = 2
            if ($x > 10) {                  // +1 = 3
                if ($x < 100) {             // +1 = 4
                    for ($i = 0; $i < $x; $i++) {  // +1 = 5
                        if ($i % 2) {       // +1 = 6
                            if ($i > 5) {   // +1 = 7
                                return $i;
                            }
                        }
                    }
                }
            }
        }
        
        if ($y) {                           // +1 = 8
            foreach ($y as $item) {         // +1 = 9
                if ($item) {                // +1 = 10
                    switch ($item) {        // +3 cases = 13
                        case 1:
                        case 2:
                        case 3:
                            return $item;
                    }
                }
            }
        }
        
        if ($z && $x || $y) {              // +1 if +1 && +1 || = 16
            while ($z > 0) {               // +1 = 17
                if ($z % 2) {              // +1 = 18
                    $z--;
                } else {                    // else doesn\'t add
                    $z -= 2;
                }
            }
        }
        
        // More conditions to reach HIGH (21+)
        if ($x > 0) {                      // +1 = 19
            if ($y > 0) {                  // +1 = 20
                if ($z > 0) {              // +1 = 21
                    return true;
                }
            }
        }
        
        return 0;
    }
}
');

        $nodes = [
            new Node('ComplexClass', 'simple', $file, 3),
            new Node('ComplexClass', 'veryComplex', $file, 7),
        ];
        $flow = new Flow($nodes, []);
        
        $analyzer = new ComplexityAnalyzer($flow);
        $complex = $analyzer->findComplexMethods();
        
        // Only 'veryComplex' should be flagged (complexity >= 21)
        $this->assertArrayNotHasKey('ComplexClass::simple', $complex);
        $this->assertArrayHasKey('ComplexClass::veryComplex', $complex);
        
        // Verify it's HIGH level
        $this->assertEquals('HIGH', $complex['ComplexClass::veryComplex']['level']);
    }

    public function test_calculates_stats(): void
    {
        $file = $this->createTempFile('<?php
class StatsTest {
    public function a() { return 1; }
    public function b($x) { if ($x) return true; }
}
');

        $nodes = [
            new Node('StatsTest', 'a', $file, 3),
            new Node('StatsTest', 'b', $file, 4),
        ];
        $flow = new Flow($nodes, []);
        
        $analyzer = new ComplexityAnalyzer($flow);
        $stats = $analyzer->stats();
        
        $this->assertEquals(2, $stats['total']);
        $this->assertArrayHasKey('avgComplexity', $stats);
        $this->assertArrayHasKey('maxComplexity', $stats);
        $this->assertArrayHasKey('byLevel', $stats);
    }

    private function createTempFile(string $content): string
    {
        $file = $this->tempDir . '/' . uniqid('test_', true) . '.php';
        file_put_contents($file, $content);
        return $file;
    }
}