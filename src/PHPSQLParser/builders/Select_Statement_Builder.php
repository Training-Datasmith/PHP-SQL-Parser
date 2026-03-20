<?php

declare (strict_types=1);
/**
 * SelectStatement.php
 *
 * Builds the SELECT statement
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
namespace Phpsql_Parser\builders;

/**
 * This class implements the builder for the whole Select statement. You can overwrite
 * all functions to achieve another handling.
 *
 * @author  André Rothe <andre.rothe@phosco.info>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *
 */
class Select_Statement_Builder implements Builder
{
    protected function build_select(array $parsed)
    {
        $builder = new Select_Builder();
        return $builder->build($parsed);
    }
    protected function build_from(array $parsed)
    {
        $builder = new From_Builder();
        return $builder->build($parsed);
    }
    protected function build_where(array $parsed)
    {
        $builder = new Where_Builder();
        return $builder->build($parsed);
    }
    protected function build_group(array $parsed)
    {
        $builder = new Group_By_Builder();
        return $builder->build($parsed);
    }
    protected function build_having(array $parsed)
    {
        $builder = new Having_Builder();
        return $builder->build($parsed);
    }
    protected function build_order(array $parsed)
    {
        $builder = new Order_By_Builder();
        return $builder->build($parsed);
    }
    protected function build_limit(array $parsed)
    {
        $builder = new Limit_Builder();
        return $builder->build($parsed);
    }
    protected function build_union(array $parsed)
    {
        $builder = new Union_Statement_Builder();
        return $builder->build($parsed);
    }
    protected function build_unionall(array $parsed)
    {
        $builder = new Union_All_Statement_Builder();
        return $builder->build($parsed);
    }
    public function build(array $parsed)
    {
        $sql = '';
        if (isset($parsed['SELECT'])) {
            $sql .= $this->build_select($parsed['SELECT']);
        }
        if (isset($parsed['FROM'])) {
            $sql .= ' ' . $this->build_from($parsed['FROM']);
        }
        if (isset($parsed['WHERE'])) {
            $sql .= ' ' . $this->build_where($parsed['WHERE']);
        }
        if (isset($parsed['GROUP'])) {
            $sql .= ' ' . $this->build_group($parsed['GROUP']);
        }
        if (isset($parsed['HAVING'])) {
            $sql .= ' ' . $this->build_having($parsed['HAVING']);
        }
        if (isset($parsed['ORDER'])) {
            $sql .= ' ' . $this->build_order($parsed['ORDER']);
        }
        if (isset($parsed['LIMIT'])) {
            $sql .= ' ' . $this->build_limit($parsed['LIMIT']);
        }
        if (isset($parsed['UNION'])) {
            $sql .= ' ' . $this->build_union($parsed);
        }
        if (isset($parsed['UNION ALL'])) {
            $sql .= ' ' . $this->build_unionall($parsed);
        }
        return $sql;
    }
}