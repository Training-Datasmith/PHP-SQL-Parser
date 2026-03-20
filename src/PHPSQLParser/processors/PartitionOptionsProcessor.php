<?php

declare (strict_types=1);
/**
 * PartitionOptionsProcessor.php
 *
 * This file implements the processor for the PARTITION BY statements
 * within CREATE TABLE.
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
 * This class processes the PARTITION BY statements within CREATE TABLE.
 *
 * @author  André Rothe <andre.rothe@phosco.info>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *
 */
class Partition_Options_Processor extends Abstract_Processor
{
    protected function process_expression_list($unparsed)
    {
        $processor = new Expression_List_Processor($this->options);
        $expr = $this->remove_parenthesis_from_start($unparsed);
        $expr = $this->split_sql_into_tokens($expr);
        return $processor->process($expr);
    }
    protected function process_column_list($unparsed)
    {
        $processor = new Column_List_Processor($this->options);
        $expr = $this->remove_parenthesis_from_start($unparsed);
        return $processor->process($expr);
    }
    protected function process_partition_definition($unparsed)
    {
        $processor = new Partition_Definition_Processor($this->options);
        $expr = $this->remove_parenthesis_from_start($unparsed);
        $expr = $this->split_sql_into_tokens($expr);
        return $processor->process($expr);
    }
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
    protected function get_bracket_expression_type($token)
    {
        return ['expr_type' => Expression_Type::BRACKET_EXPRESSION, 'base_expr' => $token, 'sub_tree' => false];
    }
    public function process($tokens)
    {
        $result = ['partition-options' => [], 'last-parsed' => false];
        $prev_category = '';
        $curr_category = '';
        $parsed = [];
        $expr = [];
        $base_expr = '';
        $skip = 0;
        foreach ($tokens as $token_key => $token) {
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
                case 'PARTITION':
                    $curr_category = $upper;
                    $expr[] = $this->get_reserved_type($trim);
                    $parsed[] = ['expr_type' => Expression_Type::PARTITION, 'base_expr' => trim($base_expr), 'sub_tree' => false];
                    break;
                case 'SUBPARTITION':
                    $curr_category = $upper;
                    $expr[] = $this->get_reserved_type($trim);
                    $parsed[] = ['expr_type' => Expression_Type::SUBPARTITION, 'base_expr' => trim($base_expr), 'sub_tree' => false];
                    break;
                case 'BY':
                    if ($prev_category === 'PARTITION' || $prev_category === 'SUBPARTITION') {
                        $expr[] = $this->get_reserved_type($trim);
                        continue 2;
                    }
                    break;
                case 'PARTITIONS':
                case 'SUBPARTITIONS':
                    $curr_category = 'PARTITION_NUM';
                    $expr = ['expr_type' => constant('PHPSQLParser\utils\ExpressionType::' . substr($upper, 0, -1) . '_COUNT'), 'base_expr' => false, 'sub_tree' => [$this->get_reserved_type($trim)], 'storage' => substr($base_expr, 0, -strlen($token))];
                    $base_expr = $token;
                    continue 2;
                case 'LINEAR':
                    // followed by HASH or KEY
                    $curr_category = $upper;
                    $expr[] = $this->get_reserved_type($trim);
                    continue 2;
                case 'HASH':
                case 'KEY':
                    $expr[] = ['expr_type' => constant('PHPSQLParser\utils\ExpressionType::' . $prev_category . '_' . $upper), 'base_expr' => false, 'linear' => $curr_category === 'LINEAR', 'sub_tree' => false, 'storage' => substr($base_expr, 0, -strlen($token))];
                    $last = array_pop($parsed);
                    $last['by'] = trim($curr_category . ' ' . $upper);
                    // $currCategory will be empty or LINEAR!
                    $last['sub_tree'] = $expr;
                    $parsed[] = $last;
                    $base_expr = $token;
                    $expr = [$this->get_reserved_type($trim)];
                    $curr_category = $upper;
                    continue 2;
                case 'ALGORITHM':
                    if ($curr_category === 'KEY') {
                        $expr[] = ['expr_type' => constant('PHPSQLParser\utils\ExpressionType::' . $prev_category . '_KEY_ALGORITHM'), 'base_expr' => false, 'sub_tree' => false, 'storage' => substr($base_expr, 0, -strlen($token))];
                        $last = array_pop($parsed);
                        $subtree = array_pop($last['sub_tree']);
                        $subtree['sub_tree'] = $expr;
                        $last['sub_tree'][] = $subtree;
                        $parsed[] = $last;
                        unset($subtree);
                        unset($last);
                        $base_expr = $token;
                        $expr = [$this->get_reserved_type($trim)];
                        $curr_category = $upper;
                        continue 2;
                    }
                    break;
                case 'RANGE':
                case 'LIST':
                    $expr[] = ['expr_type' => constant('PHPSQLParser\utils\ExpressionType::PARTITION_' . $upper), 'base_expr' => false, 'sub_tree' => false, 'storage' => substr($base_expr, 0, -strlen($token))];
                    $last = array_pop($parsed);
                    $last['by'] = $upper;
                    $last['sub_tree'] = $expr;
                    $parsed[] = $last;
                    unset($last);
                    $base_expr = $token;
                    $expr = [$this->get_reserved_type($trim)];
                    $curr_category = $upper . '_EXPR';
                    continue 2;
                case 'COLUMNS':
                    if ($curr_category === 'RANGE_EXPR' || $curr_category === 'LIST_EXPR') {
                        $expr[] = $this->get_reserved_type($trim);
                        $curr_category = substr($curr_category, 0, -4) . $upper;
                        continue 2;
                    }
                    break;
                case '=':
                    if ($curr_category === 'ALGORITHM') {
                        // between ALGORITHM and a constant
                        $expr[] = $this->get_operator_type($trim);
                        continue 2;
                    }
                    break;
                default:
                    switch ($curr_category) {
                        case 'PARTITION_NUM':
                            // the number behind PARTITIONS or SUBPARTITIONS
                            $expr['base_expr'] = trim($base_expr);
                            $expr['sub_tree'][] = $this->get_constant_type($trim);
                            $base_expr = $expr['storage'] . $base_expr;
                            unset($expr['storage']);
                            $last = array_pop($parsed);
                            $last['count'] = $trim;
                            $last['sub_tree'][] = $expr;
                            $last['base_expr'] .= $base_expr;
                            $parsed[] = $last;
                            unset($last);
                            $expr = [];
                            $base_expr = '';
                            $curr_category = $prev_category;
                            break;
                        case 'ALGORITHM':
                            // the number of the algorithm
                            $expr[] = $this->get_constant_type($trim);
                            $last = array_pop($parsed);
                            $subtree = array_pop($last['sub_tree']);
                            $key = array_pop($subtree['sub_tree']);
                            $key['sub_tree'] = $expr;
                            $key['base_expr'] = trim($base_expr);
                            $base_expr = $key['storage'] . $base_expr;
                            unset($key['storage']);
                            $subtree['sub_tree'][] = $key;
                            unset($key);
                            $expr = $subtree['sub_tree'];
                            $subtree['sub_tree'] = false;
                            $subtree['algorithm'] = $trim;
                            $last['sub_tree'][] = $subtree;
                            unset($subtree);
                            $parsed[] = $last;
                            unset($last);
                            $curr_category = 'KEY';
                            continue 3;
                        case 'LIST_EXPR':
                        case 'RANGE_EXPR':
                        case 'HASH':
                            // parenthesis around an expression
                            $last = $this->get_bracket_expression_type($trim);
                            $res = $this->process_expression_list($trim);
                            $last['sub_tree'] = empty($res) ? false : $res;
                            $expr[] = $last;
                            $last = array_pop($parsed);
                            $subtree = array_pop($last['sub_tree']);
                            $subtree['base_expr'] = $base_expr;
                            $subtree['sub_tree'] = $expr;
                            $base_expr = $subtree['storage'] . $base_expr;
                            unset($subtree['storage']);
                            $last['sub_tree'][] = $subtree;
                            $last['base_expr'] = trim($base_expr);
                            $parsed[] = $last;
                            unset($last);
                            unset($subtree);
                            $expr = [];
                            $base_expr = '';
                            $curr_category = $prev_category;
                            break;
                        case 'LIST_COLUMNS':
                        case 'RANGE_COLUMNS':
                        case 'KEY':
                            // the columnlist
                            $expr[] = ['expr_type' => Expression_Type::COLUMN_LIST, 'base_expr' => $trim, 'sub_tree' => $this->process_column_list($trim)];
                            $last = array_pop($parsed);
                            $subtree = array_pop($last['sub_tree']);
                            $subtree['base_expr'] = $base_expr;
                            $subtree['sub_tree'] = $expr;
                            $base_expr = $subtree['storage'] . $base_expr;
                            unset($subtree['storage']);
                            $last['sub_tree'][] = $subtree;
                            $last['base_expr'] = trim($base_expr);
                            $parsed[] = $last;
                            unset($last);
                            unset($subtree);
                            $expr = [];
                            $base_expr = '';
                            $curr_category = $prev_category;
                            break;
                        case '':
                            if ($prev_category === 'PARTITION' || $prev_category === 'SUBPARTITION') {
                                if ($upper[0] === '(' && substr($upper, -1) === ')') {
                                    // last part to process, it is only one token!
                                    $last = $this->get_bracket_expression_type($trim);
                                    $last['sub_tree'] = $this->process_partition_definition($trim);
                                    $parsed[] = $last;
                                    break;
                                }
                            }
                            // else ?
                            break;
                        default:
                            break;
                    }
                    break;
            }
            $prev_category = $curr_category;
            $curr_category = '';
        }
        $result['partition-options'] = $parsed;
        if ($result['last-parsed'] === false) {
            $result['last-parsed'] = $token_key;
        }
        return $result;
    }
}