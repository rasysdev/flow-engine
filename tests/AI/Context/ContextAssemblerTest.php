<?php
use PHPUnit\Framework\TestCase;
use FlowEngine\AI\Context\ContextAssembler;

final class ContextAssemblerTest extends TestCase
{
    public function test_context_is_serializable(): void
    {
        $assembler = new ContextAssembler();
        $ctx = $assembler->impact(['impact' => ['upstream'=>[], 'downstream'=>[]]]);
        $this->assertIsString(json_encode($ctx));
    }
}
