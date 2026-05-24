<?php

namespace Tests\Bootstrap;

use FlowEngine\Bootstrap\LanguageSupportCatalog;
use PHPUnit\Framework\TestCase;

final class LanguageSupportCatalogTest extends TestCase
{
    public function test_supported_languages_payload_exposes_full_and_edge_only_support(): void
    {
        $catalog = new LanguageSupportCatalog();
        $payload = $catalog->supportedLanguagesPayload();

        $byId = [];
        foreach ($payload as $item) {
            $byId[$item['id']] = $item;
        }

        self::assertSame('full', $byId['php']['supportLevel']);
        self::assertSame('full', $byId['python']['supportLevel']);
        self::assertSame('full', $byId['typescript']['supportLevel']);
        self::assertSame('full', $byId['javascript']['supportLevel']);
        self::assertSame('full', $byId['go']['supportLevel']);
        self::assertSame('full', $byId['dart']['supportLevel']);
        self::assertSame('edge_only', $byId['blade']['supportLevel']);
        self::assertStringContainsString('Livewire', $byId['blade']['notes']);
    }

    public function test_detect_from_files_collapses_extensions_and_prioritizes_blade_suffix(): void
    {
        $catalog = new LanguageSupportCatalog();

        $detected = $catalog->detectFromFiles([
            '/tmp/app/Service.php',
            '/tmp/app/views/order.blade.php',
            '/tmp/app/frontend/page.tsx',
            '/tmp/app/frontend/script.jsx',
            '/tmp/app/worker.py',
            '/tmp/app/main.go',
            '/tmp/app/mobile/app.dart',
        ]);

        self::assertSame(
            ['php', 'python', 'typescript', 'javascript', 'go', 'dart', 'blade'],
            $detected
        );
    }

    public function test_supported_configured_languages_expands_php_to_blade_edge_support(): void
    {
        $catalog = new LanguageSupportCatalog();

        self::assertSame(
            ['php', 'typescript', 'javascript', 'blade'],
            $catalog->supportedConfiguredLanguages(['php', 'ts', 'jsx'])
        );
    }

    public function test_description_and_payload_summaries_are_human_readable(): void
    {
        $catalog = new LanguageSupportCatalog();

        self::assertStringContainsString('Current support', $catalog->descriptionSummary());
        self::assertStringContainsString('TypeScript/JavaScript', $catalog->descriptionSummary());
        self::assertStringContainsString('Supports', $catalog->payloadSummary());
        self::assertStringContainsString('PHP', $catalog->payloadSummary());
        self::assertStringContainsString('TypeScript/JavaScript', $catalog->payloadSummary());
    }
}
