<?php

declare (strict_types=1);
/**
 * WhereProcessor.php
 *
 * This file implements the processor for the UNION statements.
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

/**
 * This class processes the UNION statements.
 *
 * @author  André Rothe <andre.rothe@phosco.info>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *
 */
class Union_Processor extends Abstract_Processor
{
    protected function process_default($token)
    {
        $processor = new Default_Processor($this->options);
        return $processor->process($token);
    }
    protected function process_sql($token)
    {
        $processor = new Sql_Processor($this->options);
        return $processor->process($token);
    }
    public static function is_union(array $queries)
    {
        $union_types = ['UNION', 'UNION ALL'];
        foreach ($union_types as $union_type) {
            if (!empty($queries[$union_type])) {
                return true;
            }
        }
        return false;
    }
    /**
     * MySQL supports a special form of UNION:
     * (select ...)
     * union
     * (select ...)
     *
     * This function handles this query syntax. Only one such subquery
     * is supported in each UNION block. (select)(select)union(select) is not legal.
     * The extra queries will be silently ignored.
     */
    protected function process_my_sql_union(array $queries)
    {
        $union_types = ['UNION', 'UNION ALL'];
        foreach ($union_types as $union_type) {
            if (empty($queries[$union_type])) {
                continue;
            }
            foreach ($queries[$union_type] as $key => $token_list) {
                foreach ($token_list as $token) {
                    $token = trim($token);
                    if ($token === '') {
                        continue;
                    }
                    // starts with "(select"
                    if (preg_match('/^\(\s*select\s*/i', $token)) {
                        $queries[$union_type][$key] = $this->process_default($this->remove_parenthesis_from_start($token));
                        break;
                    }
                    $queries[$union_type][$key] = $this->process_sql($queries[$union_type][$key]);
                    break;
                }
            }
        }
        // it can be parsed or not
        return $queries;
    }
    /**
     * Moves the final union query into a separate output, so the remainder (such as ORDER BY) can
     * be processed separately.
     */
    protected function split_union_remainder(array $queries, $union_type, array $output_array)
    {
        $final_query = [];
        //If this token contains a matching pair of brackets at the start and end, use it as the final query
        $final_query_found = false;
        if (count($output_array) === 1) {
            $token_as_array = str_split(trim($output_array[0]));
            if ($token_as_array[0] == '(' && $token_as_array[count($token_as_array) - 1] == ')') {
                $queries[$union_type][] = $output_array;
                $final_query_found = true;
            }
        }
        if (!$final_query_found) {
            foreach ($output_array as $key => $token) {
                if (strtoupper($token) == 'ORDER') {
                    break;
                } else {
                    $final_query[] = $token;
                    unset($output_array[$key]);
                }
            }
        }
        $final_query_string = trim(implode('', $final_query));
        if (!empty($final_query) && $final_query_string != '') {
            $queries[$union_type][] = $final_query;
        }
        $default_processor = new Default_Processor($this->options);
        $re_prepare_sql_string = trim(implode('', $output_array));
        if (!empty($re_prepare_sql_string)) {
            $remaining_queries = $default_processor->process($re_prepare_sql_string);
            $queries[] = $remaining_queries;
        }
        return $queries;
    }
    public function process($input_array)
    {
        $output_array = [];
        // ometimes the parser needs to skip ahead until a particular
        // oken is found
        $skip_until_token = false;
        // his is the last type of union used (UNION or UNION ALL)
        // ndicates a) presence of at least one union in this query
        // b) the type of union if this is the first or last query
        $union_type = false;
        // ometimes a "query" consists of more than one query (like a UNION query)
        // his array holds all the queries
        $queries = [];
        foreach ($input_array as $key => $token) {
            $trim = trim($token);
            // overread all tokens till that given token
            if ($skip_until_token) {
                if ($trim === '') {
                    continue;
                    // read the next token
                }
                if (strtoupper($trim) === $skip_until_token) {
                    $skip_until_token = false;
                    continue;
                    // read the next token
                }
            }
            if (strtoupper($trim) !== 'UNION') {
                $output_array[] = $token;
                // here we get empty tokens, if we remove these, we get problems in parse_sql()
                continue;
            }
            $union_type = 'UNION';
            // we are looking for an ALL token right after UNION
            for ($i = $key + 1; $i < count($input_array); ++$i) {
                if (trim($input_array[$i]) === '') {
                    continue;
                }
                if (strtoupper($input_array[$i]) !== 'ALL') {
                    break;
                }
                // the other for-loop should overread till "ALL"
                $skip_until_token = 'ALL';
                $union_type = 'UNION ALL';
            }
            // store the tokens related to the unionType
            $queries[$union_type][] = $output_array;
            $output_array = [];
        }
        // the query tokens after the last UNION or UNION ALL
        // or we don't have an UNION/UNION ALL
        if (!empty($output_array)) {
            if ($union_type) {
                $queries = $this->split_union_remainder($queries, $union_type, $output_array);
            } else {
                $queries[] = $output_array;
            }
        }
        return $this->process_my_sql_union($queries);
    }
}