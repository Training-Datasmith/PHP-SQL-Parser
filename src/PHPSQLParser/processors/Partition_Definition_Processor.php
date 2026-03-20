<?php

declare (strict_types=1);
/**
 * PartitionDefinitionProcessor.php
 *
 * This file implements the processor for the PARTITION statements
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
 * This class processes the PARTITION statements within CREATE TABLE.
 *
 * @author  André Rothe <andre.rothe@phosco.info>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *
 */
class Partition_Definition_Processor extends Abstract_Processor
{
    protected function process_expression_list($unparsed)
    {
        $processor = new Expression_List_Processor($this->options);
        $expr = $this->remove_parenthesis_from_start($unparsed);
        $expr = $this->split_sql_into_tokens($expr);
        return $processor->process($expr);
    }
    protected function process_subpartition_definition($unparsed)
    {
        $processor = new Subpartition_Definition_Processor($this->options);
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
        $result = [];
        $prev_category = '';
        $curr_category = '';
        $parsed = [];
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
                case 'PARTITION':
                    if ($curr_category === '') {
                        $expr[] = $this->get_reserved_type($trim);
                        $parsed = ['expr_type' => Expression_Type::PARTITION_DEF, 'base_expr' => trim($base_expr), 'sub_tree' => false];
                        $curr_category = $upper;
                        continue 2;
                    }
                    // else ?
                    break;
                case 'VALUES':
                    if ($prev_category === 'PARTITION') {
                        $expr[] = ['expr_type' => Expression_Type::PARTITION_VALUES, 'base_expr' => false, 'sub_tree' => false, 'storage' => substr($base_expr, 0, -strlen($token))];
                        $parsed['sub_tree'] = $expr;
                        $base_expr = $token;
                        $expr = [$this->get_reserved_type($trim)];
                        $curr_category = $upper;
                        continue 2;
                    }
                    // else ?
                    break;
                case 'LESS':
                case 'THAN':
                case 'IN':
                    if ($curr_category === 'VALUES') {
                        $expr[] = $this->get_reserved_type($trim);
                        continue 2;
                    }
                    // else ?
                    break;
                case 'MAXVALUE':
                    if ($curr_category === 'VALUES') {
                        $expr[] = $this->get_constant_type($trim);
                        $last = array_pop($parsed['sub_tree']);
                        $last['base_expr'] = $base_expr;
                        $last['sub_tree'] = $expr;
                        $base_expr = $last['storage'] . $base_expr;
                        unset($last['storage']);
                        $parsed['sub_tree'][] = $last;
                        $parsed['base_expr'] = trim($base_expr);
                        $expr = $parsed['sub_tree'];
                        unset($last);
                        $curr_category = $prev_category;
                    }
                    // else ?
                    break;
                case 'COMMENT':
                    if ($prev_category === 'PARTITION') {
                        $expr[] = ['expr_type' => Expression_Type::PARTITION_COMMENT, 'base_expr' => false, 'sub_tree' => false, 'storage' => substr($base_expr, 0, -strlen($token))];
                        $parsed['sub_tree'] = $expr;
                        $base_expr = $token;
                        $expr = [$this->get_reserved_type($trim)];
                        $curr_category = $upper;
                        continue 2;
                    }
                    // else ?
                    break;
                case 'STORAGE':
                    if ($prev_category === 'PARTITION') {
                        // followed by ENGINE
                        $expr[] = ['expr_type' => Expression_Type::ENGINE, 'base_expr' => false, 'sub_tree' => false, 'storage' => substr($base_expr, 0, -strlen($token))];
                        $parsed['sub_tree'] = $expr;
                        $base_expr = $token;
                        $expr = [$this->get_reserved_type($trim)];
                        $curr_category = $upper;
                        continue 2;
                    }
                    // else ?
                    break;
                case 'ENGINE':
                    if ($curr_category === 'STORAGE') {
                        $expr[] = $this->get_reserved_type($trim);
                        $curr_category = $upper;
                        continue 2;
                    }
                    if ($prev_category === 'PARTITION') {
                        $expr[] = ['expr_type' => Expression_Type::ENGINE, 'base_expr' => false, 'sub_tree' => false, 'storage' => substr($base_expr, 0, -strlen($token))];
                        $parsed['sub_tree'] = $expr;
                        $base_expr = $token;
                        $expr = [$this->get_reserved_type($trim)];
                        $curr_category = $upper;
                        continue 2;
                    }
                    // else ?
                    break;
                case '=':
                    if (in_array($curr_category, ['ENGINE', 'COMMENT', 'DIRECTORY', 'MAX_ROWS', 'MIN_ROWS'])) {
                        $expr[] = $this->get_operator_type($trim);
                        continue 2;
                    }
                    // else ?
                    break;
                case ',':
                    if ($prev_category === 'PARTITION' && $curr_category === '') {
                        // it separates the partition-definitions
                        $result[] = $parsed;
                        $parsed = [];
                        $base_expr = '';
                        $expr = [];
                    }
                    break;
                case 'DATA':
                case 'INDEX':
                    if ($prev_category === 'PARTITION') {
                        // followed by DIRECTORY
                        $expr[] = ['expr_type' => constant('PHPSQLParser\utils\ExpressionType::PARTITION_' . $upper . '_DIR'), 'base_expr' => false, 'sub_tree' => false, 'storage' => substr($base_expr, 0, -strlen($token))];
                        $parsed['sub_tree'] = $expr;
                        $base_expr = $token;
                        $expr = [$this->get_reserved_type($trim)];
                        $curr_category = $upper;
                        continue 2;
                    }
                    // else ?
                    break;
                case 'DIRECTORY':
                    if ($curr_category === 'DATA' || $curr_category === 'INDEX') {
                        $expr[] = $this->get_reserved_type($trim);
                        $curr_category = $upper;
                        continue 2;
                    }
                    // else ?
                    break;
                case 'MAX_ROWS':
                case 'MIN_ROWS':
                    if ($prev_category === 'PARTITION') {
                        $expr[] = ['expr_type' => constant('PHPSQLParser\utils\ExpressionType::PARTITION_' . $upper), 'base_expr' => false, 'sub_tree' => false, 'storage' => substr($base_expr, 0, -strlen($token))];
                        $parsed['sub_tree'] = $expr;
                        $base_expr = $token;
                        $expr = [$this->get_reserved_type($trim)];
                        $curr_category = $upper;
                        continue 2;
                    }
                    // else ?
                    break;
                default:
                    switch ($curr_category) {
                        case 'MIN_ROWS':
                        case 'MAX_ROWS':
                        case 'ENGINE':
                        case 'DIRECTORY':
                        case 'COMMENT':
                            $expr[] = $this->get_constant_type($trim);
                            $last = array_pop($parsed['sub_tree']);
                            $last['sub_tree'] = $expr;
                            $last['base_expr'] = trim($base_expr);
                            $base_expr = $last['storage'] . $base_expr;
                            unset($last['storage']);
                            $parsed['sub_tree'][] = $last;
                            $parsed['base_expr'] = trim($base_expr);
                            $expr = $parsed['sub_tree'];
                            unset($last);
                            $curr_category = $prev_category;
                            break;
                        case 'PARTITION':
                            // that is the partition name
                            $last = array_pop($expr);
                            $last['name'] = $trim;
                            $expr[] = $last;
                            $expr[] = $this->get_constant_type($trim);
                            $parsed['sub_tree'] = $expr;
                            $parsed['base_expr'] = trim($base_expr);
                            break;
                        case 'VALUES':
                            // we have parenthesis and have to process an expression/in-list
                            $last = $this->get_bracket_expression_type($trim);
                            $res = $this->process_expression_list($trim);
                            $last['sub_tree'] = empty($res) ? false : $res;
                            $expr[] = $last;
                            $last = array_pop($parsed['sub_tree']);
                            $last['base_expr'] = $base_expr;
                            $last['sub_tree'] = $expr;
                            $base_expr = $last['storage'] . $base_expr;
                            unset($last['storage']);
                            $parsed['sub_tree'][] = $last;
                            $parsed['base_expr'] = trim($base_expr);
                            $expr = $parsed['sub_tree'];
                            unset($last);
                            $curr_category = $prev_category;
                            break;
                        case '':
                            if ($prev_category === 'PARTITION') {
                                // last part to process, it is only one token!
                                if ($upper[0] === '(' && substr($upper, -1) === ')') {
                                    $last = $this->get_bracket_expression_type($trim);
                                    $last['sub_tree'] = $this->process_subpartition_definition($trim);
                                    $expr[] = $last;
                                    unset($last);
                                    $parsed['base_expr'] = trim($base_expr);
                                    $parsed['sub_tree'] = $expr;
                                    $curr_category = $prev_category;
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
        $result[] = $parsed;
        return $result;
    }
}