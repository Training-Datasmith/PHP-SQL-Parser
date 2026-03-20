<?php

declare (strict_types=1);
/**
 * OrderByProcessor.php
 *
 * This file implements the processor for the ORDER-BY statements.
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
 * This class processes the ORDER-BY statements.
 *
 * @author  André Rothe <andre.rothe@phosco.info>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *
 */
class Order_By_Processor extends Abstract_Processor
{
    protected function process_select_expression($unparsed)
    {
        $processor = new Select_Expression_Processor($this->options);
        return $processor->process($unparsed);
    }
    protected function init_parse_info()
    {
        return ['base_expr' => '', 'dir' => 'ASC', 'expr_type' => Expression_Type::EXPRESSION];
    }
    protected function process_order_expression(array &$parse_info, $select)
    {
        $parse_info['base_expr'] = trim($parse_info['base_expr']);
        if ($parse_info['base_expr'] === '') {
            return false;
        }
        if (is_numeric($parse_info['base_expr'])) {
            $parse_info['expr_type'] = Expression_Type::POSITION;
        } else {
            $parse_info['no_quotes'] = $this->revoke_quotation($parse_info['base_expr']);
            // search to see if the expression matches an alias
            foreach ($select as $clause) {
                if (empty($clause['alias'])) {
                    continue;
                }
                if ($clause['alias']['no_quotes'] === $parse_info['no_quotes']) {
                    $parse_info['expr_type'] = Expression_Type::ALIAS;
                    break;
                }
            }
        }
        if ($parse_info['expr_type'] === Expression_Type::EXPRESSION) {
            $expr = $this->process_select_expression($parse_info['base_expr']);
            $expr['direction'] = $parse_info['dir'];
            unset($expr['alias']);
            return $expr;
        }
        $result = [];
        $result['expr_type'] = $parse_info['expr_type'];
        $result['base_expr'] = $parse_info['base_expr'];
        if (isset($parse_info['no_quotes'])) {
            $result['no_quotes'] = $parse_info['no_quotes'];
        }
        $result['direction'] = $parse_info['dir'];
        return $result;
    }
    public function process($tokens, $select = [])
    {
        $out = [];
        $parse_info = $this->init_parse_info();
        if (!$tokens) {
            return false;
        }
        foreach ($tokens as $token) {
            $upper = strtoupper(trim($token));
            switch ($upper) {
                case ',':
                    $out[] = $this->process_order_expression($parse_info, $select);
                    $parse_info = $this->init_parse_info();
                    break;
                case 'DESC':
                    $parse_info['dir'] = 'DESC';
                    break;
                case 'ASC':
                    $parse_info['dir'] = 'ASC';
                    break;
                default:
                    if ($this->is_comment_token($token)) {
                        $out[] = parent::process_comment($token);
                        break;
                    }
                    $parse_info['base_expr'] .= $token;
            }
        }
        $out[] = $this->process_order_expression($parse_info, $select);
        return $out;
    }
}