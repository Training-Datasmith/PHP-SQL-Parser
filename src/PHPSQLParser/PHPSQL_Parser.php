<?php

declare (strict_types=1);
/**
 * PHPSQLParser.php
 *
 * A pure PHP SQL (non validating) parser w/ focus on MySQL dialect of SQL
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
 */
namespace Phpsql_Parser;

use Phpsql_Parser\positions\Position_Calculator;
use Phpsql_Parser\processors\Default_Processor;
use Phpsql_Parser\utils\Phpsql_Parser_Constants;
/**
 * This class implements the parser functionality.
 *
 * @author  Justin Swanhart <greenlion@gmail.com>
 * @author  André Rothe <arothe@phosco.info>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 */
class Phpsql_Parser
{
    public $parsed;
    /**
     * @var Options
     */
    private $options;
    /**
     * Constructor. Optionally parses an SQL string on construction.
     *
     * Pass a non-empty SQL string to parse immediately; the result will be
     * stored in the public `$parsed` property. You may also call parse()
     * separately at any time.
     *
     * @param string|false $sql             SQL statement to parse on construction, or false to skip
     * @param bool         $calc_positions  Whether to annotate every token with its character position
     * @param array<string, mixed> $options Parser option overrides
     *
     * @complexity O(n) where n is the length of the SQL string
     */
    public function __construct(string|false $sql = false, bool $calc_positions = false, array $options = [])
    {
        $this->options = new Options($options);
        if ($sql) {
            $this->parse($sql, $calc_positions);
        }
    }

    /**
     * Parses an SQL statement into a structured array representation.
     *
     * Returns an associative array where each top-level key corresponds to a
     * SQL clause (SELECT, FROM, WHERE, etc.) and each value is an ordered
     * list of expression nodes describing that clause's contents.
     *
     * When $calc_positions is true, every node receives a 'position' key with
     * the byte offset of that token within the original SQL string. This adds
     * an O(n) position-calculation pass after parsing.
     *
     * @param string $sql            SQL statement to parse (single or multi-statement)
     * @param bool   $calc_positions Whether to annotate nodes with character positions
     *
     * @return array<string, array<int, mixed>> Parsed SQL as a clause-keyed associative array
     *
     * @complexity O(n) where n is the length of the SQL string
     */
    public function parse(string $sql, bool $calc_positions = false): array
    {
        $processor = new Default_Processor($this->options);
        $queries = $processor->process($sql);
        // calc the positions of some important tokens
        if ($calc_positions) {
            $calculator = new Position_Calculator();
            $queries = $calculator->set_positions_within_sql($sql, $queries);
        }
        // store the parsed queries
        $this->parsed = $queries;
        return $this->parsed;
    }
    /**
     * Registers a custom function name so the parser recognises it as a function call.
     *
     * Call this before parse() if your SQL uses UDFs or stored-function names that
     * the built-in keyword list does not include.
     *
     * @param string $token The function name to register (case-insensitive)
     *
     * @return void
     */
    public function add_custom_function(string $token): void
    {
        Phpsql_Parser_Constants::get_instance()->add_custom_function($token);
    }

    /**
     * Removes a previously-registered custom function name.
     *
     * After calling this, the parser will no longer treat $token as a function call
     * and may misclassify it depending on context.
     *
     * @param string $token The function name to deregister (case-insensitive)
     *
     * @return void
     */
    public function remove_custom_function(string $token): void
    {
        Phpsql_Parser_Constants::get_instance()->remove_custom_function($token);
    }

    /**
     * Returns the list of all registered custom function names.
     *
     * @return string[] Custom function tokens registered via add_custom_function()
     */
    public function get_custom_functions(): array
    {
        return Phpsql_Parser_Constants::get_instance()->get_custom_functions();
    }
}