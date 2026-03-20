<?php

declare (strict_types=1);
/**
 * ColumnDefinitionProcessor.php
 *
 * This file implements the processor for column definition part of a CREATE TABLE statement.
 *
 * Copyright (c) 2010-2012, Justin Swanhart
 * with contributions by André Rothe <arothe@phosco.info, phosco@gmx.de>
 *
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 *   * Redistributions of source code must retain the above copyright notice,
 *     this list of conditions and the following disclaimer.
 *   * Redistributions in binary form must reproduce the above copyright notice,
 *     this list of conditions and the following disclaimer in the documentation
 *     and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
 * OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT
 * SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED
 * TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR
 * BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH
 * DAMAGE.
 */
namespace Phpsql_Parser\processors;

use Phpsql_Parser\utils\Expression_Type;
/**
 *
 * This class processes the column definition part of a CREATE TABLE statement.
 *
 * @author arothe
 *
 */
class Column_Definition_Processor extends Abstract_Processor
{
    protected function process_expression_list($parsed)
    {
        $processor = new Expression_List_Processor($this->options);
        $expr = $this->remove_parenthesis_from_start($parsed);
        $expr = $this->split_sql_into_tokens($expr);
        $expr = $this->remove_comma($expr);
        return $processor->process($expr);
    }
    protected function process_reference_definition($parsed)
    {
        $processor = new Reference_Definition_Processor($this->options);
        return $processor->process($parsed);
    }
    protected function remove_comma($tokens)
    {
        $res = [];
        foreach ($tokens as $token) {
            if (trim($token) !== ',') {
                $res[] = $token;
            }
        }
        return $res;
    }
    protected function build_col_def($expr, $base_expr, array $options, $refs, $key)
    {
        $expr = ['expr_type' => Expression_Type::COLUMN_TYPE, 'base_expr' => $base_expr, 'sub_tree' => $expr];
        // add options first
        $expr['sub_tree'] = array_merge($expr['sub_tree'], $options['sub_tree']);
        unset($options['sub_tree']);
        $expr = array_merge($expr, $options);
        // followed by references
        if (sizeof($refs) !== 0) {
            $expr['sub_tree'] = array_merge($expr['sub_tree'], $refs);
        }
        $expr['till'] = $key;
        return $expr;
    }
    protected function peek_at_next_token(array $tokens, $index)
    {
        $offset = $index + 1;
        while (isset($tokens[$offset])) {
            $token = trim($tokens[$offset]);
            if ($token !== '') {
                return strtoupper($token);
            }
            $offset++;
        }
        return '';
    }
    public function process($tokens)
    {
        $trim = '';
        $base_expr = '';
        $curr_category = '';
        $expr = [];
        $refs = [];
        $options = ['unique' => false, 'nullable' => true, 'auto_inc' => false, 'primary' => false, 'sub_tree' => []];
        $skip = 0;
        foreach ($tokens as $key => $token) {
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
                    // we stop on a single comma and return
                    // the $expr entry and the index $key
                    $expr = $this->build_col_def($expr, trim(substr($base_expr, 0, -strlen($token))), $options, $refs, $key - 1);
                    break 2;
                case 'VARCHAR':
                case 'VARCHARACTER':
                    // Alias for VARCHAR
                    $expr[] = ['expr_type' => Expression_Type::DATA_TYPE, 'base_expr' => $trim, 'length' => false];
                    $prev_category = 'TEXT';
                    $curr_category = 'SINGLE_PARAM_PARENTHESIS';
                    continue 2;
                case 'VARBINARY':
                    $expr[] = ['expr_type' => Expression_Type::DATA_TYPE, 'base_expr' => $trim, 'length' => false];
                    $prev_category = $upper;
                    $curr_category = 'SINGLE_PARAM_PARENTHESIS';
                    continue 2;
                case 'UNSIGNED':
                    foreach (array_reverse(array_keys($expr)) as $i) {
                        if (isset($expr[$i]['expr_type']) && Expression_Type::DATA_TYPE === $expr[$i]['expr_type']) {
                            $expr[$i]['unsigned'] = true;
                            break;
                        }
                    }
                    $options['sub_tree'][] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                    continue 2;
                case 'ZEROFILL':
                    $last = array_pop($expr);
                    $last['zerofill'] = true;
                    $expr[] = $last;
                    $options['sub_tree'][] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                    continue 2;
                case 'BIT':
                case 'TINYBIT':
                case 'TINYINT':
                case 'SMALLINT':
                case 'INT2':
                // Alias of SMALLINT
                case 'MEDIUMINT':
                case 'INT3':
                // Alias of MEDIUMINT
                case 'MIDDLEINT':
                // Alias of MEDIUMINT
                case 'INT':
                case 'INTEGER':
                case 'INT4':
                // Alias of INT
                case 'BIGINT':
                case 'INT8':
                // Alias of BIGINT
                case 'BOOL':
                case 'BOOLEAN':
                    $expr[] = ['expr_type' => Expression_Type::DATA_TYPE, 'base_expr' => $trim, 'unsigned' => false, 'zerofill' => false, 'length' => false];
                    $curr_category = 'SINGLE_PARAM_PARENTHESIS';
                    $prev_category = $upper;
                    continue 2;
                case 'BINARY':
                    if ($curr_category === 'TEXT') {
                        $last = array_pop($expr);
                        $last['binary'] = true;
                        $last['sub_tree'][] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                        $expr[] = $last;
                        continue 2;
                    }
                    $expr[] = ['expr_type' => Expression_Type::DATA_TYPE, 'base_expr' => $trim, 'length' => false];
                    $curr_category = 'SINGLE_PARAM_PARENTHESIS';
                    $prev_category = $upper;
                    continue 2;
                case 'CHAR':
                    $expr[] = ['expr_type' => Expression_Type::DATA_TYPE, 'base_expr' => $trim, 'length' => false];
                    $curr_category = 'SINGLE_PARAM_PARENTHESIS';
                    $prev_category = 'TEXT';
                    continue 2;
                case 'REAL':
                case 'DOUBLE':
                case 'FLOAT8':
                // Alias for DOUBLE
                case 'FLOAT':
                case 'FLOAT4':
                    // Alias for FLOAT
                    $expr[] = ['expr_type' => Expression_Type::DATA_TYPE, 'base_expr' => $trim, 'unsigned' => false, 'zerofill' => false];
                    $curr_category = 'TWO_PARAM_PARENTHESIS';
                    $prev_category = $upper;
                    continue 2;
                case 'DECIMAL':
                case 'NUMERIC':
                    $expr[] = ['expr_type' => Expression_Type::DATA_TYPE, 'base_expr' => $trim, 'unsigned' => false, 'zerofill' => false];
                    $curr_category = 'TWO_PARAM_PARENTHESIS';
                    $prev_category = $upper;
                    continue 2;
                case 'YEAR':
                    $expr[] = ['expr_type' => Expression_Type::DATA_TYPE, 'base_expr' => $trim, 'length' => false];
                    $curr_category = 'SINGLE_PARAM_PARENTHESIS';
                    $prev_category = $upper;
                    continue 2;
                case 'DATE':
                case 'TIME':
                case 'TIMESTAMP':
                case 'DATETIME':
                case 'TINYBLOB':
                case 'BLOB':
                case 'MEDIUMBLOB':
                case 'LONGBLOB':
                    $expr[] = ['expr_type' => Expression_Type::DATA_TYPE, 'base_expr' => $trim];
                    $prev_category = $curr_category = $upper;
                    continue 2;
                // the next token can be BINARY
                case 'TINYTEXT':
                case 'TEXT':
                case 'MEDIUMTEXT':
                case 'LONGTEXT':
                    $prev_category = $curr_category = 'TEXT';
                    $expr[] = ['expr_type' => Expression_Type::DATA_TYPE, 'base_expr' => $trim, 'binary' => false];
                    continue 2;
                case 'ENUM':
                    $curr_category = 'MULTIPLE_PARAM_PARENTHESIS';
                    $prev_category = 'TEXT';
                    $expr[] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim, 'sub_tree' => false];
                    continue 2;
                case 'GEOMETRY':
                case 'POINT':
                case 'LINESTRING':
                case 'POLYGON':
                case 'MULTIPOINT':
                case 'MULTILINESTRING':
                case 'MULTIPOLYGON':
                case 'GEOMETRYCOLLECTION':
                    $expr[] = ['expr_type' => Expression_Type::DATA_TYPE, 'base_expr' => $trim];
                    $prev_category = $curr_category = $upper;
                    // TODO: is it right?
                    // spatial types
                    continue 2;
                case 'CHARSET':
                    $curr_category = 'CHARSET';
                    $options['sub_tree'][] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                    continue 2;
                case 'CHARACTER':
                    // Alias of CHAR as well as pre-running for CHARACTER SET
                    // To determine which we peek at the next token to see if it's a SET or not.
                    if ($this->peek_at_next_token($tokens, $key) == 'SET') {
                        $curr_category = 'CHARSET';
                        $options['sub_tree'][] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                        // If it's not a SET we assume that it is a CHARACTER type definition
                    } else {
                        $expr[] = ['expr_type' => Expression_Type::DATA_TYPE, 'base_expr' => $trim, 'length' => false];
                        $curr_category = 'SINGLE_PARAM_PARENTHESIS';
                        $prev_category = 'TEXT';
                    }
                    continue 2;
                case 'SET':
                    if ($curr_category == 'CHARSET') {
                        $options['sub_tree'][] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                    } else {
                        $curr_category = 'MULTIPLE_PARAM_PARENTHESIS';
                        $prev_category = 'TEXT';
                        $expr[] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim, 'sub_tree' => false];
                    }
                    continue 2;
                case 'COLLATE':
                    $curr_category = $upper;
                    $options['sub_tree'][] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                    continue 2;
                case 'NOT':
                case 'NULL':
                    $options['sub_tree'][] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                    if ($options['nullable']) {
                        $options['nullable'] = $upper === 'NOT' ? false : true;
                    }
                    continue 2;
                case 'DEFAULT':
                case 'COMMENT':
                    $curr_category = $upper;
                    $options['sub_tree'][] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                    continue 2;
                case 'AUTO_INCREMENT':
                    $options['sub_tree'][] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                    $options['auto_inc'] = true;
                    continue 2;
                case 'COLUMN_FORMAT':
                case 'STORAGE':
                    $curr_category = $upper;
                    $options['sub_tree'][] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                    continue 2;
                case 'UNIQUE':
                    // it can follow a KEY word
                    $curr_category = $upper;
                    $options['sub_tree'][] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                    $options['unique'] = true;
                    continue 2;
                case 'PRIMARY':
                    // it must follow a KEY word
                    $options['sub_tree'][] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                    continue 2;
                case 'KEY':
                    $options['sub_tree'][] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                    if ($curr_category !== 'UNIQUE') {
                        $options['primary'] = true;
                    }
                    continue 2;
                case 'REFERENCES':
                    $refs = $this->process_reference_definition(array_splice($tokens, $key - 1, null, true));
                    $skip = $refs['till'] - $key;
                    unset($refs['till']);
                    // TODO: check this, we need the last comma
                    continue 2;
                default:
                    switch ($curr_category) {
                        case 'STORAGE':
                            if ($upper === 'DISK' || $upper === 'MEMORY' || $upper === 'DEFAULT') {
                                $options['sub_tree'][] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                                $options['storage'] = $trim;
                                continue 3;
                            }
                            // else ?
                            break;
                        case 'COLUMN_FORMAT':
                            if ($upper === 'FIXED' || $upper === 'DYNAMIC' || $upper === 'DEFAULT') {
                                $options['sub_tree'][] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                                $options['col_format'] = $trim;
                                continue 3;
                            }
                            // else ?
                            break;
                        case 'COMMENT':
                            // this is the comment string
                            $options['sub_tree'][] = ['expr_type' => Expression_Type::COMMENT, 'base_expr' => $trim];
                            $options['comment'] = $trim;
                            $curr_category = $prev_category;
                            break;
                        case 'DEFAULT':
                            // this is the default value
                            $options['sub_tree'][] = ['expr_type' => Expression_Type::DEF_VALUE, 'base_expr' => $trim];
                            $options['default'] = $trim;
                            $curr_category = $prev_category;
                            break;
                        case 'COLLATE':
                            // this is the collation name
                            $options['sub_tree'][] = ['expr_type' => Expression_Type::COLLATE, 'base_expr' => $trim];
                            $options['collate'] = $trim;
                            $curr_category = $prev_category;
                            break;
                        case 'CHARSET':
                            // this is the character set name
                            $options['sub_tree'][] = ['expr_type' => Expression_Type::CHARSET, 'base_expr' => $trim];
                            $options['charset'] = $trim;
                            $curr_category = $prev_category;
                            break;
                        case 'SINGLE_PARAM_PARENTHESIS':
                            $parsed = $this->remove_parenthesis_from_start($trim);
                            $parsed = ['expr_type' => Expression_Type::CONSTANT, 'base_expr' => trim($parsed)];
                            $last = array_pop($expr);
                            $last['length'] = $parsed['base_expr'];
                            $expr[] = $last;
                            $expr[] = ['expr_type' => Expression_Type::BRACKET_EXPRESSION, 'base_expr' => $trim, 'sub_tree' => [$parsed]];
                            $curr_category = $prev_category;
                            break;
                        case 'TWO_PARAM_PARENTHESIS':
                            // maximum of two parameters
                            $parsed = $this->process_expression_list($trim);
                            $last = array_pop($expr);
                            $last['length'] = $parsed[0]['base_expr'];
                            $last['decimals'] = isset($parsed[1]) ? $parsed[1]['base_expr'] : false;
                            $expr[] = $last;
                            $expr[] = ['expr_type' => Expression_Type::BRACKET_EXPRESSION, 'base_expr' => $trim, 'sub_tree' => $parsed];
                            $curr_category = $prev_category;
                            break;
                        case 'MULTIPLE_PARAM_PARENTHESIS':
                            // some parameters
                            $parsed = $this->process_expression_list($trim);
                            $last = array_pop($expr);
                            $sub_tree = ['expr_type' => Expression_Type::BRACKET_EXPRESSION, 'base_expr' => $trim, 'sub_tree' => $parsed];
                            if ($this->options->get_consistent_subtrees()) {
                                $sub_tree = [$sub_tree];
                            }
                            $last['sub_tree'] = $sub_tree;
                            $expr[] = $last;
                            $curr_category = $prev_category;
                            break;
                        default:
                            break;
                    }
            }
            $prev_category = $curr_category;
            $curr_category = '';
        }
        if (!isset($expr['till'])) {
            // end of $tokens array
            return $this->build_col_def($expr, trim($base_expr), $options, $refs, -1);
        }
        return $expr;
    }
}