<?php

declare (strict_types=1);
/**
 * ReferenceDefinitionProcessor.php
 *
 * This file implements the processor reference definition part of the CREATE TABLE statements.
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
 * This class processes the reference definition part of the CREATE TABLE statements.
 *
 * @author arothe
 */
class Reference_Definition_Processor extends Abstract_Processor
{
    protected function build_reference_def(array $expr, $base_expr, $key)
    {
        $expr['till'] = $key;
        $expr['base_expr'] = $base_expr;
        return $expr;
    }
    public function process($tokens)
    {
        $expr = ['expr_type' => Expression_Type::REFERENCE, 'base_expr' => false, 'sub_tree' => []];
        $base_expr = '';
        foreach ($tokens as $key => $token) {
            $trim = trim($token);
            $base_expr .= $token;
            if ($trim === '') {
                continue;
            }
            $upper = strtoupper($trim);
            switch ($upper) {
                case ',':
                    # we stop on a single comma
                    # or at the end of the array $tokens
                    $expr = $this->build_reference_def($expr, trim(substr($base_expr, 0, -strlen($token))), $key - 1);
                    break 2;
                case 'REFERENCES':
                    $expr['sub_tree'][] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                    $curr_category = $upper;
                    break;
                case 'MATCH':
                    if ($curr_category === 'REF_COL_LIST') {
                        $expr['sub_tree'][] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                        $curr_category = 'REF_MATCH';
                        continue 2;
                    }
                    # else?
                    break;
                case 'FULL':
                case 'PARTIAL':
                case 'SIMPLE':
                    if ($curr_category === 'REF_MATCH') {
                        $expr['sub_tree'][] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                        $expr['match'] = $upper;
                        $curr_category = 'REF_COL_LIST';
                        continue 2;
                    }
                    # else?
                    break;
                case 'ON':
                    if ($curr_category === 'REF_COL_LIST') {
                        $expr['sub_tree'][] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                        $curr_category = 'REF_ACTION';
                        continue 2;
                    }
                    # else ?
                    break;
                case 'UPDATE':
                case 'DELETE':
                    if ($curr_category === 'REF_ACTION') {
                        $expr['sub_tree'][] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                        $curr_category = 'REF_OPTION_' . $upper;
                        continue 2;
                    }
                    # else ?
                    break;
                case 'RESTRICT':
                case 'CASCADE':
                    if (strpos($curr_category, 'REF_OPTION_') === 0) {
                        $expr['sub_tree'][] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                        $expr['on_' . strtolower(substr($curr_category, -6))] = $upper;
                        continue 2;
                    }
                    # else ?
                    break;
                case 'SET':
                case 'NO':
                    if (strpos($curr_category, 'REF_OPTION_') === 0) {
                        $expr['sub_tree'][] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                        $expr['on_' . strtolower(substr($curr_category, -6))] = $upper;
                        $curr_category = 'SEC_' . $curr_category;
                        continue 2;
                    }
                    # else ?
                    break;
                case 'NULL':
                case 'ACTION':
                    if (strpos($curr_category, 'SEC_REF_OPTION_') === 0) {
                        $expr['sub_tree'][] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                        $expr['on_' . strtolower(substr($curr_category, -6))] .= ' ' . $upper;
                        $curr_category = 'REF_COL_LIST';
                        continue 2;
                    }
                    # else ?
                    break;
                default:
                    switch ($curr_category) {
                        case 'REFERENCES':
                            if ($upper[0] === '(' && substr($upper, -1) === ')') {
                                # index_col_name list
                                $processor = new Index_Column_List_Processor($this->options);
                                $cols = $processor->process($this->remove_parenthesis_from_start($trim));
                                $expr['sub_tree'][] = ['expr_type' => Expression_Type::COLUMN_LIST, 'base_expr' => $trim, 'sub_tree' => $cols];
                                $curr_category = 'REF_COL_LIST';
                                continue 3;
                            }
                            # foreign key reference table name
                            $expr['sub_tree'][] = ['expr_type' => Expression_Type::TABLE, 'table' => $trim, 'base_expr' => $trim, 'no_quotes' => $this->revoke_quotation($trim)];
                            continue 3;
                        default:
                            # else ?
                            break;
                    }
                    break;
            }
        }
        if (!isset($expr['till'])) {
            return $this->build_reference_def($expr, trim($base_expr), -1);
        }
        return $expr;
    }
}