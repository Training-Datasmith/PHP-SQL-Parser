<?php

declare (strict_types=1);
/**
 * ValuesProcessor.php
 *
 * This file implements the processor for the VALUES statements.
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

use Phpsql_Parser\utils\Expression_Type;
/**
 * This class processes the VALUES statements.
 *
 * @author  André Rothe <andre.rothe@phosco.info>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *
 */
class Values_Processor extends Abstract_Processor
{
    protected function process_expression_list($unparsed)
    {
        $processor = new Expression_List_Processor($this->options);
        return $processor->process($unparsed);
    }
    protected function process_record($unparsed)
    {
        $processor = new Record_Processor($this->options);
        return $processor->process($unparsed);
    }
    public function process($tokens)
    {
        $curr_category = '';
        $parsed = [];
        $base_expr = '';
        foreach ($tokens['VALUES'] as $v) {
            if ($this->is_comment_token($v)) {
                $parsed[] = parent::process_comment($v);
                continue;
            }
            $base_expr .= $v;
            $trim = trim($v);
            if ($this->is_whitespace_token($v)) {
                continue;
            }
            $upper = strtoupper($trim);
            switch ($upper) {
                case 'ON':
                    if ($curr_category === '') {
                        $base_expr = trim(substr($base_expr, 0, -strlen($v)));
                        $parsed[] = ['expr_type' => Expression_Type::RECORD, 'base_expr' => $base_expr, 'data' => $this->process_record($base_expr), 'delim' => false];
                        $base_expr = '';
                        $curr_category = 'DUPLICATE';
                        $parsed[] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                    }
                    // else ?
                    break;
                case 'DUPLICATE':
                case 'KEY':
                case 'UPDATE':
                    if ($curr_category === 'DUPLICATE') {
                        $parsed[] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                        $base_expr = '';
                    }
                    // else ?
                    break;
                case ',':
                    if ($curr_category === 'DUPLICATE') {
                        $base_expr = trim(substr($base_expr, 0, -strlen($v)));
                        $res = $this->process_expression_list($this->split_sql_into_tokens($base_expr));
                        $parsed[] = ['expr_type' => Expression_Type::EXPRESSION, 'base_expr' => $base_expr, 'sub_tree' => empty($res) ? false : $res, 'delim' => $trim];
                        $base_expr = '';
                        continue 2;
                    }
                    $parsed[] = ['expr_type' => Expression_Type::RECORD, 'base_expr' => trim($base_expr), 'data' => $this->process_record(trim($base_expr)), 'delim' => $trim];
                    $base_expr = '';
                    break;
                default:
                    break;
            }
        }
        if (trim($base_expr) !== '') {
            if ($curr_category === '') {
                $parsed[] = ['expr_type' => Expression_Type::RECORD, 'base_expr' => trim($base_expr), 'data' => $this->process_record(trim($base_expr)), 'delim' => false];
            }
            if ($curr_category === 'DUPLICATE') {
                $res = $this->process_expression_list($this->split_sql_into_tokens($base_expr));
                $parsed[] = ['expr_type' => Expression_Type::EXPRESSION, 'base_expr' => trim($base_expr), 'sub_tree' => empty($res) ? false : $res, 'delim' => false];
            }
        }
        $tokens['VALUES'] = $parsed;
        return $tokens;
    }
}