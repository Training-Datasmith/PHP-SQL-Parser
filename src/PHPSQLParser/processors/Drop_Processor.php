<?php

declare (strict_types=1);
/**
 * DropProcessor.php
 *
 * This file implements the processor for the DROP statements.
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
 * This class processes the DROP statements.
 *
 * @author  André Rothe <andre.rothe@phosco.info>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *
 */
class Drop_Processor extends Abstract_Processor
{
    public function process($token_list)
    {
        $exists = false;
        $base_expr = '';
        $object_type = '';
        $sub_tree = [];
        $option = false;
        foreach ($token_list as $token) {
            $base_expr .= $token;
            $trim = trim($token);
            if ($trim === '') {
                continue;
            }
            $upper = strtoupper($trim);
            switch ($upper) {
                case 'VIEW':
                case 'SCHEMA':
                case 'DATABASE':
                case 'TABLE':
                case 'INDEX':
                    if ($object_type === '') {
                        $object_type = constant('PHPSQLParser\utils\ExpressionType::' . $upper);
                    }
                    $base_expr = '';
                    break;
                case 'IF':
                case 'EXISTS':
                    $exists = true;
                    $base_expr = '';
                    break;
                case 'TEMPORARY':
                    $object_type = Expression_Type::TEMPORARY_TABLE;
                    $base_expr = '';
                    break;
                case 'RESTRICT':
                case 'CASCADE':
                    $option = $upper;
                    if (!empty($object_list)) {
                        $sub_tree[] = ['expr_type' => Expression_Type::EXPRESSION, 'base_expr' => trim(substr($base_expr, 0, -strlen($token))), 'sub_tree' => $object_list];
                        $object_list = [];
                    }
                    $base_expr = '';
                    break;
                case ',':
                    $last = array_pop($object_list);
                    $last['delim'] = $trim;
                    $object_list[] = $last;
                    continue 2;
                default:
                    $object = [];
                    $object['expr_type'] = $object_type;
                    if ($object_type === Expression_Type::TABLE || $object_type === Expression_Type::TEMPORARY_TABLE) {
                        $object['table'] = $trim;
                        $object['no_quotes'] = false;
                        $object['alias'] = false;
                    }
                    $object['base_expr'] = $trim;
                    $object['no_quotes'] = $this->revoke_quotation($trim);
                    $object['delim'] = false;
                    $object_list[] = $object;
                    continue 2;
            }
            $sub_tree[] = ['expr_type' => Expression_Type::RESERVED, 'base_expr' => $trim];
        }
        if (!empty($object_list)) {
            $sub_tree[] = ['expr_type' => Expression_Type::EXPRESSION, 'base_expr' => trim($base_expr), 'sub_tree' => $object_list];
        }
        return ['expr_type' => $object_type, 'option' => $option, 'if-exists' => $exists, 'sub_tree' => $sub_tree];
    }
}