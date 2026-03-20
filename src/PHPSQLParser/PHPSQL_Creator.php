<?php

declare (strict_types=1);
/**
 * PHPSQLCreator.php
 *
 * A creator, which generates SQL from the output of PHPSQLParser.
 *
 * PHP version 5
 *
 * LICENSE:
 * Copyright (c) 2010-2014 André Rothe
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
 * @copyright 2010-2014 André Rothe
 * @license   http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 * @version   SVN: $Id$
 *
 */
namespace Phpsql_Parser;

use Phpsql_Parser\builders\Alter_Statement_Builder;
use Phpsql_Parser\builders\Bracket_Statement_Builder;
use Phpsql_Parser\builders\Create_Statement_Builder;
use Phpsql_Parser\builders\Delete_Statement_Builder;
use Phpsql_Parser\builders\Drop_Statement_Builder;
use Phpsql_Parser\builders\Insert_Statement_Builder;
use Phpsql_Parser\builders\Rename_Statement_Builder;
use Phpsql_Parser\builders\Replace_Statement_Builder;
use Phpsql_Parser\builders\Select_Statement_Builder;
use Phpsql_Parser\builders\Show_Statement_Builder;
use Phpsql_Parser\builders\Truncate_Statement_Builder;
use Phpsql_Parser\builders\Union_All_Statement_Builder;
use Phpsql_Parser\builders\Union_Statement_Builder;
use Phpsql_Parser\builders\Update_Statement_Builder;
use Phpsql_Parser\exceptions\Unsupported_Feature_Exception;
/**
 * This class generates SQL from the output of the PHPSQLParser.
 *
 * @author  André Rothe <andre.rothe@phosco.info>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *
 */
/**
 * Generates SQL strings from the parsed output of Phpsql_Parser.
 *
 * Provides the inverse operation of Phpsql_Parser::parse(): accepts the
 * array produced by parse() and rebuilds the corresponding SQL string.
 * The round-trip (parse → create) is symmetric for supported statement types.
 *
 * @see Phpsql_Parser For the parser that produces the input to create()
 */
class Phpsql_Creator
{
    /**
     * The most recently generated SQL string.
     *
     * Populated by create() and optionally by the constructor when $parsed is provided.
     *
     * @var string
     */
    public $created;

    /**
     * Optionally creates SQL from a parsed array on construction.
     *
     * @param array<string, mixed>|false $parsed Parsed SQL array from Phpsql_Parser::parse(), or false to skip
     */
    public function __construct(array|false $parsed = false)
    {
        if ($parsed) {
            $this->create($parsed);
        }
    }

    /**
     * Generates an SQL string from the array produced by Phpsql_Parser::parse().
     *
     * Dispatches to the appropriate statement builder based on the top-level clause
     * key (SELECT, INSERT, UPDATE, etc.). The result is also stored in $this->created.
     *
     * @param array<string, mixed> $parsed Parsed SQL array from Phpsql_Parser::parse()
     *
     * @return string The reconstructed SQL statement
     *
     * @throws \Phpsql_Parser\exceptions\Unsupported_Feature_Exception If the statement type is not supported
     *
     * @complexity O(n) where n is the size of the parsed array
     */
    public function create(array $parsed): string
    {
        $k = key($parsed);
        switch ($k) {
            case 'UNION':
                $builder = new Union_Statement_Builder();
                $this->created = $builder->build($parsed);
                break;
            case 'UNION ALL':
                $builder = new Union_All_Statement_Builder();
                $this->created = $builder->build($parsed);
                break;
            case 'SELECT':
                $builder = new Select_Statement_Builder();
                $this->created = $builder->build($parsed);
                break;
            case 'INSERT':
                $builder = new Insert_Statement_Builder();
                $this->created = $builder->build($parsed);
                break;
            case 'REPLACE':
                $builder = new Replace_Statement_Builder();
                $this->created = $builder->build($parsed);
                break;
            case 'DELETE':
                $builder = new Delete_Statement_Builder();
                $this->created = $builder->build($parsed);
                break;
            case 'TRUNCATE':
                $builder = new Truncate_Statement_Builder();
                $this->created = $builder->build($parsed);
                break;
            case 'UPDATE':
                $builder = new Update_Statement_Builder();
                $this->created = $builder->build($parsed);
                break;
            case 'RENAME':
                $builder = new Rename_Statement_Builder();
                $this->created = $builder->build($parsed);
                break;
            case 'SHOW':
                $builder = new Show_Statement_Builder();
                $this->created = $builder->build($parsed);
                break;
            case 'CREATE':
                $builder = new Create_Statement_Builder();
                $this->created = $builder->build($parsed);
                break;
            case 'BRACKET':
                $builder = new Bracket_Statement_Builder();
                $this->created = $builder->build($parsed);
                break;
            case 'DROP':
                $builder = new Drop_Statement_Builder();
                $this->created = $builder->build($parsed);
                break;
            case 'ALTER':
                $builder = new Alter_Statement_Builder();
                $this->created = $builder->build($parsed);
                break;
            default:
                throw new Unsupported_Feature_Exception($k);
        }
        return $this->created;
    }
}