<?php

declare (strict_types=1);
/**
 * ColumnTypeBuilder.php
 *
 * Builds the column type statement part of CREATE TABLE.
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
use Phpsql_Parser\utils\Expression_Type;
/**
 * This class implements the builder for the column type statement part of CREATE TABLE.
 * You can overwrite all functions to achieve another handling.
 *
 * @author  André Rothe <andre.rothe@phosco.info>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *
 */
class Column_Type_Builder implements Builder
{
    protected function build_column_type_bracket_expression(array $parsed)
    {
        $builder = new Column_Type_Bracket_Expression_Builder();
        return $builder->build($parsed);
    }
    protected function build_reserved(array $parsed)
    {
        $builder = new Reserved_Builder();
        return $builder->build($parsed);
    }
    protected function build_data_type(array $parsed)
    {
        $builder = new Data_Type_Builder();
        return $builder->build($parsed);
    }
    protected function build_default_value(array $parsed)
    {
        $builder = new Default_Value_Builder();
        return $builder->build($parsed);
    }
    protected function build_character_set(array $parsed)
    {
        if ($parsed['expr_type'] !== Expression_Type::CHARSET) {
            return '';
        }
        return $parsed['base_expr'];
    }
    protected function build_collation(array $parsed)
    {
        if ($parsed['expr_type'] !== Expression_Type::COLLATE) {
            return '';
        }
        return $parsed['base_expr'];
    }
    protected function build_comment(array $parsed)
    {
        if ($parsed['expr_type'] !== Expression_Type::COMMENT) {
            return '';
        }
        return $parsed['base_expr'];
    }
    public function build(array $parsed)
    {
        if ($parsed['expr_type'] !== Expression_Type::COLUMN_TYPE) {
            return '';
        }
        $sql = '';
        foreach ($parsed['sub_tree'] as $k => $v) {
            $len = strlen($sql);
            $sql .= $this->build_data_type($v);
            $sql .= $this->build_column_type_bracket_expression($v);
            $sql .= $this->build_reserved($v);
            $sql .= $this->build_default_value($v);
            $sql .= $this->build_character_set($v);
            $sql .= $this->build_collation($v);
            $sql .= $this->build_comment($v);
            if ($len == strlen($sql)) {
                throw new Unable_To_Create_Sql_Exception('CREATE TABLE column-type subtree', $k, $v, 'expr_type');
            }
            $sql .= ' ';
        }
        return substr($sql, 0, -1);
    }
}