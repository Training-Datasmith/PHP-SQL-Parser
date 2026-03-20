<?php

declare (strict_types=1);
/**
 * HavingBracketExpressionBuilder.php
 *
 * Builds bracket expressions within the HAVING part.
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
 * This class implements the builder for bracket expressions within the HAVING part.
 * You can overwrite all functions to achieve another handling.
 *
 * @author  Ian Barker <ian@theorganicagency.com>
 * @author  André Rothe <andre.rothe@phosco.info>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *
 */
class Having_Bracket_Expression_Builder extends Where_Bracket_Expression_Builder
{
    protected function build_having_expression(array $parsed)
    {
        $builder = new Having_Expression_Builder();
        return $builder->build($parsed);
    }
    public function build(array $parsed)
    {
        if ($parsed['expr_type'] !== Expression_Type::BRACKET_EXPRESSION) {
            return '';
        }
        $sql = '';
        foreach ($parsed['sub_tree'] as $k => $v) {
            $len = strlen($sql);
            $sql .= $this->build_col_ref($v);
            $sql .= $this->build_constant($v);
            $sql .= $this->build_operator($v);
            $sql .= $this->build_in_list($v);
            $sql .= $this->build_function($v);
            $sql .= $this->build_having_expression($v);
            $sql .= $this->build($v);
            $sql .= $this->build_user_variable($v);
            if ($len == strlen($sql)) {
                throw new Unable_To_Create_Sql_Exception('HAVING expression subtree', $k, $v, 'expr_type');
            }
            $sql .= ' ';
        }
        return '(' . substr($sql, 0, -1) . ')';
    }
}