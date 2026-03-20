<?php

declare (strict_types=1);
/**
 * AbstractProcessor.php
 *
 * This file implements an abstract processor, which implements some helper functions.
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

use Phpsql_Parser\lexer\Phpsql_Lexer;
use Phpsql_Parser\Options;
use Phpsql_Parser\utils\Expression_Type;
/**
 * This class contains some general functions for a processor.
 *
 * @author  André Rothe <andre.rothe@phosco.info>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *
 */
abstract class Abstract_Processor
{
    /**
     * @var Options
     */
    protected $options;
    /**
     * AbstractProcessor constructor.
     *
     * @param Options $options
     */
    public function __construct(?Options $options = null)
    {
        $this->options = $options;
    }
    /**
     * This function implements the main functionality of a processor class.
     * Always use default valuses for additional parameters within overridden functions.
     */
    abstract public function process($tokens);
    /**
     * this function splits up a SQL statement into easy to "parse"
     * tokens for the SQL processor
     */
    public function split_sql_into_tokens($sql)
    {
        $lexer = new Phpsql_Lexer();
        return $lexer->split($sql);
    }
    /**
     * Revokes the quoting characters from an expression
     * Possibibilies:
     *   `a`
     *   'a'
     *   "a"
     *   `a`.`b`
     *   `a.b`
     *   a.`b`
     *   `a`.b
     * It is also possible to have escaped quoting characters
     * within an expression part:
     *   `a``b` => a`b
     * And you can use whitespace between the parts:
     *   a  .  `b` => [a,b]
     */
    protected function revoke_quotation($sql)
    {
        $tmp = trim($sql);
        $result = [];
        $quote = false;
        $start = 0;
        $i = 0;
        $len = strlen($tmp);
        while ($i < $len) {
            $char = $tmp[$i];
            switch ($char) {
                case '`':
                case '\'':
                case '"':
                    if ($quote === false) {
                        // start
                        $quote = $char;
                        $start = $i + 1;
                        break;
                    }
                    if ($quote !== $char) {
                        break;
                    }
                    if (isset($tmp[$i + 1]) && $quote === $tmp[$i + 1]) {
                        // escaped
                        $i++;
                        break;
                    }
                    // end
                    $char = substr($tmp, $start, $i - $start);
                    $result[] = str_replace($quote . $quote, $quote, $char);
                    $start = $i + 1;
                    $quote = false;
                    break;
                case '.':
                    if ($quote === false) {
                        // we have found a separator
                        $char = trim(substr($tmp, $start, $i - $start));
                        if ($char !== '') {
                            $result[] = $char;
                        }
                        $start = $i + 1;
                    }
                    break;
                default:
                    // ignore
                    break;
            }
            $i++;
        }
        if ($quote === false && $start < $len) {
            $char = trim(substr($tmp, $start, $i - $start));
            if ($char !== '') {
                $result[] = $char;
            }
        }
        return ['delim' => count($result) === 1 ? false : '.', 'parts' => $result];
    }
    /**
     * This method removes parenthesis from start of the given string.
     * It removes also the associated closing parenthesis.
     */
    protected function remove_parenthesis_from_start($token)
    {
        $parenthesis_removed = 0;
        $trim = trim($token);
        if ($trim !== '' && $trim[0] === '(') {
            // remove only one parenthesis pair now!
            $parenthesis_removed++;
            $trim[0] = ' ';
            $trim = trim($trim);
        }
        $parenthesis = $parenthesis_removed;
        $i = 0;
        // Whether a string was opened or not, and with which character it was open (' or ")
        $string_opened = '';
        while ($i < strlen($trim)) {
            if ($trim[$i] === '\\') {
                $i += 2;
                // an escape character, the next character is irrelevant
                continue;
            }
            if ($trim[$i] === "'") {
                if ($string_opened === '') {
                    $string_opened = "'";
                } elseif ($string_opened === "'") {
                    $string_opened = '';
                }
            }
            if ($trim[$i] === '"') {
                if ($string_opened === '') {
                    $string_opened = '"';
                } elseif ($string_opened === '"') {
                    $string_opened = '';
                }
            }
            if ($string_opened === '' && $trim[$i] === '(') {
                $parenthesis++;
            }
            if ($string_opened === '' && $trim[$i] === ')') {
                if ($parenthesis == $parenthesis_removed) {
                    $trim[$i] = ' ';
                    $parenthesis_removed--;
                }
                $parenthesis--;
            }
            $i++;
        }
        return trim($trim);
    }
    protected function get_variable_type($expression)
    {
        // $expression must contain only upper-case characters
        if ($expression[1] !== '@') {
            return Expression_Type::USER_VARIABLE;
        }
        $type = substr($expression, 2, strpos($expression, '.', 2));
        switch ($type) {
            case 'GLOBAL':
                $type = Expression_Type::GLOBAL_VARIABLE;
                break;
            case 'LOCAL':
                $type = Expression_Type::LOCAL_VARIABLE;
                break;
            case 'SESSION':
            default:
                $type = Expression_Type::SESSION_VARIABLE;
                break;
        }
        return $type;
    }
    protected function is_comma_token($token)
    {
        return trim($token) === ',';
    }
    protected function is_whitespace_token($token)
    {
        return trim($token) === '';
    }
    protected function is_comment_token($token)
    {
        return isset($token[0]) && isset($token[1]) && ($token[0] === '-' && $token[1] === '-' || $token[0] === '/' && $token[1] === '*');
    }
    protected function is_column_reference(array $out)
    {
        return isset($out['expr_type']) && $out['expr_type'] === Expression_Type::COLREF;
    }
    protected function is_reserved(array $out)
    {
        return isset($out['expr_type']) && $out['expr_type'] === Expression_Type::RESERVED;
    }
    protected function is_constant(array $out)
    {
        return isset($out['expr_type']) && $out['expr_type'] === Expression_Type::CONSTANT;
    }
    protected function is_aggregate_function(array $out)
    {
        return isset($out['expr_type']) && $out['expr_type'] === Expression_Type::AGGREGATE_FUNCTION;
    }
    protected function is_custom_function(array $out)
    {
        return isset($out['expr_type']) && $out['expr_type'] === Expression_Type::CUSTOM_FUNCTION;
    }
    protected function is_function(array $out)
    {
        return isset($out['expr_type']) && $out['expr_type'] === Expression_Type::SIMPLE_FUNCTION;
    }
    protected function is_expression(array $out)
    {
        return isset($out['expr_type']) && $out['expr_type'] === Expression_Type::EXPRESSION;
    }
    protected function is_bracket_expression(array $out)
    {
        return isset($out['expr_type']) && $out['expr_type'] === Expression_Type::BRACKET_EXPRESSION;
    }
    protected function is_sub_query(array $out)
    {
        return isset($out['expr_type']) && $out['expr_type'] === Expression_Type::SUBQUERY;
    }
    protected function is_comment(array $out)
    {
        return isset($out['expr_type']) && $out['expr_type'] === Expression_Type::COMMENT;
    }
    public function process_comment($expression)
    {
        $result = [];
        $result['expr_type'] = Expression_Type::COMMENT;
        $result['value'] = $expression;
        return $result;
    }
    /**
     * translates an array of objects into an associative array
     */
    public function to_array($token_list)
    {
        $expr = [];
        foreach ($token_list as $token) {
            if ($token instanceof \Phpsql_Parser\utils\Expression_Token) {
                $expr[] = $token->to_array();
            } else {
                $expr[] = $token;
            }
        }
        return $expr;
    }
    protected function array_insert_after($array, $key, $entry)
    {
        $idx = array_search($key, array_keys($array));
        return array_slice($array, 0, $idx + 1, true) + $entry + array_slice($array, $idx + 1, count($array) - 1, true);
    }
}