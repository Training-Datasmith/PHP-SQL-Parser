<?php

declare (strict_types=1);
/**
 * ExpressionListProcessor.php
 *
 * This file implements the processor for expression lists.
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

use Phpsql_Parser\utils\Expression_Token;
use Phpsql_Parser\utils\Expression_Type;
use Phpsql_Parser\utils\Phpsql_Parser_Constants;
/**
 * This class processes expression lists.
 *
 * @author  André Rothe <andre.rothe@phosco.info>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *
 */
class Expression_List_Processor extends Abstract_Processor
{
    public function process($tokens)
    {
        $result_list = [];
        $skip_next = false;
        $prev = new Expression_Token();
        foreach ($tokens as $k => $v) {
            if ($this->is_comment_token($v)) {
                $result_list[] = parent::process_comment($v);
                continue;
            }
            $curr = new Expression_Token($k, $v);
            if ($curr->is_whitespace_token()) {
                continue;
            }
            if ($skip_next) {
                // skip the next non-whitespace token
                $skip_next = false;
                continue;
            }
            /* is it a subquery? */
            if ($curr->is_sub_query_token()) {
                $processor = new Default_Processor($this->options);
                $curr->set_sub_tree($processor->process($this->remove_parenthesis_from_start($curr->get_trim())));
                $curr->set_token_type(Expression_Type::SUBQUERY);
            } elseif ($curr->is_enclosed_within_parenthesis()) {
                /* is it an in-list? */
                $local_token_list = $this->split_sql_into_tokens($this->remove_parenthesis_from_start($curr->get_trim()));
                if ($prev->get_upper() === 'IN') {
                    foreach ($local_token_list as $k => $v) {
                        $tmp_token = new Expression_Token($k, $v);
                        if ($tmp_token->is_comma_token()) {
                            unset($local_token_list[$k]);
                        }
                    }
                    $local_token_list = array_values($local_token_list);
                    $curr->set_sub_tree($this->process($local_token_list));
                    $curr->set_token_type(Expression_Type::IN_LIST);
                } elseif ($prev->get_upper() === 'AGAINST') {
                    $match_mode = false;
                    foreach ($local_token_list as $k => $v) {
                        $tmp_token = new Expression_Token($k, $v);
                        switch ($tmp_token->get_upper()) {
                            case 'WITH':
                                $match_mode = 'WITH QUERY EXPANSION';
                                break;
                            case 'IN':
                                $match_mode = 'IN BOOLEAN MODE';
                                break;
                            default:
                        }
                        if ($match_mode !== false) {
                            unset($local_token_list[$k]);
                        }
                    }
                    $tmp_token = $this->process($local_token_list);
                    if ($match_mode !== false) {
                        $match_mode = new Expression_Token(0, $match_mode);
                        $match_mode->set_token_type(Expression_Type::MATCH_MODE);
                        $tmp_token[] = $match_mode->to_array();
                    }
                    $curr->set_sub_tree($tmp_token);
                    $curr->set_token_type(Expression_Type::MATCH_ARGUMENTS);
                    $prev->set_token_type(Expression_Type::SIMPLE_FUNCTION);
                } elseif ($prev->is_column_reference() || $prev->is_function() || $prev->is_aggregate_function() || $prev->is_custom_function()) {
                    // if we have a colref followed by a parenthesis pair,
                    // it isn't a colref, it is a user-function
                    // TODO: this should be a method, because we need the same code
                    // below for unspecified tokens (expressions).
                    $local_expr = new Expression_Token();
                    $tmp_expr_list = [];
                    foreach ($local_token_list as $k => $v) {
                        $tmp_token = new Expression_Token($k, $v);
                        if (!$tmp_token->is_comma_token()) {
                            $local_expr->add_token($v);
                            $tmp_expr_list[] = $v;
                        } else {
                            // an expression could have multiple parts split by operands
                            // if we have a comma, it is a split-point for expressions
                            $tmp_expr_list = array_values($tmp_expr_list);
                            $local_expr_list = $this->process($tmp_expr_list);
                            if (count($local_expr_list) > 1) {
                                $local_expr->set_sub_tree($local_expr_list);
                                $local_expr->set_token_type(Expression_Type::EXPRESSION);
                                $local_expr_list = $local_expr->to_array();
                                $local_expr_list['alias'] = false;
                                $local_expr_list = [$local_expr_list];
                            }
                            if (!$curr->get_sub_tree()) {
                                if (!empty($local_expr_list)) {
                                    $curr->set_sub_tree($local_expr_list);
                                }
                            } else {
                                $tmp_expr_list = $curr->get_sub_tree();
                                $curr->set_sub_tree(array_merge($tmp_expr_list, $local_expr_list));
                            }
                            $tmp_expr_list = [];
                            $local_expr = new Expression_Token();
                        }
                    }
                    $tmp_expr_list = array_values($tmp_expr_list);
                    $local_expr_list = $this->process($tmp_expr_list);
                    if (count($local_expr_list) > 1) {
                        $local_expr->set_sub_tree($local_expr_list);
                        $local_expr->set_token_type(Expression_Type::EXPRESSION);
                        $local_expr_list = $local_expr->to_array();
                        $local_expr_list['alias'] = false;
                        $local_expr_list = [$local_expr_list];
                    }
                    if (!$curr->get_sub_tree()) {
                        if (!empty($local_expr_list)) {
                            $curr->set_sub_tree($local_expr_list);
                        }
                    } else {
                        $tmp_expr_list = $curr->get_sub_tree();
                        $curr->set_sub_tree(array_merge($tmp_expr_list, $local_expr_list));
                    }
                    $prev->set_sub_tree($curr->get_sub_tree());
                    if ($prev->is_column_reference()) {
                        if (Phpsql_Parser_Constants::get_instance()->is_custom_function($prev->get_upper())) {
                            $prev->set_token_type(Expression_Type::CUSTOM_FUNCTION);
                        } else {
                            $prev->set_token_type(Expression_Type::SIMPLE_FUNCTION);
                        }
                        $prev->set_no_quotes(null, null, $this->options);
                    }
                    array_pop($result_list);
                    $curr = $prev;
                }
                // we have parenthesis, but it seems to be an expression
                if ($curr->is_unspecified()) {
                    $tmp_expr_list = array_values($local_token_list);
                    $local_expr_list = $this->process($tmp_expr_list);
                    $curr->set_token_type(Expression_Type::BRACKET_EXPRESSION);
                    if (!$curr->get_sub_tree()) {
                        if (!empty($local_expr_list)) {
                            $curr->set_sub_tree($local_expr_list);
                        }
                    } else {
                        $tmp_expr_list = $curr->get_sub_tree();
                        $curr->set_sub_tree(array_merge($tmp_expr_list, $local_expr_list));
                    }
                }
            } elseif ($curr->is_variable_token()) {
                # a variable
                # it can be quoted
                $curr->set_token_type($this->get_variable_type($curr->get_upper()));
                $curr->set_sub_tree(false);
                $curr->set_no_quotes(trim(trim($curr->get_token()), '@'), "`'\"", $this->options);
            } else {
                /* it is either an operator, a colref or a constant */
                switch ($curr->get_upper()) {
                    case '*':
                        $curr->set_sub_tree(false);
                        // o subtree
                        // single or first element of expression list -> all-column-alias
                        if (empty($result_list)) {
                            $curr->set_token_type(Expression_Type::COLREF);
                            break;
                        }
                        // if the last token is colref, const or expression
                        // then * is an operator
                        // but if the previous colref ends with a dot, the * is the all-columns-alias
                        if (!$prev->is_column_reference() && !$prev->is_constant() && !$prev->is_expression() && !$prev->is_bracket_expression() && !$prev->is_aggregate_function() && !$prev->is_variable() && !$prev->is_function()) {
                            $curr->set_token_type(Expression_Type::COLREF);
                            break;
                        }
                        if ($prev->is_column_reference() && $prev->ends_with('.')) {
                            $prev->add_token('*');
                            // tablealias dot *
                            continue 2;
                            // skip the current token
                        }
                        $curr->set_token_type(Expression_Type::OPERATOR);
                        break;
                    case ':=':
                    case 'AND':
                    case '&&':
                    case 'BETWEEN':
                    case 'BINARY':
                    case '&':
                    case '~':
                    case '|':
                    case '^':
                    case 'DIV':
                    case '/':
                    case '<=>':
                    case '=':
                    case '>=':
                    case '>':
                    case 'IS':
                    case 'NOT':
                    case '<<':
                    case '<=':
                    case '<':
                    case 'LIKE':
                    case '%':
                    case '!=':
                    case '<>':
                    case 'REGEXP':
                    case '!':
                    case '||':
                    case 'OR':
                    case '>>':
                    case 'RLIKE':
                    case 'SOUNDS':
                    case 'XOR':
                    case 'IN':
                        $curr->set_sub_tree(false);
                        $curr->set_token_type(Expression_Type::OPERATOR);
                        break;
                    case 'NULL':
                        $curr->set_sub_tree(false);
                        $curr->set_token_type(Expression_Type::CONSTANT);
                        break;
                    case '-':
                    case '+':
                        // differ between preceding sign and operator
                        $curr->set_sub_tree(false);
                        if ($prev->is_column_reference() || $prev->is_function() || $prev->is_aggregate_function() || $prev->is_constant() || $prev->is_sub_query() || $prev->is_expression() || $prev->is_bracket_expression() || $prev->is_variable() || $prev->is_custom_function()) {
                            $curr->set_token_type(Expression_Type::OPERATOR);
                        } else {
                            $curr->set_token_type(Expression_Type::SIGN);
                        }
                        break;
                    default:
                        $curr->set_sub_tree(false);
                        switch ($curr->get_token(0)) {
                            case "'":
                                // it is a string literal
                                $curr->set_token_type(Expression_Type::CONSTANT);
                                break;
                            case '"':
                                if (!$this->options->get_ansi_quotes()) {
                                    // If we're not using ANSI quotes, this is a string literal.
                                    $curr->set_token_type(Expression_Type::CONSTANT);
                                    break;
                                }
                            // Otherwise continue to the next case
                            // no break
                            case '`':
                                // it is an escaped colum name
                                $curr->set_token_type(Expression_Type::COLREF);
                                $curr->set_no_quotes($curr->get_token(), null, $this->options);
                                break;
                            default:
                                if (is_numeric($curr->get_token())) {
                                    if ($prev->is_sign()) {
                                        $prev->add_token($curr->get_token());
                                        // it is a negative numeric constant
                                        $prev->set_token_type(Expression_Type::CONSTANT);
                                        continue 3;
                                        // skip current token
                                    } else {
                                        $curr->set_token_type(Expression_Type::CONSTANT);
                                    }
                                } else {
                                    $curr->set_token_type(Expression_Type::COLREF);
                                    $curr->set_no_quotes($curr->get_token(), null, $this->options);
                                }
                                break;
                        }
                }
            }
            /* is a reserved word? */
            if (!$curr->is_operator() && !$curr->is_in_list() && !$curr->is_function() && !$curr->is_aggregate_function() && !$curr->is_custom_function() && Phpsql_Parser_Constants::get_instance()->is_reserved($curr->get_upper())) {
                $next = isset($tokens[$k + 1]) ? new Expression_Token($k + 1, $tokens[$k + 1]) : new Expression_Token();
                $is_enclosed_within_parenthesis = $next->is_enclosed_within_parenthesis();
                if ($is_enclosed_within_parenthesis && Phpsql_Parser_Constants::get_instance()->is_custom_function($curr->get_upper())) {
                    $curr->set_token_type(Expression_Type::CUSTOM_FUNCTION);
                    $curr->set_no_quotes(null, null, $this->options);
                } elseif ($is_enclosed_within_parenthesis && Phpsql_Parser_Constants::get_instance()->is_aggregate_function($curr->get_upper())) {
                    $curr->set_token_type(Expression_Type::AGGREGATE_FUNCTION);
                    $curr->set_no_quotes(null, null, $this->options);
                } elseif ($curr->get_upper() === 'NULL') {
                    // it is a reserved word, but we would like to set it as constant
                    $curr->set_token_type(Expression_Type::CONSTANT);
                } else if ($is_enclosed_within_parenthesis && Phpsql_Parser_Constants::get_instance()->is_parameterized_function($curr->get_upper())) {
                    // issue 60: check functions with parameters
                    // -> colref (we check parameters later)
                    // -> if there is no parameter, we leave the colref
                    $curr->set_token_type(Expression_Type::COLREF);
                } elseif ($is_enclosed_within_parenthesis && Phpsql_Parser_Constants::get_instance()->is_function($curr->get_upper())) {
                    $curr->set_token_type(Expression_Type::SIMPLE_FUNCTION);
                    $curr->set_no_quotes(null, null, $this->options);
                } elseif (!$is_enclosed_within_parenthesis && Phpsql_Parser_Constants::get_instance()->is_function($curr->get_upper())) {
                    // Colname using function name.
                    $curr->set_token_type(Expression_Type::COLREF);
                } else {
                    $curr->set_token_type(Expression_Type::RESERVED);
                    $curr->set_no_quotes(null, null, $this->options);
                }
            }
            // issue 94, INTERVAL 1 MONTH
            if ($curr->is_constant() && Phpsql_Parser_Constants::get_instance()->is_parameterized_function($prev->get_upper())) {
                $prev->set_token_type(Expression_Type::RESERVED);
                $prev->set_no_quotes(null, null, $this->options);
            }
            if ($prev->is_constant() && Phpsql_Parser_Constants::get_instance()->is_parameterized_function($curr->get_upper())) {
                $curr->set_token_type(Expression_Type::RESERVED);
                $curr->set_no_quotes(null, null, $this->options);
            }
            if ($curr->is_unspecified()) {
                $curr->set_token_type(Expression_Type::EXPRESSION);
                $curr->set_no_quotes(null, null, $this->options);
                $curr->set_sub_tree($this->process($this->split_sql_into_tokens($curr->get_trim())));
            }
            $result_list[] = $curr;
            $prev = $curr;
        }
        // end of for-loop
        return $this->to_array($result_list);
    }
}