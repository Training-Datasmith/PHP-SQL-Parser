<?php

declare (strict_types=1);
/**
 * IndexProcessor.php
 *
 * This file implements the processor for the INDEX statements.
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
namespace Phpsql_Parser\processors;

use Phpsql_Parser\utils\Expression_Type;
/**
 * This class processes the INDEX statements.
 *
 * @author  André Rothe <andre.rothe@phosco.info>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *
 */
class Index_Processor extends Abstract_Processor
{
    protected function get_reserved_type($token)
    {
        return ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $token];
    }
    protected function get_constant_type($token)
    {
        return ['expr_type' => Expression_Type::CONSTANT, 'base_expr' => $token];
    }
    protected function get_operator_type($token)
    {
        return ['expr_type' => Expression_Type::OPERATOR, 'base_expr' => $token];
    }
    protected function process_index_column_list($parsed)
    {
        $processor = new Index_Column_List_Processor($this->options);
        return $processor->process($parsed);
    }
    public function process($tokens)
    {
        $curr_category = 'INDEX_NAME';
        $result = ['base_expr' => false, 'name' => false, 'no_quotes' => false, 'index-type' => false, 'on' => false, 'options' => []];
        $expr = [];
        $base_expr = '';
        $skip = 0;
        foreach ($tokens as $token) {
            $trim = trim($token);
            $base_expr .= $token;
            if ($skip > 0) {
                $skip--;
                continue;
            }
            if ($skip < 0) {
                break;
            }
            if ($trim === '') {
                continue;
            }
            $upper = strtoupper($trim);
            switch ($upper) {
                case 'USING':
                    if ($prev_category === 'CREATE_DEF') {
                        $expr[] = $this->get_reserved_type($trim);
                        $curr_category = 'TYPE_OPTION';
                        continue 2;
                    }
                    if ($prev_category === 'TYPE_DEF') {
                        $expr[] = $this->get_reserved_type($trim);
                        $curr_category = 'INDEX_TYPE';
                        continue 2;
                    }
                    // else ?
                    break;
                case 'KEY_BLOCK_SIZE':
                    if ($prev_category === 'CREATE_DEF') {
                        $expr[] = $this->get_reserved_type($trim);
                        $curr_category = 'INDEX_OPTION';
                        continue 2;
                    }
                    // else ?
                    break;
                case 'WITH':
                    if ($prev_category === 'CREATE_DEF') {
                        $expr[] = $this->get_reserved_type($trim);
                        $curr_category = 'INDEX_PARSER';
                        continue 2;
                    }
                    // else ?
                    break;
                case 'PARSER':
                    if ($curr_category === 'INDEX_PARSER') {
                        $expr[] = $this->get_reserved_type($trim);
                        continue 2;
                    }
                    // else ?
                    break;
                case 'COMMENT':
                    if ($prev_category === 'CREATE_DEF') {
                        $expr[] = $this->get_reserved_type($trim);
                        $curr_category = 'INDEX_COMMENT';
                        continue 2;
                    }
                    // else ?
                    break;
                case 'ALGORITHM':
                case 'LOCK':
                    if ($prev_category === 'CREATE_DEF') {
                        $expr[] = $this->get_reserved_type($trim);
                        $curr_category = $upper . '_OPTION';
                        continue 2;
                    }
                    // else ?
                    break;
                case '=':
                    // the optional operator
                    if (substr($curr_category, -7, 7) === '_OPTION') {
                        $expr[] = $this->get_operator_type($trim);
                        continue 2;
                        // don't change the category
                    }
                    // else ?
                    break;
                case 'ON':
                    if ($prev_category === 'CREATE_DEF' || $prev_category === 'TYPE_DEF') {
                        $expr[] = $this->get_reserved_type($trim);
                        $curr_category = 'TABLE_DEF';
                        continue 2;
                    }
                    // else ?
                    break;
                default:
                    switch ($curr_category) {
                        case 'COLUMN_DEF':
                            if ($upper[0] === '(' && substr($upper, -1) === ')') {
                                $cols = $this->process_index_column_list($this->remove_parenthesis_from_start($trim));
                                $result['on']['base_expr'] .= $base_expr;
                                $result['on']['sub_tree'] = ['expr_type' => Expression_Type::COLUMN_LIST, 'base_expr' => $trim, 'sub_tree' => $cols];
                            }
                            $expr = [];
                            $base_expr = '';
                            $curr_category = 'CREATE_DEF';
                            break;
                        case 'TABLE_DEF':
                            // the table name
                            $expr[] = $this->get_constant_type($trim);
                            // TODO: the base_expr should contain the column-def too
                            $result['on'] = ['expr_type' => Expression_Type::TABLE, 'base_expr' => $base_expr, 'name' => $trim, 'no_quotes' => $this->revoke_quotation($trim), 'sub_tree' => false];
                            $expr = [];
                            $base_expr = '';
                            $curr_category = 'COLUMN_DEF';
                            continue 3;
                        case 'INDEX_NAME':
                            $result['base_expr'] = $result['name'] = $trim;
                            $result['no_quotes'] = $this->revoke_quotation($trim);
                            $expr = [];
                            $base_expr = '';
                            $curr_category = 'TYPE_DEF';
                            break;
                        case 'INDEX_PARSER':
                            // the parser name
                            $expr[] = $this->get_constant_type($trim);
                            $result['options'][] = ['expr_type' => Expression_Type::INDEX_PARSER, 'base_expr' => trim($base_expr), 'sub_tree' => $expr];
                            $expr = [];
                            $base_expr = '';
                            $curr_category = 'CREATE_DEF';
                            break;
                        case 'INDEX_COMMENT':
                            // the index comment
                            $expr[] = $this->get_constant_type($trim);
                            $result['options'][] = ['expr_type' => Expression_Type::COMMENT, 'base_expr' => trim($base_expr), 'sub_tree' => $expr];
                            $expr = [];
                            $base_expr = '';
                            $curr_category = 'CREATE_DEF';
                            break;
                        case 'INDEX_OPTION':
                            // the key_block_size
                            $expr[] = $this->get_constant_type($trim);
                            $result['options'][] = ['expr_type' => Expression_Type::INDEX_SIZE, 'base_expr' => trim($base_expr), 'size' => $upper, 'sub_tree' => $expr];
                            $expr = [];
                            $base_expr = '';
                            $curr_category = 'CREATE_DEF';
                            break;
                        case 'INDEX_TYPE':
                        case 'TYPE_OPTION':
                            // BTREE or HASH
                            $expr[] = $this->get_reserved_type($trim);
                            if ($curr_category === 'INDEX_TYPE') {
                                $result['index-type'] = ['expr_type' => Expression_Type::INDEX_TYPE, 'base_expr' => trim($base_expr), 'using' => $upper, 'sub_tree' => $expr];
                            } else {
                                $result['options'][] = ['expr_type' => Expression_Type::INDEX_TYPE, 'base_expr' => trim($base_expr), 'using' => $upper, 'sub_tree' => $expr];
                            }
                            $expr = [];
                            $base_expr = '';
                            $curr_category = 'CREATE_DEF';
                            break;
                        case 'LOCK_OPTION':
                            // DEFAULT|NONE|SHARED|EXCLUSIVE
                            $expr[] = $this->get_reserved_type($trim);
                            $result['options'][] = ['expr_type' => Expression_Type::INDEX_LOCK, 'base_expr' => trim($base_expr), 'lock' => $upper, 'sub_tree' => $expr];
                            $expr = [];
                            $base_expr = '';
                            $curr_category = 'CREATE_DEF';
                            break;
                        case 'ALGORITHM_OPTION':
                            // DEFAULT|INPLACE|COPY
                            $expr[] = $this->get_reserved_type($trim);
                            $result['options'][] = ['expr_type' => Expression_Type::INDEX_ALGORITHM, 'base_expr' => trim($base_expr), 'algorithm' => $upper, 'sub_tree' => $expr];
                            $expr = [];
                            $base_expr = '';
                            $curr_category = 'CREATE_DEF';
                            break;
                        default:
                            break;
                    }
                    break;
            }
            $prev_category = $curr_category;
            $curr_category = '';
        }
        if ($result['options'] === []) {
            $result['options'] = false;
        }
        return $result;
    }
}