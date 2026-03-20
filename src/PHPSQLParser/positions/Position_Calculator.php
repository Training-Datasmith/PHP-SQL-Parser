<?php

declare (strict_types=1);
/**
 * PositionCalculator.php
 *
 * This class implements the calculator for the string positions of the
 * base_expr elements within the output of the PHPSQLParser.
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
 * @copyright 2010-2015 Justin Swanhart and André Rothe
 * @license   http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 * @version   SVN: $Id$
 *
 */
namespace Phpsql_Parser\positions;

use Phpsql_Parser\exceptions\Unable_To_Calculate_Position_Exception;
use Phpsql_Parser\utils\Expression_Type;
use Phpsql_Parser\utils\Phpsql_Parser_Constants;
/**
 * This class implements the calculator for the string positions of the
 * base_expr elements within the output of the PHPSQLParser.
 *
 * @author  André Rothe <andre.rothe@phosco.info>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *
 */
class Position_Calculator
{
    protected static $allowed_on_operator = ["\t", "\n", "\r", ' ', ',', '(', ')', '_', "'", '"', '?', '@', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    protected static $allowed_on_other = ["\t", "\n", "\r", ' ', ',', '(', ')', '<', '>', '*', '+', '-', '/', '|', '&', '=', '!', ';'];
    protected $flipped_backtracking_types;
    protected static $backtracking_types = [Expression_Type::EXPRESSION, Expression_Type::SUBQUERY, Expression_Type::BRACKET_EXPRESSION, Expression_Type::TABLE_EXPRESSION, Expression_Type::RECORD, Expression_Type::IN_LIST, Expression_Type::MATCH_ARGUMENTS, Expression_Type::TABLE, Expression_Type::TEMPORARY_TABLE, Expression_Type::COLUMN_TYPE, Expression_Type::COLDEF, Expression_Type::PRIMARY_KEY, Expression_Type::CONSTRAINT, Expression_Type::COLUMN_LIST, Expression_Type::CHECK, Expression_Type::COLLATE, Expression_Type::LIKE, Expression_Type::INDEX, Expression_Type::INDEX_TYPE, Expression_Type::INDEX_SIZE, Expression_Type::INDEX_PARSER, Expression_Type::FOREIGN_KEY, Expression_Type::REFERENCE, Expression_Type::PARTITION, Expression_Type::PARTITION_HASH, Expression_Type::PARTITION_COUNT, Expression_Type::PARTITION_KEY, Expression_Type::PARTITION_KEY_ALGORITHM, Expression_Type::PARTITION_RANGE, Expression_Type::PARTITION_LIST, Expression_Type::PARTITION_DEF, Expression_Type::PARTITION_VALUES, Expression_Type::SUBPARTITION_DEF, Expression_Type::PARTITION_DATA_DIR, Expression_Type::PARTITION_INDEX_DIR, Expression_Type::PARTITION_COMMENT, Expression_Type::PARTITION_MAX_ROWS, Expression_Type::PARTITION_MIN_ROWS, Expression_Type::SUBPARTITION_COMMENT, Expression_Type::SUBPARTITION_DATA_DIR, Expression_Type::SUBPARTITION_INDEX_DIR, Expression_Type::SUBPARTITION_KEY, Expression_Type::SUBPARTITION_KEY_ALGORITHM, Expression_Type::SUBPARTITION_MAX_ROWS, Expression_Type::SUBPARTITION_MIN_ROWS, Expression_Type::SUBPARTITION, Expression_Type::SUBPARTITION_HASH, Expression_Type::SUBPARTITION_COUNT, Expression_Type::CHARSET, Expression_Type::ENGINE, Expression_Type::QUERY, Expression_Type::INDEX_ALGORITHM, Expression_Type::INDEX_LOCK, Expression_Type::SUBQUERY_FACTORING, Expression_Type::CUSTOM_FUNCTION, Expression_Type::SIMPLE_FUNCTION];
    /**
     * Constructor.
     *
     * It initializes some fields.
     */
    public function __construct()
    {
        $this->flipped_backtracking_types = array_flip(self::$backtracking_types);
    }
    protected function print_pos($text, $sql, $char_pos, $key, $parsed, $backtracking)
    {
        if (!isset($_SERVER['DEBUG'])) {
            return;
        }
        $spaces = '';
        $caller = debug_backtrace();
        $i = 1;
        while ($caller[$i]['function'] === 'lookForBaseExpression') {
            $spaces .= '   ';
            $i++;
        }
        $holdem = substr($sql, 0, $char_pos) . '^' . substr($sql, $char_pos);
        echo $spaces . $text . ' key:' . $key . '  parsed:' . $parsed . ' back:' . serialize($backtracking) . ' ' . $holdem . "\n";
    }
    public function set_positions_within_sql($sql, $parsed)
    {
        $char_pos = 0;
        $backtracking = [];
        $this->look_for_base_expression($sql, $char_pos, $parsed, 0, $backtracking);
        return $parsed;
    }
    protected function find_position_within_string($sql, $value, $expr_type)
    {
        if ($value === '') {
            return false;
        }
        $offset = 0;
        $ok = false;
        while (true) {
            $pos = strpos($sql, $value, $offset);
            // error_log("pos:$pos value:$value sql:$sql");
            if ($pos === false) {
                break;
            }
            $before = '';
            if ($pos > 0) {
                $before = $sql[$pos - 1];
            }
            // if we have a quoted string, we every character is allowed after it
            // see issues 137 and 361
            $quoted_before = in_array($sql[$pos], ['`', '('], true);
            $quoted_after = in_array($sql[$pos + strlen($value) - 1], ['`', ')'], true);
            $after = '';
            if (isset($sql[$pos + strlen($value)])) {
                $after = $sql[$pos + strlen($value)];
            }
            // if we have an operator, it should be surrounded by
            // whitespace, comma, parenthesis, digit or letter, end_of_string
            // an operator should not be surrounded by another operator
            if (in_array($expr_type, ['operator', 'column-list'], true)) {
                $ok = $before === '' || in_array($before, self::$allowed_on_operator, true) || strtolower($before) >= 'a' && strtolower($before) <= 'z';
                $ok = $ok && ($after === '' || in_array($after, self::$allowed_on_operator, true) || strtolower($after) >= 'a' && strtolower($after) <= 'z');
                if (!$ok) {
                    $offset = $pos + 1;
                    continue;
                }
                break;
            }
            // in all other cases we accept
            // whitespace, comma, operators, parenthesis and end_of_string
            $ok = $before === '' || in_array($before, self::$allowed_on_other, true) || $quoted_before && (strtolower($before) >= 'a' && strtolower($before) <= 'z');
            $ok = $ok && ($after === '' || in_array($after, self::$allowed_on_other, true) || $quoted_after && (strtolower($after) >= 'a' && strtolower($after) <= 'z'));
            if ($ok) {
                break;
            }
            $offset = $pos + 1;
        }
        return $pos;
    }
    protected function look_for_base_expression($sql, &$char_pos, &$parsed, $key, &$backtracking)
    {
        if (!is_numeric($key)) {
            if ($key === 'UNION' || $key === 'UNION ALL' || $key === 'expr_type' && isset($this->flipped_backtracking_types[$parsed]) || $key === 'select-option' && $parsed !== false || $key === 'alias' && $parsed !== false) {
                // we hold the current position and come back after the next base_expr
                // we do this, because the next base_expr contains the complete expression/subquery/record
                // and we have to look into it too
                $backtracking[] = $char_pos;
            } elseif (($key === 'ref_clause' || $key === 'columns') && $parsed !== false) {
                // we hold the current position and come back after n base_expr(s)
                // there is an array of sub-elements before (!) the base_expr clause of the current element
                // so we go through the sub-elements and must come at the end
                $backtracking[] = $char_pos;
                for ($i = 1; $i < count($parsed); $i++) {
                    $backtracking[] = false;
                    // backtracking only after n base_expr!
                }
            } elseif ($key === 'sub_tree' && $parsed !== false || $key === 'options' && $parsed !== false) {
                // we prevent wrong backtracking on subtrees (too much array_pop())
                // there is an array of sub-elements after(!) the base_expr clause of the current element
                // so we go through the sub-elements and must not come back at the end
                for ($i = 1; $i < count($parsed); $i++) {
                    $backtracking[] = false;
                }
            } elseif ($key === 'TABLE' || $key === 'create-def' && $parsed !== false) {
                // do nothing
            } else if (Phpsql_Parser_Constants::get_instance()->is_reserved($key)) {
                $char_pos = stripos($sql, $key, $char_pos);
                $char_pos += strlen($key);
            }
        }
        if (!is_array($parsed)) {
            return;
        }
        foreach ($parsed as $key => $value) {
            if ($key === 'base_expr') {
                //$this->printPos("0", $sql, $charPos, $key, $value, $backtracking);
                $subject = substr($sql, $char_pos);
                $pos = $this->find_position_within_string($subject, $value, isset($parsed['expr_type']) ? $parsed['expr_type'] : 'alias');
                if ($pos === false) {
                    throw new Unable_To_Calculate_Position_Exception($value, $subject);
                }
                $parsed['position'] = $char_pos + $pos;
                $char_pos += $pos + strlen($value);
                //$this->printPos("1", $sql, $charPos, $key, $value, $backtracking);
                $old_pos = array_pop($backtracking);
                if (isset($old_pos) && $old_pos !== false) {
                    $char_pos = $old_pos;
                }
                //$this->printPos("2", $sql, $charPos, $key, $value, $backtracking);
            } else {
                $this->look_for_base_expression($sql, $char_pos, $parsed[$key], $key, $backtracking);
            }
        }
    }
}