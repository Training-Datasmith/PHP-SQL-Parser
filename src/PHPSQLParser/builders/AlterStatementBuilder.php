<?php

declare(strict_types=1);

namespace PHPSQLParser\builders;

class AlterStatementBuilder implements Builder
{
    protected function buildSubTree(array $parsed)
    {
        $builder = new SubTreeBuilder();
        return $builder->build($parsed);
    }

    private function buildAlter(array $parsed)
    {
        $builder = new AlterBuilder();
        return $builder->build($parsed);
    }

    public function build(array $parsed)
    {
        $alter = $parsed['ALTER'];

        return $this->buildAlter($alter);
    }
}
