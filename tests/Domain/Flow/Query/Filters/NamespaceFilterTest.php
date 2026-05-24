<?php

namespace Tests\Domain\Flow\Query\Filters;

use FlowEngine\Domain\Flow\Node;
use FlowEngine\Domain\Flow\NodeCollection;
use FlowEngine\Domain\Flow\Query\Filters\NamespaceFilter;
use PHPUnit\Framework\TestCase;

final class NamespaceFilterTest extends TestCase
{
    public function test_filters_by_exact_namespace(): void
    {
        $nodes = new NodeCollection([
            new Node('App\Services\UserService', 'create', __FILE__, 1),
            new Node('App\Controllers\UserController', 'index', __FILE__, 2),
            new Node('App\Services\AuthService', 'login', __FILE__, 3),
        ]);

        $filter = new NamespaceFilter('App\Services');
        $result = $filter->apply($nodes);

        $this->assertCount(2, $result->all());
    }

    public function test_filters_by_nested_namespace(): void
    {
        $nodes = new NodeCollection([
            new Node('App\Services\Auth\LoginService', 'handle', __FILE__, 1),
            new Node('App\Services\Auth\LogoutService', 'handle', __FILE__, 2),
            new Node('App\Services\UserService', 'create', __FILE__, 3),
        ]);

        $filter = new NamespaceFilter('App\Services\Auth');
        $result = $filter->apply($nodes);

        $this->assertCount(2, $result->all());
    }

    public function test_handles_trailing_backslash(): void
    {
        $nodes = new NodeCollection([
            new Node('App\Services\UserService', 'create', __FILE__, 1),
        ]);

        $filter = new NamespaceFilter('App\Services\\'); // com trailing \
        $result = $filter->apply($nodes);

        $this->assertCount(1, $result->all());
    }

    public function test_matches_exact_class_name(): void
    {
        $nodes = new NodeCollection([
            new Node('App\Services', 'helper', __FILE__, 1),
            new Node('App\Services\UserService', 'create', __FILE__, 2),
        ]);

        $filter = new NamespaceFilter('App\Services');
        $result = $filter->apply($nodes);

        $this->assertCount(2, $result->all());
    }
}
