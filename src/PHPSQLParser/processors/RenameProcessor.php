<?php

declare (strict_types=1);
/**
 * RenameProcessor.php
 *
 * This file implements the processor for the RENAME statements.
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
/**
 * This class processes the RENAME statements.
 *
 * @author  André Rothe <andre.rothe@phosco.info>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *
 */
class Rename_Processor extends Abstract_Processor
{
    public function process($token_list)
    {
        $base_expr = '';
        $result_list = [];
        $table_pair = [];
        foreach ($token_list as $k => $v) {
            $token = new Expression_Token($k, $v);
            if ($token->is_whitespace_token()) {
                continue;
            }
            switch ($token->get_upper()) {
                case 'TO':
                    // separate source table from destination
                    $table_pair['source'] = ['expr_type' => Expression_Type::TABLE, 'table' => trim($base_expr), 'no_quotes' => $this->revoke_quotation($base_expr), 'base_expr' => $base_expr];
                    $base_expr = '';
                    break;
                case ',':
                    // split rename operations
                    $table_pair['destination'] = ['expr_type' => Expression_Type::TABLE, 'table' => trim($base_expr), 'no_quotes' => $this->revoke_quotation($base_expr), 'base_expr' => $base_expr];
                    $result_list[] = $table_pair;
                    $table_pair = [];
                    $base_expr = '';
                    break;
                case 'TABLE':
                    $object_type = Expression_Type::TABLE;
                    $result_list[] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $token->get_trim()];
                    continue 2;
                default:
                    $base_expr .= $token->get_token();
                    break;
            }
        }
        if ($base_expr !== '') {
            $table_pair['destination'] = ['expr_type' => Expression_Type::TABLE, 'table' => trim($base_expr), 'no_quotes' => $this->revoke_quotation($base_expr), 'base_expr' => $base_expr];
            $result_list[] = $table_pair;
        }
        return ['expr_type' => $object_type, 'sub_tree' => $result_list];
    }
}