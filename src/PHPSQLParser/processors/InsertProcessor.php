<?php

declare (strict_types=1);
/**
 * InsertProcessor.php
 *
 * This file implements the processor for the INSERT statements.
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
 * This class processes the INSERT statements.
 *
 * @author  André Rothe <andre.rothe@phosco.info>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *
 */
class Insert_Processor extends Abstract_Processor
{
    protected function process_options(array $token_list)
    {
        if (!isset($token_list['OPTIONS'])) {
            return [];
        }
        $result = [];
        foreach ($token_list['OPTIONS'] as $token) {
            $result[] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => trim($token)];
        }
        return $result;
    }
    protected function process_keyword($keyword, array $token_list)
    {
        if (!isset($token_list[$keyword])) {
            return ['', false, []];
        }
        $table = '';
        $cols = false;
        $result = [];
        foreach ($token_list[$keyword] as $token) {
            $trim = trim($token);
            if ($trim === '') {
                continue;
            }
            $upper = strtoupper($trim);
            switch ($upper) {
                case 'INTO':
                    $result[] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
                    break;
                case 'INSERT':
                case 'REPLACE':
                    break;
                default:
                    if ($table === '') {
                        $table = $trim;
                        break;
                    }
                    if ($cols === false) {
                        $cols = $trim;
                    }
                    break;
            }
        }
        return [$table, $cols, $result];
    }
    protected function process_columns($cols)
    {
        if ($cols === false) {
            return $cols;
        }
        if ($cols[0] === '(' && substr($cols, -1) === ')') {
            $parsed = ['expr_type' => Expression_Type::BRACKET_EXPRESSION, 'base_expr' => $cols, 'sub_tree' => false];
        }
        $cols = $this->remove_parenthesis_from_start($cols);
        if (stripos($cols, 'SELECT') === 0) {
            $processor = new Default_Processor($this->options);
            $parsed['sub_tree'] = [['expr_type' => Expression_Type::QUERY, 'base_expr' => $cols, 'sub_tree' => $processor->process($cols)]];
        } else {
            $processor = new Column_List_Processor($this->options);
            $parsed['sub_tree'] = $processor->process($cols);
            $parsed['expr_type'] = Expression_Type::COLUMN_LIST;
        }
        return $parsed;
    }
    public function process($token_list, $token_category = 'INSERT')
    {
        $table = '';
        $cols = false;
        $comments = [];
        foreach ($token_list as $key => &$token) {
            if ($key == 'VALUES') {
                continue;
            }
            foreach ($token as &$value) {
                if ($this->is_comment_token($value)) {
                    $comments[] = parent::process_comment($value);
                    $value = '';
                }
            }
        }
        $parsed = $this->process_options($token_list);
        unset($token_list['OPTIONS']);
        list($table, $cols, $key) = $this->process_keyword('INTO', $token_list);
        $parsed = array_merge($parsed, $key);
        unset($token_list['INTO']);
        if ($table === '' && in_array($token_category, ['INSERT', 'REPLACE'])) {
            list($table, $cols, $key) = $this->process_keyword($token_category, $token_list);
        }
        $parsed[] = ['expr_type' => Expression_Type::TABLE, 'table' => $table, 'no_quotes' => $this->revoke_quotation($table), 'alias' => false, 'base_expr' => $table];
        $cols = $this->process_columns($cols);
        if ($cols !== false) {
            $parsed[] = $cols;
        }
        $parsed = array_merge($parsed, $comments);
        $token_list[$token_category] = $parsed;
        return $token_list;
    }
}