<?php

declare (strict_types=1);
/**
 * CreateDefinitionProcessor.php
 *
 * This file implements the processor for the create definition within the TABLE statements.
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
 * This class processes the create definition of the TABLE statements.
 *
 * @author  André Rothe <andre.rothe@phosco.info>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *
 */
class Create_Definition_Processor extends Abstract_Processor
{
    protected function process_expression_list($parsed)
    {
        $processor = new Expression_List_Processor($this->options);
        return $processor->process($parsed);
    }
    protected function process_index_column_list($parsed)
    {
        $processor = new Index_Column_List_Processor($this->options);
        return $processor->process($parsed);
    }
    protected function process_column_definition($parsed)
    {
        $processor = new Column_Definition_Processor($this->options);
        return $processor->process($parsed);
    }
    protected function process_reference_definition($parsed)
    {
        $processor = new Reference_Definition_Processor($this->options);
        return $processor->process($parsed);
    }
    protected function correct_expression_type(&$expr)
    {
        $type = Expression_Type::EXPRESSION;
        if (!isset($expr[0]) || !isset($expr[0]['expr_type'])) {
            return $type;
        }
        // replace the constraint type with a more descriptive one
        switch ($expr[0]['expr_type']) {
            case Expression_Type::CONSTRAINT:
                $type = $expr[1]['expr_type'];
                $expr[1]['expr_type'] = Expression_Type::RESERVED;
                break;
            case Expression_Type::COLREF:
                $type = Expression_Type::COLDEF;
                break;
            default:
                $type = $expr[0]['expr_type'];
                $expr[0]['expr_type'] = Expression_Type::RESERVED;
                break;
        }
        return $type;
    }
    public function process($tokens)
    {
        $base_expr = '';
        $prev_category = '';
        $curr_category = '';
        $expr = [];
        $result = [];
        $skip = 0;
        foreach ($tokens as $k => $token) {
            $trim = trim($token);
            $base_expr .= $token;
            if ($skip !== 0) {
                $skip--;
                continue;
            }
            if ($trim === '') {
                continue;
            }
            $upper = strtoupper($trim);
            switch ($upper) {
                case 'CONSTRAINT':
                    $expr[] = ['expr_type' => Expression_Type::CONSTRAINT, 'base_expr' => $trim, 'sub_tree' => false];
                    $curr_category = $prev_category = $upper;
                    continue 2;
                case 'LIKE':
                    $expr[] = ['expr_type' => Expression_Type::LIKE, 'base_expr' => $trim];
                    $curr_category = $prev_category = $upper;
                    continue 2;
                case 'FOREIGN':
                    if ($prev_category === '' || $prev_category === 'CONSTRAINT') {
                        $expr[] = ['expr_type' => Expression_Type::FOREIGN_KEY, 'base_expr' => $trim];
                        $curr_category = $upper;
                        continue 2;
                    }
                    // else ?
                    break;
                case 'PRIMARY':
                    if ($prev_category === '' || $prev_category === 'CONSTRAINT') {
                        // next one is KEY
                        $expr[] = ['expr_type' => Expression_Type::PRIMARY_KEY, 'base_expr' => $trim];
                        $curr_category = $upper;
                        continue 2;
                    }
                    // else ?
                    break;
                case 'UNIQUE':
                    if ($prev_category === '' || $prev_category === 'CONSTRAINT' || $prev_category === 'INDEX_COL_LIST') {
                        // next one is KEY
                        $expr[] = ['expr_type' => Expression_Type::UNIQUE_IDX, 'base_expr' => $trim];
                        $curr_category = $upper;
                        continue 2;
                    }
                    // else ?
                    break;
                case 'KEY':
                    // the next one is an index name
                    if ($curr_category === 'PRIMARY' || $curr_category === 'FOREIGN' || $curr_category === 'UNIQUE') {
                        $expr[] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                        continue 2;
                    }
                    $expr[] = ['expr_type' => Expression_Type::INDEX, 'base_expr' => $trim];
                    $curr_category = $upper;
                    continue 2;
                case 'CHECK':
                    $expr[] = ['expr_type' => Expression_Type::CHECK, 'base_expr' => $trim];
                    $curr_category = $upper;
                    continue 2;
                case 'INDEX':
                    if ($curr_category === 'UNIQUE' || $curr_category === 'FULLTEXT' || $curr_category === 'SPATIAL') {
                        $expr[] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                        continue 2;
                    }
                    $expr[] = ['expr_type' => Expression_Type::INDEX, 'base_expr' => $trim];
                    $curr_category = $upper;
                    continue 2;
                case 'FULLTEXT':
                    $expr[] = ['expr_type' => Expression_Type::FULLTEXT_IDX, 'base_expr' => $trim];
                    $curr_category = $prev_category = $upper;
                    continue 2;
                case 'SPATIAL':
                    $expr[] = ['expr_type' => Expression_Type::SPATIAL_IDX, 'base_expr' => $trim];
                    $curr_category = $prev_category = $upper;
                    continue 2;
                case 'WITH':
                    // starts an index option
                    if ($curr_category === 'INDEX_COL_LIST') {
                        $option = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                        $expr[] = ['expr_type' => Expression_Type::INDEX_PARSER, 'base_expr' => substr($base_expr, 0, -strlen($token)), 'sub_tree' => [$option]];
                        $base_expr = $token;
                        $curr_category = 'INDEX_PARSER';
                        continue 2;
                    }
                    break;
                case 'KEY_BLOCK_SIZE':
                    // starts an index option
                    if ($curr_category === 'INDEX_COL_LIST') {
                        $option = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                        $expr[] = ['expr_type' => Expression_Type::INDEX_SIZE, 'base_expr' => substr($base_expr, 0, -strlen($token)), 'sub_tree' => [$option]];
                        $base_expr = $token;
                        $curr_category = 'INDEX_SIZE';
                        continue 2;
                    }
                    break;
                case 'USING':
                    // starts an index option
                    if ($curr_category === 'INDEX_COL_LIST' || $curr_category === 'PRIMARY') {
                        $option = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                        $expr[] = ['base_expr' => substr($base_expr, 0, -strlen($token)), 'trim' => $trim, 'category' => $curr_category, 'sub_tree' => [$option]];
                        $base_expr = $token;
                        $curr_category = 'INDEX_TYPE';
                        continue 2;
                    }
                    // else ?
                    break;
                case 'REFERENCES':
                    if ($curr_category === 'INDEX_COL_LIST' && $prev_category === 'FOREIGN') {
                        $refs = $this->process_reference_definition(array_slice($tokens, $k - 1, null, true));
                        $skip = $refs['till'] - $k;
                        unset($refs['till']);
                        $expr[] = $refs;
                        $curr_category = $upper;
                    }
                    // else ?
                    break;
                case 'BTREE':
                case 'HASH':
                    if ($curr_category === 'INDEX_TYPE') {
                        $last = array_pop($expr);
                        $last['sub_tree'][] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                        $expr[] = ['expr_type' => Expression_Type::INDEX_TYPE, 'base_expr' => $base_expr, 'sub_tree' => $last['sub_tree']];
                        $base_expr = $last['base_expr'] . $base_expr;
                        // FIXME: it could be wrong for index_type within index_option
                        $curr_category = $last['category'];
                        continue 2;
                    }
                    // else ?
                    break;
                case '=':
                    if ($curr_category === 'INDEX_SIZE') {
                        // the optional character between KEY_BLOCK_SIZE and the numeric constant
                        $last = array_pop($expr);
                        $last['sub_tree'][] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                        $expr[] = $last;
                        continue 2;
                    }
                    break;
                case 'PARSER':
                    if ($curr_category === 'INDEX_PARSER') {
                        $last = array_pop($expr);
                        $last['sub_tree'][] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                        $expr[] = $last;
                        continue 2;
                    }
                    // else ?
                    break;
                case ',':
                    // this starts the next definition
                    $type = $this->correct_expression_type($expr);
                    $result['create-def'][] = ['expr_type' => $type, 'base_expr' => trim(substr($base_expr, 0, -strlen($token))), 'sub_tree' => $expr];
                    $base_expr = '';
                    $expr = [];
                    break;
                default:
                    switch ($curr_category) {
                        case 'LIKE':
                            // this is the tablename after LIKE
                            $expr[] = ['expr_type' => Expression_Type::TABLE, 'table' => $trim, 'base_expr' => $trim, 'no_quotes' => $this->revoke_quotation($trim)];
                            break;
                        case 'PRIMARY':
                            if ($upper[0] === '(' && substr($upper, -1) === ')') {
                                // the column list
                                $cols = $this->process_index_column_list($this->remove_parenthesis_from_start($trim));
                                $expr[] = ['expr_type' => Expression_Type::COLUMN_LIST, 'base_expr' => $trim, 'sub_tree' => $cols];
                                $prev_category = $curr_category;
                                $curr_category = 'INDEX_COL_LIST';
                                continue 3;
                            }
                            // else?
                            break;
                        case 'FOREIGN':
                            if ($upper[0] === '(' && substr($upper, -1) === ')') {
                                $cols = $this->process_index_column_list($this->remove_parenthesis_from_start($trim));
                                $expr[] = ['expr_type' => Expression_Type::COLUMN_LIST, 'base_expr' => $trim, 'sub_tree' => $cols];
                                $prev_category = $curr_category;
                                $curr_category = 'INDEX_COL_LIST';
                                continue 3;
                            }
                            // index name
                            $expr[] = ['expr_type' => Expression_Type::CONSTANT, 'base_expr' => $trim];
                            continue 3;
                        case 'KEY':
                        case 'UNIQUE':
                        case 'INDEX':
                            if ($upper[0] === '(' && substr($upper, -1) === ')') {
                                $cols = $this->process_index_column_list($this->remove_parenthesis_from_start($trim));
                                $expr[] = ['expr_type' => Expression_Type::COLUMN_LIST, 'base_expr' => $trim, 'sub_tree' => $cols];
                                $prev_category = $curr_category;
                                $curr_category = 'INDEX_COL_LIST';
                                continue 3;
                            }
                            // index name
                            $expr[] = ['expr_type' => Expression_Type::CONSTANT, 'base_expr' => $trim];
                            continue 3;
                        case 'CONSTRAINT':
                            // constraint name
                            $last = array_pop($expr);
                            $last['base_expr'] = $base_expr;
                            $last['sub_tree'] = ['expr_type' => Expression_Type::CONSTANT, 'base_expr' => $trim];
                            $expr[] = $last;
                            continue 3;
                        case 'INDEX_PARSER':
                            // index parser name
                            $last = array_pop($expr);
                            $last['sub_tree'][] = ['expr_type' => Expression_Type::CONSTANT, 'base_expr' => $trim];
                            $expr[] = ['expr_type' => Expression_Type::INDEX_PARSER, 'base_expr' => $base_expr, 'sub_tree' => $last['sub_tree']];
                            $base_expr = $last['base_expr'] . $base_expr;
                            $curr_category = 'INDEX_COL_LIST';
                            continue 3;
                        case 'INDEX_SIZE':
                            // index key block size numeric constant
                            $last = array_pop($expr);
                            $last['sub_tree'][] = ['expr_type' => Expression_Type::CONSTANT, 'base_expr' => $trim];
                            $expr[] = ['expr_type' => Expression_Type::INDEX_SIZE, 'base_expr' => $base_expr, 'sub_tree' => $last['sub_tree']];
                            $base_expr = $last['base_expr'] . $base_expr;
                            $curr_category = 'INDEX_COL_LIST';
                            continue 3;
                        case 'CHECK':
                            if ($upper[0] === '(' && substr($upper, -1) === ')') {
                                $parsed = $this->split_sql_into_tokens($this->remove_parenthesis_from_start($trim));
                                $parsed = $this->process_expression_list($parsed);
                                $expr[] = ['expr_type' => Expression_Type::BRACKET_EXPRESSION, 'base_expr' => $trim, 'sub_tree' => $parsed];
                            }
                            // else?
                            break;
                        case '':
                            // if the currCategory is empty, we have an unknown token,
                            // which is a column reference
                            $expr[] = ['expr_type' => Expression_Type::COLREF, 'base_expr' => $trim, 'no_quotes' => $this->revoke_quotation($trim)];
                            $curr_category = 'COLUMN_NAME';
                            continue 3;
                        case 'COLUMN_NAME':
                            // the column-definition
                            // it stops on a comma or on a parenthesis
                            $parsed = $this->process_column_definition(array_slice($tokens, $k, null, true));
                            $skip = $parsed['till'] - $k;
                            unset($parsed['till']);
                            $expr[] = $parsed;
                            $curr_category = '';
                            break;
                        default:
                            // ?
                            break;
                    }
                    break;
            }
            $prev_category = $curr_category;
            $curr_category = '';
        }
        $type = $this->correct_expression_type($expr);
        $result['create-def'][] = ['expr_type' => $type, 'base_expr' => trim($base_expr), 'sub_tree' => $expr];
        return $result;
    }
}