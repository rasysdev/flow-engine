<?php

namespace FlowEngine\Domain\Contracts;

use FlowEngine\Domain\Flow\Node;

interface InputIntrospector
{
    /**
     * @return array{
     *   inputs: array<int, array{name:string,type:string,required:bool,default:mixed}>,
     *   return: string
     * }
     */
    public function introspect(Node $node): array;
}
