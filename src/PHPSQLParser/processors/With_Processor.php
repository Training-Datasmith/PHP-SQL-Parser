<?php

declare (strict_types=1);
/**
 * WithProcessor.php
 *
 * This file implements the processor for Oracle's WITH statements.
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
 * This class processes Oracle's WITH statements.
 *
 * @author  André Rothe <andre.rothe@phosco.info>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *
 */
class With_Processor extends Abstract_Processor
{
    protected function process_top_level($sql)
    {
        $processor = new Default_Processor($this->options);
        return $processor->process($sql);
    }
    protected function build_table_name($token)
    {
        return ['expr_type' => Expression_Type::TEMPORARY_TABLE, 'name' => $token, 'base_expr' => $token, 'no_quotes' => $this->revoke_quotation($token)];
    }
    public function process($tokens)
    {
        $out = [];
        $result_list = [];
        $category = '';
        $base_expr = '';
        $prev = '';
        foreach ($tokens as $token) {
            $base_expr .= $token;
            $upper = strtoupper(trim($token));
            if ($this->is_whitespace_token($token)) {
                continue;
            }
            $trim = trim($token);
            switch ($upper) {
                case 'AS':
                    if ($prev !== 'TABLENAME') {
                        // error or tablename is AS
                        $result_list[] = $this->build_table_name($trim);
                        $category = 'TABLENAME';
                        break;
                    }
                    $result_list[] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                    $category = $upper;
                    break;
                case ',':
                    // ignore
                    $base_expr = '';
                    break;
                default:
                    switch ($prev) {
                        case 'AS':
                            // it follows a parentheses pair
                            $subtree = $this->process_top_level($this->remove_parenthesis_from_start($token));
                            $result_list[] = ['expr_type' => Expression_Type::BRACKET_EXPRESSION, 'base_expr' => $trim, 'sub_tree' => $subtree];
                            $out[] = ['expr_type' => Expression_Type::SUBQUERY_FACTORING, 'base_expr' => trim($base_expr), 'sub_tree' => $result_list];
                            $result_list = [];
                            $category = '';
                            break;
                        case '':
                            // we have the name of the table
                            $result_list[] = $this->build_table_name($trim);
                            $category = 'TABLENAME';
                            break;
                        default:
                            // ignore
                            break;
                    }
                    break;
            }
            $prev = $category;
        }
        return $out;
    }
}