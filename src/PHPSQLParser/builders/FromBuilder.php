<?php

declare (strict_types=1);
/**
 * FromBuilder.php
 *
 * Builds the FROM statement
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

use Phpsql_Parser\exceptions\Unable_To_Create_Sql_Exception;
/**
 * This class implements the builder for the [FROM] part. You can overwrite
 * all functions to achieve another handling.
 *
 * @author  André Rothe <andre.rothe@phosco.info>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *
 */
class From_Builder implements Builder
{
    protected function build_table(array $parsed, $key)
    {
        $builder = new Table_Builder();
        return $builder->build($parsed, $key);
    }
    protected function build_table_expression(array $parsed, $key)
    {
        $builder = new Table_Expression_Builder();
        return $builder->build($parsed, $key);
    }
    protected function build_sub_query(array $parsed, $key)
    {
        $builder = new Sub_Query_Builder();
        return $builder->build($parsed, $key);
    }
    public function build(array $parsed)
    {
        $sql = '';
        if (array_key_exists('UNION ALL', $parsed) || array_key_exists('UNION', $parsed)) {
            foreach ($parsed as $union_type => $outer_v) {
                $first = true;
                foreach ($outer_v as $item) {
                    if (!$first) {
                        $sql .= " {$union_type} ";
                    } else {
                        $first = false;
                    }
                    $select_builder = new Select_Statement_Builder();
                    $len = strlen($sql);
                    $sql .= $select_builder->build($item);
                    if ($len === strlen($sql)) {
                        throw new Unable_To_Create_Sql_Exception('FROM', $union_type, $outer_v, 'expr_type');
                    }
                }
            }
        } else {
            foreach ($parsed as $k => $v) {
                $len = strlen($sql);
                $sql .= $this->build_table($v, $k);
                $sql .= $this->build_table_expression($v, $k);
                $sql .= $this->build_subquery($v, $k);
                if ($len == strlen($sql)) {
                    throw new Unable_To_Create_Sql_Exception('FROM', $k, $v, 'expr_type');
                }
            }
        }
        return 'FROM ' . $sql;
    }
}