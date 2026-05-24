<?php

namespace Tests\Infrastructure\Analyzer;

use FlowEngine\Domain\Flow\DefaultNodeFactory;
use FlowEngine\Infrastructure\Analyzer\DartParser;
use PHPUnit\Framework\TestCase;

final class DartParserTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/dart-parser-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
        file_put_contents($this->tempDir . '/pubspec.yaml', "name: the_singer\n");
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    public function test_emits_import_edge_for_local_package_import(): void
    {
        $this->createFile('lib/domain/repositories/song_repository.dart', "abstract class SongRepository {}\n");
        $file = $this->createFile('lib/presentation/bloc/library_bloc.dart', <<<'DART'
import 'package:the_singer/domain/repositories/song_repository.dart';

class LibraryBloc {}
DART);

        $result = $this->makeParser()->parse($file);

        self::assertCount(1, $result['nodes']);
        self::assertSame('dart:lib.presentation.bloc.library_bloc::module', $result['nodes'][0]->id());
        self::assertSame('bloc', $result['nodes'][0]->metadata()['kind']);
        self::assertSame('Application', $result['nodes'][0]->metadata()['layer']);

        $edgeTos = array_map(static fn($edge) => $edge->to(), $result['edges']);
        self::assertContains('dart:lib.domain.repositories.song_repository::module', $edgeTos);
    }

    public function test_emits_http_call_edges_from_api_constants(): void
    {
        $this->createFile('lib/core/constants/api_constants.dart', <<<'DART'
class ApiConstants {
  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://localhost:8000/v1',
  );
  static const String userMe = '$baseUrl/users/me';
  static const String songs = '$baseUrl/songs';
}
DART);

        $file = $this->createFile('lib/data/datasources/user_remote_data_source.dart', <<<'DART'
import 'package:dio/dio.dart';
import 'package:the_singer/core/constants/api_constants.dart';

class UserRemoteDataSourceImpl {
  Future<void> load(Dio dio, String songId) async {
    await dio.get(ApiConstants.userMe);
    await dio.get('${ApiConstants.songs}/$songId/lyrics');
  }
}
DART);

        $result = $this->makeParser()->parse($file);
        $edgeTos = array_map(static fn($edge) => $edge->to(), $result['edges']);

        self::assertContains('http:GET:/v1/users/me', $edgeTos);
        self::assertContains('http:GET:/v1/songs/{*}/lyrics', $edgeTos);
    }

    public function test_collects_flutter_metadata_and_integrations(): void
    {
        mkdir($this->tempDir . '/android', 0777, true);
        mkdir($this->tempDir . '/ios', 0777, true);
        mkdir($this->tempDir . '/windows', 0777, true);

        $file = $this->createFile('lib/main.dart', <<<'DART'
import 'package:firebase_core/firebase_core.dart';
import 'package:purchases_flutter/purchases_flutter.dart';
import 'package:go_router/go_router.dart';

Future<void> main() async {}
DART);

        $result = $this->makeParser()->parse($file);
        $metadata = $result['nodes'][0]->metadata();

        self::assertSame('main', $metadata['kind']);
        self::assertSame('ui', $metadata['entrypoint_type']);
        self::assertSame(['android', 'ios', 'windows'], $metadata['platforms']);
        self::assertContains('firebase', $metadata['integrations']);
        self::assertContains('revenuecat', $metadata['integrations']);
        self::assertContains('go_router', $metadata['integrations']);
    }

    private function makeParser(): DartParser
    {
        return new DartParser(new DefaultNodeFactory(), $this->tempDir);
    }

    private function createFile(string $relativePath, string $content): string
    {
        $path = $this->tempDir . '/' . str_replace('\\', '/', $relativePath);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($path, $content);
        return $path;
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }

        rmdir($dir);
    }
}
