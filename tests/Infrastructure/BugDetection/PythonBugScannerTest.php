<?php

namespace Tests\Infrastructure\BugDetection;

use FlowEngine\Domain\Analysis\Bug;
use FlowEngine\Infrastructure\BugDetection\PythonBugScanner;
use PHPUnit\Framework\TestCase;

final class PythonBugScannerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/py-bug-scanner-test-' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob($this->tempDir . '/*') ?: []);
            rmdir($this->tempDir);
        }
    }

    private function createFile(string $name, string $content): string
    {
        $path = $this->tempDir . '/' . $name;
        file_put_contents($path, $content);
        return $path;
    }

    private function scan(string $path): array
    {
        return (new PythonBugScanner([$path]))->scan();
    }

    private function findingsOfType(array $findings, string $type): array
    {
        return array_values(array_filter($findings, fn(array $f) => $f['type'] === $type));
    }

    // -------------------------------------------------------------------------
    // Exception Swallowing
    // -------------------------------------------------------------------------

    public function test_detects_bare_except_with_pass(): void
    {
        $file = $this->createFile('bare.py', <<<'PY'
def run():
    try:
        risky()
    except:
        pass
PY);

        $f = $this->findingsOfType($this->scan($file), Bug::TYPE_PYTHON_EXCEPTION_SWALLOWING);
        $this->assertNotEmpty($f, 'bare except: pass should be detected');
        $this->assertSame('bare::run', $f[0]['nodeId']);
        $this->assertSame(0.85, $f[0]['confidence']);
    }

    public function test_detects_except_exception_with_pass(): void
    {
        $file = $this->createFile('exc.py', <<<'PY'
def run():
    try:
        risky()
    except Exception:
        pass
PY);

        $f = $this->findingsOfType($this->scan($file), Bug::TYPE_PYTHON_EXCEPTION_SWALLOWING);
        $this->assertNotEmpty($f);
        $this->assertSame('exc::run', $f[0]['nodeId']);
    }

    public function test_detects_except_exception_as_e_with_pass(): void
    {
        $file = $this->createFile('exc_as.py', <<<'PY'
def handle():
    try:
        risky()
    except Exception as e:
        pass
PY);

        $f = $this->findingsOfType($this->scan($file), Bug::TYPE_PYTHON_EXCEPTION_SWALLOWING);
        $this->assertNotEmpty($f);
    }

    public function test_detects_except_block_with_only_comment(): void
    {
        $file = $this->createFile('comment.py', <<<'PY'
def handle():
    try:
        risky()
    except Exception:
        # TODO: fix later
        pass
PY);

        $f = $this->findingsOfType($this->scan($file), Bug::TYPE_PYTHON_EXCEPTION_SWALLOWING);
        $this->assertNotEmpty($f, 'except block with only a comment+pass is still swallowing');
    }

    public function test_no_false_positive_except_with_real_handling(): void
    {
        $file = $this->createFile('handled.py', <<<'PY'
def handle():
    try:
        risky()
    except Exception as e:
        print(e)
        raise
PY);

        $f = $this->findingsOfType($this->scan($file), Bug::TYPE_PYTHON_EXCEPTION_SWALLOWING);
        $this->assertEmpty($f, 'except with real handling should NOT be flagged');
    }

    // -------------------------------------------------------------------------
    // Mutable Default Argument
    // -------------------------------------------------------------------------

    public function test_detects_mutable_list_default(): void
    {
        $file = $this->createFile('mut_list.py', <<<'PY'
def append(items=[]):
    items.append(1)
    return items
PY);

        $f = $this->findingsOfType($this->scan($file), Bug::TYPE_PYTHON_MUTABLE_DEFAULT);
        $this->assertNotEmpty($f);
        $this->assertSame('mut_list::append', $f[0]['nodeId']);
        $this->assertSame(0.95, $f[0]['confidence']);
    }

    public function test_detects_mutable_dict_default(): void
    {
        $file = $this->createFile('mut_dict.py', <<<'PY'
def configure(config={}):
    return config
PY);

        $f = $this->findingsOfType($this->scan($file), Bug::TYPE_PYTHON_MUTABLE_DEFAULT);
        $this->assertNotEmpty($f);
    }

    public function test_detects_nonempty_mutable_list_default(): void
    {
        $file = $this->createFile('mut_nonempty.py', <<<'PY'
def process(items=[1, 2, 3]):
    return items
PY);

        $f = $this->findingsOfType($this->scan($file), Bug::TYPE_PYTHON_MUTABLE_DEFAULT);
        $this->assertNotEmpty($f, 'Non-empty mutable default should also be detected');
    }

    public function test_no_false_positive_none_default(): void
    {
        $file = $this->createFile('none_default.py', <<<'PY'
def process(items=None):
    return items or []
PY);

        $f = $this->findingsOfType($this->scan($file), Bug::TYPE_PYTHON_MUTABLE_DEFAULT);
        $this->assertEmpty($f, 'None default should NOT be flagged');
    }

    public function test_no_false_positive_string_default(): void
    {
        $file = $this->createFile('str_default.py', <<<'PY'
def greet(name="World"):
    return f"Hello {name}"
PY);

        $f = $this->findingsOfType($this->scan($file), Bug::TYPE_PYTHON_MUTABLE_DEFAULT);
        $this->assertEmpty($f, 'String default should NOT be flagged');
    }

    // -------------------------------------------------------------------------
    // Missing Return Type
    // -------------------------------------------------------------------------

    public function test_detects_public_function_without_return_type(): void
    {
        $file = $this->createFile('no_type.py', <<<'PY'
def compute(x, y):
    return x + y
PY);

        $f = $this->findingsOfType($this->scan($file), Bug::TYPE_PYTHON_MISSING_RETURN_TYPE);
        $this->assertNotEmpty($f);
        $this->assertSame('no_type::compute', $f[0]['nodeId']);
        $this->assertSame(0.40, $f[0]['confidence']);
    }

    public function test_no_finding_for_typed_function(): void
    {
        $file = $this->createFile('typed.py', <<<'PY'
def compute(x: int, y: int) -> int:
    return x + y
PY);

        $f = $this->findingsOfType($this->scan($file), Bug::TYPE_PYTHON_MISSING_RETURN_TYPE);
        $this->assertEmpty($f, 'Function with -> annotation should NOT be flagged');
    }

    public function test_private_function_not_flagged(): void
    {
        $file = $this->createFile('private.py', <<<'PY'
def _helper(x):
    return x * 2
PY);

        $f = $this->findingsOfType($this->scan($file), Bug::TYPE_PYTHON_MISSING_RETURN_TYPE);
        $this->assertEmpty($f, '_private functions should NOT be flagged');
    }

    public function test_dunder_function_not_flagged(): void
    {
        $file = $this->createFile('dunder.py', <<<'PY'
def __str__(self):
    return "obj"
PY);

        $f = $this->findingsOfType($this->scan($file), Bug::TYPE_PYTHON_MISSING_RETURN_TYPE);
        $this->assertEmpty($f, '__dunder__ functions should NOT be flagged');
    }

    // -------------------------------------------------------------------------
    // Unchecked Subprocess
    // -------------------------------------------------------------------------

    public function test_detects_unchecked_os_system(): void
    {
        $file = $this->createFile('os_sys.py', <<<'PY'
import os

def deploy():
    os.system("make build")
PY);

        $f = $this->findingsOfType($this->scan($file), Bug::TYPE_PYTHON_UNCHECKED_SUBPROCESS);
        $this->assertNotEmpty($f);
        $this->assertSame('os_sys::deploy', $f[0]['nodeId']);
        $this->assertSame(0.75, $f[0]['confidence']);
    }

    public function test_detects_unchecked_subprocess_run(): void
    {
        $file = $this->createFile('sub_run.py', <<<'PY'
import subprocess

def deploy():
    subprocess.run(["git", "push"])
PY);

        $f = $this->findingsOfType($this->scan($file), Bug::TYPE_PYTHON_UNCHECKED_SUBPROCESS);
        $this->assertNotEmpty($f);
        $this->assertSame('sub_run::deploy', $f[0]['nodeId']);
    }

    public function test_detects_unchecked_subprocess_call(): void
    {
        $file = $this->createFile('sub_call.py', <<<'PY'
import subprocess

def build():
    subprocess.call(["make", "clean"])
PY);

        $f = $this->findingsOfType($this->scan($file), Bug::TYPE_PYTHON_UNCHECKED_SUBPROCESS);
        $this->assertNotEmpty($f);
    }

    public function test_no_false_positive_assigned_subprocess(): void
    {
        $file = $this->createFile('assigned.py', <<<'PY'
import subprocess

def check():
    result = subprocess.run(["git", "status"], capture_output=True)
    return result.returncode
PY);

        $f = $this->findingsOfType($this->scan($file), Bug::TYPE_PYTHON_UNCHECKED_SUBPROCESS);
        $this->assertEmpty($f, 'Assigned subprocess result should NOT be flagged');
    }

    public function test_no_false_positive_assigned_os_system(): void
    {
        $file = $this->createFile('assigned_os.py', <<<'PY'
import os

def check():
    rc = os.system("ls")
    return rc
PY);

        $f = $this->findingsOfType($this->scan($file), Bug::TYPE_PYTHON_UNCHECKED_SUBPROCESS);
        $this->assertEmpty($f, 'Assigned os.system result should NOT be flagged');
    }

    // -------------------------------------------------------------------------
    // NodeId and module name
    // -------------------------------------------------------------------------

    public function test_node_id_uses_basename_without_extension(): void
    {
        $file = $this->createFile('my_module.py', <<<'PY'
def my_func(items=[]):
    return items
PY);

        $f = $this->findingsOfType($this->scan($file), Bug::TYPE_PYTHON_MUTABLE_DEFAULT);
        $this->assertNotEmpty($f);
        $this->assertSame('my_module::my_func', $f[0]['nodeId']);
    }

    // -------------------------------------------------------------------------
    // Edge cases
    // -------------------------------------------------------------------------

    public function test_empty_file_returns_no_findings(): void
    {
        $file = $this->createFile('empty.py', '');
        $this->assertSame([], $this->scan($file));
    }

    public function test_fixture_file_is_scannable(): void
    {
        $fixture = __DIR__ . '/../Fixtures/ExampleProject/App/src/bug_patterns.py';
        $this->assertFileExists($fixture);

        $findings = (new PythonBugScanner([$fixture]))->scan();

        // Fixture contains known patterns — just verify we find multiple types
        $types = array_unique(array_column($findings, 'type'));
        $this->assertContains(Bug::TYPE_PYTHON_EXCEPTION_SWALLOWING, $types);
        $this->assertContains(Bug::TYPE_PYTHON_MUTABLE_DEFAULT, $types);
        $this->assertContains(Bug::TYPE_PYTHON_MISSING_RETURN_TYPE, $types);
        $this->assertContains(Bug::TYPE_PYTHON_UNCHECKED_SUBPROCESS, $types);
    }
}
