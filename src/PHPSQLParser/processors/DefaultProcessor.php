<?php

declare (strict_types=1);
/**
 * DefaultProcessor.php
 *
 * This file implements the processor the unparsed sql string given by the user.
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

/**
 * This class processes the incoming sql string.
 *
 * @author  André Rothe <andre.rothe@phosco.info>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *
 */
class Default_Processor extends Abstract_Processor
{
    protected function is_union($tokens)
    {
        return Union_Processor::is_union($tokens);
    }
    protected function process_union($tokens)
    {
        // this is the highest level lexical analysis. This is the part of the
        // code which finds UNION and UNION ALL query parts
        $processor = new Union_Processor($this->options);
        return $processor->process($tokens);
    }
    protected function process_sql($tokens)
    {
        $processor = new Sql_Processor($this->options);
        return $processor->process($tokens);
    }
    public function process($sql)
    {
        $input_array = $this->split_sql_into_tokens($sql);
        $queries = $this->process_union($input_array);
        // If there was no UNION or UNION ALL in the query, then the query is
        // stored at $queries[0].
        if (!empty($queries) && !$this->is_union($queries)) {
            return $this->process_sql($queries[0]);
        }
        return $queries;
    }
    public function revoke_quotation($sql)
    {
        return parent::revoke_quotation($sql);
    }
}