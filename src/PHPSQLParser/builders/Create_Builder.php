<?php

declare (strict_types=1);
/**
 * CreateBuilder.php
 *
 * Builds the CREATE statement
 *
 * PHP version 5
 *
 * LICENSE:
 * Copyright (c) 2010-2014 Justin Swanhart and André Rothe
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products
 *    derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
 * IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
 * OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @author    André Rothe <andre.rothe@phosco.info>
 * @copyright 2010-2014 Justin Swanhart and André Rothe
 * @license   http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 * @version   SVN: $Id$
 *
 */
namespace Phpsql_Parser\builders;

use Phpsql_Parser\utils\Expression_Type;
/**
 * This class implements the builder for the [CREATE] part. You can overwrite
 * all functions to achieve another handling.
 *
 * @author  André Rothe <andre.rothe@phosco.info>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *
 */
class Create_Builder implements Builder
{
    protected function build_create_table(array $parsed)
    {
        $builder = new Create_Table_Builder();
        return $builder->build($parsed);
    }
    protected function build_create_index(array $parsed)
    {
        $builder = new Create_Index_Builder();
        return $builder->build($parsed);
    }
    protected function build_sub_tree(array $parsed)
    {
        $builder = new Sub_Tree_Builder();
        return $builder->build($parsed);
    }
    public function build(array $parsed)
    {
        $create = $parsed['CREATE'];
        $sql = $this->build_sub_tree($create);
        if ($create['expr_type'] === Expression_Type::TABLE || $create['expr_type'] === Expression_Type::TEMPORARY_TABLE) {
            $sql .= ' ' . $this->build_create_table($parsed['TABLE']);
        }
        if ($create['expr_type'] === Expression_Type::INDEX) {
            $sql .= ' ' . $this->build_create_index($parsed['INDEX']);
        }
        // TODO: add more expr_types here (like VIEW), if available in parser output
        return 'CREATE ' . $sql;
    }
}