<?php

declare (strict_types=1);
/**
 * FromProcessor.php
 *
 * This file implements the processor for the FROM statement.
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
 * @author    George Schneeloch <noisecapella@gmail.com>
 * @copyright 2010-2014 Justin Swanhart and André Rothe
 * @license   http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 * @version   SVN: $Id$
 *
 */
namespace Phpsql_Parser\processors;

use Phpsql_Parser\utils\Expression_Type;
/**
 * This class processes the FROM statement.
 *
 * @author  André Rothe <andre.rothe@phosco.info>
 * @author  Marco Th. <marco64th@gmail.com>
 * @author  George Schneeloch <noisecapella@gmail.com>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *
 */
class From_Processor extends Abstract_Processor
{
    protected function process_expression_list($unparsed)
    {
        $processor = new Expression_List_Processor($this->options);
        return $processor->process($unparsed);
    }
    protected function process_column_list($unparsed)
    {
        $processor = new Column_List_Processor($this->options);
        return $processor->process($unparsed);
    }
    protected function process_sql_default($unparsed)
    {
        $processor = new Default_Processor($this->options);
        return $processor->process($unparsed);
    }
    protected function init_parse_info($parse_info = false)
    {
        // first init
        if ($parse_info === false) {
            $parse_info = ['join_type' => '', 'saved_join_type' => 'JOIN'];
        }
        // loop init
        return ['expression' => '', 'token_count' => 0, 'table' => '', 'no_quotes' => '', 'alias' => false, 'hints' => [], 'join_type' => '', 'next_join_type' => '', 'saved_join_type' => $parse_info['saved_join_type'], 'ref_type' => false, 'ref_expr' => false, 'base_expr' => false, 'sub_tree' => false, 'subquery' => ''];
    }
    protected function process_from_expression(array &$parse_info)
    {
        $res = [];
        if ($parse_info['hints'] === []) {
            $parse_info['hints'] = false;
        }
        // exchange the join types (join_type is save now, saved_join_type holds the next one)
        $parse_info['join_type'] = $parse_info['saved_join_type'];
        // initialized with JOIN
        $parse_info['saved_join_type'] = $parse_info['next_join_type'] ?: 'JOIN';
        // we have a reg_expr, so we have to parse it
        if ($parse_info['ref_expr'] !== false) {
            $unparsed = $this->split_sql_into_tokens(trim($parse_info['ref_expr']));
            // here we can get a comma separated list
            foreach ($unparsed as $k => $v) {
                if ($this->is_comma_token($v)) {
                    $unparsed[$k] = '';
                }
            }
            if ($parse_info['ref_type'] === 'USING') {
                // unparsed has only one entry, the column list
                $ref = $this->process_column_list($this->remove_parenthesis_from_start($unparsed[0]));
                $ref = [['expr_type' => Expression_Type::COLUMN_LIST, 'base_expr' => $unparsed[0], 'sub_tree' => $ref]];
            } else {
                $ref = $this->process_expression_list($unparsed);
            }
            $parse_info['ref_expr'] = empty($ref) ? false : $ref;
        }
        // there is an expression, we have to parse it
        if (substr(trim($parse_info['table']), 0, 1) == '(') {
            $parse_info['expression'] = $this->remove_parenthesis_from_start($parse_info['table']);
            if (preg_match('/^\s*(-- [\w\s]+\n)?\s*SELECT/i', $parse_info['expression'])) {
                $parse_info['sub_tree'] = $this->process_sql_default($parse_info['expression']);
                $res['expr_type'] = Expression_Type::SUBQUERY;
            } else {
                $tmp = $this->split_sql_into_tokens($parse_info['expression']);
                $union_processor = new Union_Processor($this->options);
                $union_queries = $union_processor->process($tmp);
                // If there was no UNION or UNION ALL in the query, then the query is
                // stored at $queries[0].
                if (!empty($union_queries) && !Union_Processor::is_union($union_queries)) {
                    $sub_tree = $this->process($union_queries[0]);
                } else {
                    $sub_tree = $union_queries;
                }
                $parse_info['sub_tree'] = $sub_tree;
                $res['expr_type'] = Expression_Type::TABLE_EXPRESSION;
            }
        } else {
            $res['expr_type'] = Expression_Type::TABLE;
            $res['table'] = $parse_info['table'];
            $res['no_quotes'] = $this->revoke_quotation($parse_info['table']);
        }
        $res['alias'] = $parse_info['alias'];
        $res['hints'] = $parse_info['hints'];
        $res['join_type'] = $parse_info['join_type'];
        $res['ref_type'] = $parse_info['ref_type'];
        $res['ref_clause'] = $parse_info['ref_expr'];
        $res['base_expr'] = trim($parse_info['expression']);
        $res['sub_tree'] = $parse_info['sub_tree'];
        return $res;
    }
    public function process($tokens)
    {
        $parse_info = $this->init_parse_info();
        $expr = [];
        $token_category = '';
        $prev_token = '';
        $skip_next = false;
        $i = 0;
        foreach ($tokens as $token) {
            $upper = strtoupper(trim($token));
            if ($skip_next && $token !== '') {
                $parse_info['token_count']++;
                $skip_next = false;
                continue;
            }
            if ($skip_next) {
                continue;
            }
            if ($this->is_comment_token($token)) {
                $expr[] = parent::process_comment($token);
                continue;
            }
            switch ($upper) {
                case 'CROSS':
                case ',':
                case 'INNER':
                case 'STRAIGHT_JOIN':
                    break;
                case 'OUTER':
                case 'JOIN':
                    if ($token_category === 'LEFT' || $token_category === 'RIGHT' || $token_category === 'NATURAL') {
                        $token_category = '';
                        $parse_info['next_join_type'] = strtoupper(trim($prev_token));
                        // it seems to be a join
                    } elseif ($token_category === 'IDX_HINT') {
                        $parse_info['expression'] .= $token;
                        if ($parse_info['ref_type'] !== false) {
                            // all after ON / USING
                            $parse_info['ref_expr'] .= $token;
                        }
                    }
                    break;
                case 'LEFT':
                case 'RIGHT':
                case 'NATURAL':
                    $token_category = $upper;
                    $prev_token = $token;
                    $i++;
                    continue 2;
                default:
                    if ($token_category === 'LEFT' || $token_category === 'RIGHT') {
                        if ($upper === '') {
                            $prev_token .= $token;
                            break;
                        } else {
                            $token_category = '';
                            // it seems to be a function
                            $parse_info['expression'] .= $prev_token;
                            if ($parse_info['ref_type'] !== false) {
                                // all after ON / USING
                                $parse_info['ref_expr'] .= $prev_token;
                            }
                            $prev_token = '';
                        }
                    }
                    $parse_info['expression'] .= $token;
                    if ($parse_info['ref_type'] !== false) {
                        // all after ON / USING
                        $parse_info['ref_expr'] .= $token;
                    }
                    break;
            }
            if ($upper === '') {
                $i++;
                continue;
            }
            switch ($upper) {
                case 'AS':
                    $parse_info['alias'] = ['as' => true, 'name' => '', 'base_expr' => $token];
                    $parse_info['token_count']++;
                    $n = 1;
                    $str = '';
                    while ($str === '' && isset($tokens[$i + $n])) {
                        $parse_info['alias']['base_expr'] .= $tokens[$i + $n] === '' ? ' ' : $tokens[$i + $n];
                        $str = trim($tokens[$i + $n]);
                        ++$n;
                    }
                    $parse_info['alias']['name'] = $str;
                    $parse_info['alias']['no_quotes'] = $this->revoke_quotation($str);
                    $parse_info['alias']['base_expr'] = trim($parse_info['alias']['base_expr']);
                    break;
                case 'IGNORE':
                case 'USE':
                case 'FORCE':
                    $token_category = 'IDX_HINT';
                    $parse_info['hints'][]['hint_type'] = $upper;
                    continue 2;
                case 'KEY':
                case 'INDEX':
                    if ($token_category === 'CREATE') {
                        $token_category = $upper;
                        // TODO: what is it for a statement?
                        continue 2;
                    }
                    if ($token_category === 'IDX_HINT') {
                        $cur_hint = count($parse_info['hints']) - 1;
                        $parse_info['hints'][$cur_hint]['hint_type'] .= ' ' . $upper;
                        continue 2;
                    }
                    break;
                case 'USING':
                case 'ON':
                    $parse_info['ref_type'] = $upper;
                    $parse_info['ref_expr'] = '';
                // no break
                case 'CROSS':
                case 'INNER':
                case 'OUTER':
                case 'NATURAL':
                    $parse_info['token_count']++;
                    break;
                case 'FOR':
                    if ($token_category === 'IDX_HINT') {
                        $cur_hint = count($parse_info['hints']) - 1;
                        $parse_info['hints'][$cur_hint]['hint_type'] .= ' ' . $upper;
                        continue 2;
                    }
                    $parse_info['token_count']++;
                    $skip_next = true;
                    break;
                case 'STRAIGHT_JOIN':
                    $parse_info['next_join_type'] = 'STRAIGHT_JOIN';
                    if ($parse_info['subquery']) {
                        $parse_info['sub_tree'] = $this->parse($this->remove_parenthesis_from_start($parse_info['subquery']));
                        $parse_info['expression'] = $parse_info['subquery'];
                    }
                    $expr[] = $this->process_from_expression($parse_info);
                    $parse_info = $this->init_parse_info($parse_info);
                    break;
                case ',':
                    $parse_info['next_join_type'] = 'CROSS';
                // no break
                case 'JOIN':
                    if ($token_category === 'IDX_HINT') {
                        $cur_hint = count($parse_info['hints']) - 1;
                        $parse_info['hints'][$cur_hint]['hint_type'] .= ' ' . $upper;
                        continue 2;
                    }
                    if ($parse_info['subquery']) {
                        $parse_info['sub_tree'] = $this->parse($this->remove_parenthesis_from_start($parse_info['subquery']));
                        $parse_info['expression'] = $parse_info['subquery'];
                    }
                    $expr[] = $this->process_from_expression($parse_info);
                    $parse_info = $this->init_parse_info($parse_info);
                    break;
                case 'GROUP BY':
                    if ($token_category === 'IDX_HINT') {
                        $cur_hint = count($parse_info['hints']) - 1;
                        $parse_info['hints'][$cur_hint]['hint_type'] .= ' ' . $upper;
                        continue 2;
                    }
                // no break
                default:
                    // TODO: enhance it, so we can have base_expr to calculate the position of the keywords
                    // build a subtree under "hints"
                    if ($token_category === 'IDX_HINT') {
                        $token_category = '';
                        $cur_hint = count($parse_info['hints']) - 1;
                        $parse_info['hints'][$cur_hint]['hint_list'] = $token;
                        break;
                    }
                    if ($parse_info['token_count'] === 0) {
                        if ($parse_info['table'] === '') {
                            $parse_info['table'] = $token;
                            $parse_info['no_quotes'] = $this->revoke_quotation($token);
                        }
                    } elseif ($parse_info['token_count'] === 1) {
                        $parse_info['alias'] = ['as' => false, 'name' => trim($token), 'no_quotes' => $this->revoke_quotation($token), 'base_expr' => trim($token)];
                    }
                    $parse_info['token_count']++;
                    break;
            }
            $i++;
        }
        $expr[] = $this->process_from_expression($parse_info);
        return $expr;
    }
}