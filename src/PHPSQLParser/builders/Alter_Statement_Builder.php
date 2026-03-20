<?php

declare (strict_types=1);
namespace Phpsql_Parser\builders;

class Alter_Statement_Builder implements Builder
{
    protected function build_sub_tree(array $parsed)
    {
        $builder = new Sub_Tree_Builder();
        return $builder->build($parsed);
    }
    private function build_alter(array $parsed)
    {
        $builder = new Alter_Builder();
        return $builder->build($parsed);
    }
    public function build(array $parsed)
    {
        $alter = $parsed['ALTER'];
        return $this->build_alter($alter);
    }
}