<?php

namespace Tests\Docs;

use FlowEngine\Application\MCP\McpServer;
use FlowEngine\Infrastructure\Config\SchemaValidator;
use PHPUnit\Framework\TestCase;

/**
 * Gate de coerencia: docs publicas vs codigo real.
 * Falha quando configuration.md / mcp.md / CLI_COMMANDS.md divergem da implementacao.
 */
final class DocumentationCoherenceTest extends TestCase
{
    private const DOCS = __DIR__ . '/../../docs';
    private const ROOT = __DIR__ . '/../..';

    /** Comandos fora da referencia publica de CLI_COMMANDS.md: builtin, experimentais ou nao registrados. */
    private const INTERNAL_COMMANDS = [
        'help', 'interactive', 'compare', 'simulate',
        'appmap-diff', 'run-guided',
    ];

    public function test_configuration_md_json_examples_validate_against_schema(): void
    {
        $md = file_get_contents(self::DOCS . '/configuration.md');
        $blocks = $this->jsonBlocks($md);
        $this->assertNotEmpty($blocks, 'configuration.md has no JSON example to validate');

        $validator = new SchemaValidator(self::ROOT . '/composer.json');
        foreach ($blocks as $block) {
            $data = json_decode($block, true, 512, JSON_THROW_ON_ERROR);
            $validator->validate($data);
        }
        $this->addToAssertionCount(count($blocks));
    }

    public function test_mcp_md_tool_list_matches_server(): void
    {
        $server = new McpServer();
        $response = $server->dispatch([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'method'  => 'tools/list',
        ]);
        $real = array_column($response['result']['tools'], 'name');
        sort($real);

        $md = file_get_contents(self::DOCS . '/mcp.md');
        preg_match_all('/flow_[a-z_]+/', $md, $matches);
        $documented = array_values(array_unique($matches[0]));
        sort($documented);

        $this->assertSame(
            $real,
            $documented,
            'docs/mcp.md tool mentions diverge from McpServer tools/list'
        );
    }

    public function test_cli_commands_md_documents_every_registered_command(): void
    {
        $real = $this->realCommandNames();
        $documented = $this->documentedCommandNames();

        $missing = array_values(array_diff($real, $documented, self::INTERNAL_COMMANDS));
        $this->assertSame(
            [],
            $missing,
            'Commands missing from docs/CLI_COMMANDS.md: ' . implode(', ', $missing)
        );
    }

    /** @return string[] */
    private function jsonBlocks(string $markdown): array
    {
        preg_match_all('/```json\s*\n(.*?)```/s', $markdown, $m);
        return array_map('trim', $m[1]);
    }

    /** @return string[] */
    private function realCommandNames(): array
    {
        $names = [];
        foreach (glob(self::ROOT . '/src/Application/CLI/Command/*Command.php') as $file) {
            $src = file_get_contents($file);
            if (preg_match_all('/\$command === \'([a-z][a-z-]*)\'/', $src, $m)) {
                foreach ($m[1] as $name) {
                    $names[$name] = true;
                }
            }
        }
        return array_keys($names);
    }

    /** @return string[] */
    private function documentedCommandNames(): array
    {
        $md = file_get_contents(self::DOCS . '/CLI_COMMANDS.md');
        preg_match_all('/^-\s+`([a-z][a-z-]*)/m', $md, $m);
        return array_values(array_unique($m[1]));
    }
}
