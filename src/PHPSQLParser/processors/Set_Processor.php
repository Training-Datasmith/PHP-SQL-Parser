<?php

declare (strict_types=1);
/**
 * SetProcessor.php
 *
 * This file implements the processor for the SET statements.
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
 * This class processes the SET statements.
 *
 * @author arothe
 *
 */
class Set_Processor extends Abstract_Processor
{
    protected function process_expression_list($tokens)
    {
        $processor = new Expression_List_Processor($this->options);
        return $processor->process($tokens);
    }
    /**
     * A SET list is simply a list of key = value expressions separated by comma (,).
     * This function produces a list of the key/value expressions.
     */
    protected function process_assignment($base_expr)
    {
        $assignment = $this->process_expression_list($this->split_sql_into_tokens($base_expr));
        // TODO: if the left side of the assignment is a reserved keyword, it should be changed to colref
        return ['expr_type' => Expression_Type::EXPRESSION, 'base_expr' => trim($base_expr), 'sub_tree' => empty($assignment) ? false : $assignment];
    }
    public function process($tokens, $is_update = false)
    {
        $result = [];
        $base_expr = '';
        $assignment = false;
        $var_type = false;
        foreach ($tokens as $token) {
            $trim = trim($token);
            $upper = strtoupper($trim);
            switch ($upper) {
                case 'LOCAL':
                case 'SESSION':
                case 'GLOBAL':
                    if (!$is_update) {
                        $result[] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                        $var_type = $this->get_variable_type('@@' . $upper . '.');
                        $base_expr = '';
                        continue 2;
                    }
                    break;
                case ',':
                    $assignment = $this->process_assignment($base_expr);
                    if (!$is_update && $var_type !== false) {
                        $assignment['sub_tree'][0]['expr_type'] = $var_type;
                    }
                    $result[] = $assignment;
                    $base_expr = '';
                    $var_type = false;
                    continue 2;
                default:
            }
            $base_expr .= $token;
        }
        if (trim($base_expr) !== '') {
            $assignment = $this->process_assignment($base_expr);
            if (!$is_update && $var_type !== false) {
                $assignment['sub_tree'][0]['expr_type'] = $var_type;
            }
            $result[] = $assignment;
        }
        return $result;
    }
}