<?php

namespace Tests\Infrastructure\BugDetection;

use FlowEngine\Infrastructure\BugDetection\PhpBugScanner;
use PHPUnit\Framework\TestCase;

final class PhpBugScannerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/bug-scanner-test-' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob($this->tempDir . '/*') ?: []);
            rmdir($this->tempDir);
        }
    }

    private function createTempFile(string $name, string $content): string
    {
        $path = $this->tempDir . '/' . $name;
        file_put_contents($path, $content);
        return $path;
    }

    private function scanFile(string $path): array
    {
        return (new PhpBugScanner([$path]))->scan();
    }

    // -------------------------------------------------------------------------
    // exception_swallowing
    // -------------------------------------------------------------------------

    public function test_detects_empty_catch_block(): void
    {
        $file = $this->createTempFile('EmptyCatch.php', <<<'PHP'
<?php
namespace App;
class EmptyCatch {
    public function run(): void {
        try {
            doSomething();
        } catch (\Exception $e) {
            // intentionally empty
        }
    }
}
PHP);

        $findings = $this->scanFile($file);

        $types = array_column($findings, 'type');
        $this->assertContains('exception_swallowing', $types);

        $swallowed = array_values(array_filter($findings, fn($f) => $f['type'] === 'exception_swallowing'));
        $this->assertSame('App\\EmptyCatch::run', $swallowed[0]['nodeId']);
    }

    public function test_nullable_return_null_fallback_is_not_flagged_as_exception_swallowing(): void
    {
        $file = $this->createTempFile('ReturnNullCatch.php', <<<'PHP'
<?php
namespace App;
class ReturnNullCatch {
    public function find(): ?object {
        try {
            return fetchObject();
        } catch (\RuntimeException $e) {
            return null;
        }
    }
}
PHP);

        $findings = $this->scanFile($file);

        $swallowed = array_values(array_filter($findings, fn($f) => $f['type'] === 'exception_swallowing'));
        $this->assertEmpty($swallowed, 'Nullable fallback should not be flagged as exception swallowing');
    }

    public function test_non_nullable_return_null_fallback_is_still_flagged(): void
    {
        $file = $this->createTempFile('InvalidReturnNullCatch.php', <<<'PHP'
<?php
namespace App;
class InvalidReturnNullCatch {
    public function find(): object {
        try {
            return fetchObject();
        } catch (\RuntimeException $e) {
            return null;
        }
    }
}
PHP);

        $findings = $this->scanFile($file);

        $swallowed = array_values(array_filter($findings, fn($f) => $f['type'] === 'exception_swallowing'));
        $this->assertNotEmpty($swallowed, 'Non-nullable return null fallback should still be flagged');
        $this->assertSame('App\\InvalidReturnNullCatch::find', $swallowed[0]['nodeId']);
    }

    public function test_typed_scalar_fallback_is_not_flagged_as_exception_swallowing(): void
    {
        $file = $this->createTempFile('DefaultBoolCatch.php', <<<'PHP'
<?php
namespace App;
class DefaultBoolCatch {
    public function check(): bool {
        try {
            return performCheck();
        } catch (\RuntimeException $e) {
            return false;
        }
    }
}
PHP);

        $findings = $this->scanFile($file);

        $swallowed = array_values(array_filter($findings, fn($f) => $f['type'] === 'exception_swallowing'));
        $this->assertEmpty($swallowed, 'Typed scalar fallback should not be flagged as exception swallowing');
    }

    // -------------------------------------------------------------------------
    // graceful_degradation
    // -------------------------------------------------------------------------

    public function test_catch_returning_method_call_is_graceful_degradation(): void
    {
        $file = $this->createTempFile('GracefulMethod.php', <<<'PHP'
<?php
namespace App;
class GracefulMethod {
    public function execute(): mixed {
        try {
            return doRiskyThing();
        } catch (\Exception $e) {
            return $this->fallback();
        }
    }
    private function fallback(): mixed { return null; }
}
PHP);

        $findings = $this->scanFile($file);

        $graceful = array_values(array_filter($findings, fn($f) => $f['type'] === 'graceful_degradation'));
        $this->assertNotEmpty($graceful, 'return $this->fallback() should be graceful_degradation');
        $this->assertSame('App\\GracefulMethod::execute', $graceful[0]['nodeId']);

        $swallowed = array_values(array_filter($findings, fn($f) => $f['type'] === 'exception_swallowing'));
        $this->assertEmpty($swallowed, 'Should not also be flagged as exception_swallowing');
    }

    public function test_catch_returning_new_object_is_graceful_degradation(): void
    {
        $file = $this->createTempFile('GracefulNew.php', <<<'PHP'
<?php
namespace App;
class GracefulNew {
    public function load(): mixed {
        try {
            return loadFromRemote();
        } catch (\Exception $e) {
            return new \stdClass();
        }
    }
}
PHP);

        $findings = $this->scanFile($file);

        $graceful = array_values(array_filter($findings, fn($f) => $f['type'] === 'graceful_degradation'));
        $this->assertNotEmpty($graceful, 'return new Foo() should be graceful_degradation');

        $swallowed = array_values(array_filter($findings, fn($f) => $f['type'] === 'exception_swallowing'));
        $this->assertEmpty($swallowed);
    }

    public function test_empty_catch_is_still_exception_swallowing(): void
    {
        $file = $this->createTempFile('EmptySwallow.php', <<<'PHP'
<?php
namespace App;
class EmptySwallow {
    public function run(): void {
        try {
            doSomething();
        } catch (\Exception $e) {
        }
    }
}
PHP);

        $findings = $this->scanFile($file);

        $swallowed = array_values(array_filter($findings, fn($f) => $f['type'] === 'exception_swallowing'));
        $this->assertNotEmpty($swallowed, 'Empty catch should be exception_swallowing');

        $graceful = array_values(array_filter($findings, fn($f) => $f['type'] === 'graceful_degradation'));
        $this->assertEmpty($graceful);
    }

    public function test_catch_return_null_without_declared_type_is_swallowing(): void
    {
        $file = $this->createTempFile('ReturnNullNoType.php', <<<'PHP'
<?php
namespace App;
class ReturnNullNoType {
    public function find() {
        try {
            return fetchObject();
        } catch (\Exception $e) {
            return null;
        }
    }
}
PHP);

        $findings = $this->scanFile($file);

        $swallowed = array_values(array_filter($findings, fn($f) => $f['type'] === 'exception_swallowing'));
        $this->assertNotEmpty($swallowed, 'return null without declared type should be exception_swallowing');
    }

    public function test_catch_with_multiple_statements_produces_no_swallowing_or_graceful_finding(): void
    {
        $file = $this->createTempFile('MultiStatement.php', <<<'PHP'
<?php
namespace App;
class MultiStatement {
    public function run(): void {
        try {
            doSomething();
        } catch (\Exception $e) {
            log($e->getMessage());
            return;
        }
    }
}
PHP);

        $findings = $this->scanFile($file);

        $swallowed = array_values(array_filter($findings, fn($f) => $f['type'] === 'exception_swallowing'));
        $graceful  = array_values(array_filter($findings, fn($f) => $f['type'] === 'graceful_degradation'));
        $this->assertEmpty($swallowed, 'Multi-statement catch should not be flagged as swallowing');
        $this->assertEmpty($graceful,  'Multi-statement catch should not be flagged as graceful_degradation');
    }

    public function test_catch_returning_plain_variable_is_swallowing(): void
    {
        $file = $this->createTempFile('PlainVarReturn.php', <<<'PHP'
<?php
namespace App;
class PlainVarReturn {
    public function run(): string {
        $fallback = 'default';
        try {
            return doSomething();
        } catch (\Exception $e) {
            return $fallback;
        }
    }
}
PHP);

        $findings = $this->scanFile($file);

        // Returning a plain variable is not an active recovery (no call/new), so
        // it stays conservatively classified as swallowing, not graceful.
        $swallowed = array_values(array_filter($findings, fn($f) => $f['type'] === 'exception_swallowing'));
        $this->assertNotEmpty($swallowed, 'return $var should be exception_swallowing, not graceful');

        $graceful = array_values(array_filter($findings, fn($f) => $f['type'] === 'graceful_degradation'));
        $this->assertEmpty($graceful);
    }

    public function test_catch_returning_static_call_is_graceful_degradation(): void
    {
        $file = $this->createTempFile('StaticCallReturn.php', <<<'PHP'
<?php
namespace App;
class StaticCallReturn {
    public function run(): mixed {
        try {
            return doSomething();
        } catch (\Exception $e) {
            return Helper::recover();
        }
    }
}
PHP);

        $findings = $this->scanFile($file);

        $graceful = array_values(array_filter($findings, fn($f) => $f['type'] === 'graceful_degradation'));
        $this->assertNotEmpty($graceful, 'return Helper::recover() (static call) should be graceful_degradation');

        $swallowed = array_values(array_filter($findings, fn($f) => $f['type'] === 'exception_swallowing'));
        $this->assertEmpty($swallowed);
    }

    // -------------------------------------------------------------------------
    // resource_leak
    // -------------------------------------------------------------------------

    public function test_detects_resource_leak_fopen_without_fclose(): void
    {
        $file = $this->createTempFile('ResourceLeak.php', <<<'PHP'
<?php
namespace App;
class ResourceLeak {
    public function read(string $path): string {
        $fh = fopen($path, 'r');
        $contents = fread($fh, 1024);
        return $contents;
    }
}
PHP);

        $findings = $this->scanFile($file);

        $leaks = array_values(array_filter($findings, fn($f) => $f['type'] === 'resource_leak'));
        $this->assertNotEmpty($leaks, 'Should detect fopen without fclose');
        $this->assertSame('App\\ResourceLeak::read', $leaks[0]['nodeId']);
        $this->assertSame(0.8, $leaks[0]['confidence']);
    }

    public function test_no_false_positive_fopen_with_fclose(): void
    {
        $file = $this->createTempFile('ProperClose.php', <<<'PHP'
<?php
namespace App;
class ProperClose {
    public function read(string $path): string {
        $fh = fopen($path, 'r');
        $contents = fread($fh, 1024);
        fclose($fh);
        return $contents;
    }
}
PHP);

        $findings = $this->scanFile($file);

        $leaks = array_values(array_filter($findings, fn($f) => $f['type'] === 'resource_leak'));
        $this->assertEmpty($leaks, 'Should NOT report resource leak when fopen is matched with fclose');
    }

    // -------------------------------------------------------------------------
    // missing_return_type
    // -------------------------------------------------------------------------

    public function test_detects_missing_return_type_on_public_method(): void
    {
        $file = $this->createTempFile('MissingType.php', <<<'PHP'
<?php
namespace App;
class MissingType {
    public function getValue() {
        return 42;
    }
}
PHP);

        $findings = $this->scanFile($file);

        $missing = array_values(array_filter($findings, fn($f) => $f['type'] === 'missing_return_type'));
        $this->assertNotEmpty($missing, 'Should detect public method with no return type');
        $this->assertSame('App\\MissingType::getValue', $missing[0]['nodeId']);
        $this->assertSame(0.5, $missing[0]['confidence']);
    }

    public function test_no_finding_when_return_type_declared(): void
    {
        $file = $this->createTempFile('WithType.php', <<<'PHP'
<?php
namespace App;
class WithType {
    public function getValue(): int {
        return 42;
    }
}
PHP);

        $findings = $this->scanFile($file);

        $missing = array_values(array_filter($findings, fn($f) => $f['type'] === 'missing_return_type'));
        $this->assertEmpty($missing, 'Should NOT flag method with declared return type');
    }

    public function test_constructor_not_flagged_for_missing_return_type(): void
    {
        $file = $this->createTempFile('WithConstructor.php', <<<'PHP'
<?php
namespace App;
class WithConstructor {
    public function __construct(private string $name) {}
}
PHP);

        $findings = $this->scanFile($file);

        $missing = array_values(array_filter($findings, fn($f) => $f['type'] === 'missing_return_type'));
        $this->assertEmpty($missing, '__construct should never be flagged for missing return type');
    }

    public function test_handles_empty_file(): void
    {
        $file = $this->createTempFile('Empty.php', '');

        $findings = $this->scanFile($file);

        $this->assertSame([], $findings);
    }

    // -------------------------------------------------------------------------
    // nodeId formation
    // -------------------------------------------------------------------------

    public function test_node_id_includes_namespace(): void
    {
        $file = $this->createTempFile('Namespaced.php', <<<'PHP'
<?php
namespace MyApp\Services;
class UserService {
    public function create() {
        return null;
    }
}
PHP);

        $findings = $this->scanFile($file);

        $nodeIds = array_column($findings, 'nodeId');
        $this->assertContains('MyApp\\Services\\UserService::create', $nodeIds);
    }
}
