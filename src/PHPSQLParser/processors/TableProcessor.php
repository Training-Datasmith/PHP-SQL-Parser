<?php

declare (strict_types=1);
/**
 * TableProcessor.php
 *
 * This file implements the processor for the TABLE statements.
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
 * This class processes the TABLE statements.
 *
 * @author  André Rothe <andre.rothe@phosco.info>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *
 */
class Table_Processor extends Abstract_Processor
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
    protected function process_partition_options($tokens)
    {
        $processor = new Partition_Options_Processor($this->options);
        return $processor->process($tokens);
    }
    protected function process_create_definition($tokens)
    {
        $processor = new Create_Definition_Processor($this->options);
        return $processor->process($tokens);
    }
    protected function clear(&$expr, &$base_expr, &$category)
    {
        $expr = [];
        $base_expr = '';
        $category = 'CREATE_DEF';
    }
    public function process($tokens)
    {
        $curr_category = 'TABLE_NAME';
        $result = ['base_expr' => false, 'name' => false, 'no_quotes' => false, 'create-def' => false, 'options' => [], 'like' => false, 'select-option' => false];
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
                case ',':
                    // it is possible to separate the table options with comma!
                    if ($prev_category === 'CREATE_DEF') {
                        $last = array_pop($result['options']);
                        $last['delim'] = ',';
                        $result['options'][] = $last;
                        $base_expr = '';
                    }
                    continue 2;
                case 'UNION':
                    if ($prev_category === 'CREATE_DEF') {
                        $expr[] = $this->get_reserved_type($trim);
                        $curr_category = 'UNION';
                        continue 2;
                    }
                    break;
                case 'LIKE':
                    // like without parenthesis
                    if ($prev_category === 'TABLE_NAME') {
                        $curr_category = $upper;
                        continue 2;
                    }
                    break;
                case '=':
                    // the optional operator
                    if ($prev_category === 'TABLE_OPTION') {
                        $expr[] = $this->get_operator_type($trim);
                        continue 2;
                        // don't change the category
                    }
                    break;
                case 'CHARACTER':
                    if ($prev_category === 'CREATE_DEF') {
                        $expr[] = $this->get_reserved_type($trim);
                        $curr_category = 'TABLE_OPTION';
                    }
                    if ($prev_category === 'TABLE_OPTION') {
                        // add it to the previous DEFAULT
                        $expr[] = $this->get_reserved_type($trim);
                        continue 2;
                    }
                    break;
                case 'SET':
                case 'CHARSET':
                    if ($prev_category === 'TABLE_OPTION') {
                        // add it to a previous CHARACTER
                        $expr[] = $this->get_reserved_type($trim);
                        $curr_category = 'CHARSET';
                        continue 2;
                    }
                    break;
                case 'COLLATE':
                    if ($prev_category === 'TABLE_OPTION' || $prev_category === 'CREATE_DEF') {
                        // add it to the previous DEFAULT
                        $expr[] = $this->get_reserved_type($trim);
                        $curr_category = 'COLLATE';
                        continue 2;
                    }
                    break;
                case 'DIRECTORY':
                    if ($curr_category === 'INDEX_DIRECTORY' || $curr_category === 'DATA_DIRECTORY') {
                        // after INDEX or DATA
                        $expr[] = $this->get_reserved_type($trim);
                        continue 2;
                    }
                    break;
                case 'INDEX':
                    if ($prev_category === 'CREATE_DEF') {
                        $expr[] = $this->get_reserved_type($trim);
                        $curr_category = 'INDEX_DIRECTORY';
                        continue 2;
                    }
                    break;
                case 'DATA':
                    if ($prev_category === 'CREATE_DEF') {
                        $expr[] = $this->get_reserved_type($trim);
                        $curr_category = 'DATA_DIRECTORY';
                        continue 2;
                    }
                    break;
                case 'INSERT_METHOD':
                case 'DELAY_KEY_WRITE':
                case 'ROW_FORMAT':
                case 'PASSWORD':
                case 'MAX_ROWS':
                case 'MIN_ROWS':
                case 'PACK_KEYS':
                case 'CHECKSUM':
                case 'COMMENT':
                case 'CONNECTION':
                case 'AUTO_INCREMENT':
                case 'AVG_ROW_LENGTH':
                case 'ENGINE':
                case 'TYPE':
                case 'STATS_AUTO_RECALC':
                case 'STATS_PERSISTENT':
                case 'KEY_BLOCK_SIZE':
                    if ($prev_category === 'CREATE_DEF') {
                        $expr[] = $this->get_reserved_type($trim);
                        $curr_category = $prev_category = 'TABLE_OPTION';
                        continue 2;
                    }
                    break;
                case 'DYNAMIC':
                case 'FIXED':
                case 'COMPRESSED':
                case 'REDUNDANT':
                case 'COMPACT':
                case 'NO':
                case 'FIRST':
                case 'LAST':
                case 'DEFAULT':
                    if ($prev_category === 'CREATE_DEF') {
                        // DEFAULT before CHARACTER SET and COLLATE
                        $expr[] = $this->get_reserved_type($trim);
                        $curr_category = 'TABLE_OPTION';
                    }
                    if ($prev_category === 'TABLE_OPTION') {
                        // all assignments with the keywords
                        $expr[] = $this->get_reserved_type($trim);
                        $result['options'][] = ['expr_type' => Expression_Type::EXPRESSION, 'base_expr' => trim($base_expr), 'delim' => ' ', 'sub_tree' => $expr];
                        $this->clear($expr, $base_expr, $curr_category);
                    }
                    break;
                case 'IGNORE':
                case 'REPLACE':
                    $expr[] = $this->get_reserved_type($trim);
                    $result['select-option'] = ['base_expr' => trim($base_expr), 'duplicates' => $trim, 'as' => false, 'sub_tree' => $expr];
                    continue 2;
                case 'AS':
                    $expr[] = $this->get_reserved_type($trim);
                    if (!isset($result['select-option']['duplicates'])) {
                        $result['select-option']['duplicates'] = false;
                    }
                    $result['select-option']['as'] = true;
                    $result['select-option']['base_expr'] = trim($base_expr);
                    $result['select-option']['sub_tree'] = $expr;
                    continue 2;
                case 'PARTITION':
                    if ($prev_category === 'CREATE_DEF') {
                        $part = $this->process_partition_options(array_slice($tokens, $token_key - 1, null, true));
                        $skip = $part['last-parsed'] - $token_key;
                        $result['partition-options'] = $part['partition-options'];
                        continue 2;
                    }
                    // else
                    break;
                default:
                    switch ($curr_category) {
                        case 'CHARSET':
                            // the charset name
                            $expr[] = $this->get_constant_type($trim);
                            $result['options'][] = ['expr_type' => Expression_Type::CHARSET, 'base_expr' => trim($base_expr), 'delim' => ' ', 'sub_tree' => $expr];
                            $this->clear($expr, $base_expr, $curr_category);
                            break;
                        case 'COLLATE':
                            // the collate name
                            $expr[] = $this->get_constant_type($trim);
                            $result['options'][] = ['expr_type' => Expression_Type::COLLATE, 'base_expr' => trim($base_expr), 'delim' => ' ', 'sub_tree' => $expr];
                            $this->clear($expr, $base_expr, $curr_category);
                            break;
                        case 'DATA_DIRECTORY':
                            // we have the directory name
                            $expr[] = $this->get_constant_type($trim);
                            $result['options'][] = ['expr_type' => Expression_Type::DIRECTORY, 'kind' => 'DATA', 'base_expr' => trim($base_expr), 'delim' => ' ', 'sub_tree' => $expr];
                            $this->clear($expr, $base_expr, $prev_category);
                            continue 3;
                        case 'INDEX_DIRECTORY':
                            // we have the directory name
                            $expr[] = $this->get_constant_type($trim);
                            $result['options'][] = ['expr_type' => Expression_Type::DIRECTORY, 'kind' => 'INDEX', 'base_expr' => trim($base_expr), 'delim' => ' ', 'sub_tree' => $expr];
                            $this->clear($expr, $base_expr, $prev_category);
                            continue 3;
                        case 'TABLE_NAME':
                            $result['base_expr'] = $result['name'] = $trim;
                            $result['no_quotes'] = $this->revoke_quotation($trim);
                            $this->clear($expr, $base_expr, $prev_category);
                            break;
                        case 'LIKE':
                            $result['like'] = ['expr_type' => Expression_Type::TABLE, 'table' => $trim, 'base_expr' => $trim, 'no_quotes' => $this->revoke_quotation($trim)];
                            $this->clear($expr, $base_expr, $curr_category);
                            break;
                        case '':
                            // after table name
                            if ($prev_category === 'TABLE_NAME' && $upper[0] === '(' && substr($upper, -1) === ')') {
                                $unparsed = $this->split_sql_into_tokens($this->remove_parenthesis_from_start($trim));
                                $coldef = $this->process_create_definition($unparsed);
                                $result['create-def'] = ['expr_type' => Expression_Type::BRACKET_EXPRESSION, 'base_expr' => $base_expr, 'sub_tree' => $coldef['create-def']];
                                $expr = [];
                                $base_expr = '';
                                $curr_category = 'CREATE_DEF';
                            }
                            break;
                        case 'UNION':
                            // TODO: this token starts and ends with parenthesis
                            // and contains a list of table names (comma-separated)
                            // split the token and add the list as subtree
                            // we must change the DefaultProcessor
                            $unparsed = $this->split_sql_into_tokens($this->remove_parenthesis_from_start($trim));
                            $expr[] = ['expr_type' => Expression_Type::BRACKET_EXPRESSION, 'base_expr' => $trim, 'sub_tree' => '***TODO***'];
                            $result['options'][] = ['expr_type' => Expression_Type::UNION, 'base_expr' => trim($base_expr), 'delim' => ' ', 'sub_tree' => $expr];
                            $this->clear($expr, $base_expr, $curr_category);
                            break;
                        default:
                            // strings and numeric constants
                            $expr[] = $this->get_constant_type($trim);
                            $result['options'][] = ['expr_type' => Expression_Type::EXPRESSION, 'base_expr' => trim($base_expr), 'delim' => ' ', 'sub_tree' => $expr];
                            $this->clear($expr, $base_expr, $curr_category);
                            break;
                    }
                    break;
            }
            $prev_category = $curr_category;
            $curr_category = '';
        }
        if ($result['like'] === false) {
            unset($result['like']);
        }
        if ($result['select-option'] === false) {
            unset($result['select-option']);
        }
        if ($result['options'] === []) {
            $result['options'] = false;
        }
        return $result;
    }
}