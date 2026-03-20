<?php

declare (strict_types=1);
/**
 * SQLChunkProcessor.php
 *
 * This file implements the processor for the SQL chunks.
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
 * This class processes the SQL chunks.
 *
 * @author  André Rothe <andre.rothe@phosco.info>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *
 */
class Sql_Chunk_Processor extends Abstract_Processor
{
    protected function move_like(&$out)
    {
        if (!isset($out['TABLE']['like'])) {
            return;
        }
        $out = $this->array_insert_after($out, 'TABLE', ['LIKE' => $out['TABLE']['like']]);
        unset($out['TABLE']['like']);
    }
    public function process($out)
    {
        if (!$out) {
            return false;
        }
        if (!empty($out['BRACKET'])) {
            // TODO: this field should be a global STATEMENT field within the output
            // we could add all other categories as sub_tree, it could also work with multipe UNIONs
            $processor = new Bracket_Processor($this->options);
            $processed_bracket = $processor->process($out['BRACKET']);
            $remaining_expressions = $processed_bracket[0]['remaining_expressions'];
            unset($processed_bracket[0]['remaining_expressions']);
            if (!empty($remaining_expressions)) {
                foreach ($remaining_expressions as $key => $expression) {
                    $processed_bracket[][$key] = $expression;
                }
            }
            $out['BRACKET'] = $processed_bracket;
        }
        if (!empty($out['CREATE'])) {
            $processor = new Create_Processor($this->options);
            $out['CREATE'] = $processor->process($out['CREATE']);
        }
        if (!empty($out['TABLE'])) {
            $processor = new Table_Processor($this->options);
            $out['TABLE'] = $processor->process($out['TABLE']);
            $this->move_like($out);
        }
        if (!empty($out['INDEX'])) {
            $processor = new Index_Processor($this->options);
            $out['INDEX'] = $processor->process($out['INDEX']);
        }
        if (!empty($out['EXPLAIN'])) {
            $processor = new Explain_Processor($this->options);
            $out['EXPLAIN'] = $processor->process($out['EXPLAIN'], array_keys($out));
        }
        if (!empty($out['DESCRIBE'])) {
            $processor = new Describe_Processor($this->options);
            $out['DESCRIBE'] = $processor->process($out['DESCRIBE'], array_keys($out));
        }
        if (!empty($out['DESC'])) {
            $processor = new Desc_Processor($this->options);
            $out['DESC'] = $processor->process($out['DESC'], array_keys($out));
        }
        if (!empty($out['SELECT'])) {
            $processor = new Select_Processor($this->options);
            $out['SELECT'] = $processor->process($out['SELECT']);
        }
        if (!empty($out['FROM'])) {
            $processor = new From_Processor($this->options);
            $out['FROM'] = $processor->process($out['FROM']);
        }
        if (!empty($out['USING'])) {
            $processor = new Using_Processor($this->options);
            $out['USING'] = $processor->process($out['USING']);
        }
        if (!empty($out['UPDATE'])) {
            $processor = new Update_Processor($this->options);
            $out['UPDATE'] = $processor->process($out['UPDATE']);
        }
        if (!empty($out['GROUP'])) {
            // set empty array if we have partial SQL statement
            $processor = new Group_By_Processor($this->options);
            $out['GROUP'] = $processor->process($out['GROUP'], isset($out['SELECT']) ? $out['SELECT'] : []);
        }
        if (!empty($out['ORDER'])) {
            // set empty array if we have partial SQL statement
            $processor = new Order_By_Processor($this->options);
            $out['ORDER'] = $processor->process($out['ORDER'], isset($out['SELECT']) ? $out['SELECT'] : []);
        }
        if (!empty($out['LIMIT'])) {
            $processor = new Limit_Processor($this->options);
            $out['LIMIT'] = $processor->process($out['LIMIT']);
        }
        if (!empty($out['WHERE'])) {
            $processor = new Where_Processor($this->options);
            $out['WHERE'] = $processor->process($out['WHERE']);
        }
        if (!empty($out['HAVING'])) {
            $processor = new Having_Processor($this->options);
            $out['HAVING'] = $processor->process($out['HAVING'], isset($out['SELECT']) ? $out['SELECT'] : []);
        }
        if (!empty($out['SET'])) {
            $processor = new Set_Processor($this->options);
            $out['SET'] = $processor->process($out['SET'], isset($out['UPDATE']));
        }
        if (!empty($out['DUPLICATE'])) {
            $processor = new Duplicate_Processor($this->options);
            $out['ON DUPLICATE KEY UPDATE'] = $processor->process($out['DUPLICATE']);
            unset($out['DUPLICATE']);
        }
        if (!empty($out['INSERT'])) {
            $processor = new Insert_Processor($this->options);
            $out = $processor->process($out);
        }
        if (!empty($out['REPLACE'])) {
            $processor = new Replace_Processor($this->options);
            $out = $processor->process($out);
        }
        if (!empty($out['DELETE'])) {
            $processor = new Delete_Processor($this->options);
            $out = $processor->process($out);
        }
        if (!empty($out['VALUES'])) {
            $processor = new Values_Processor($this->options);
            $out = $processor->process($out);
        }
        if (!empty($out['INTO'])) {
            $processor = new Into_Processor($this->options);
            $out = $processor->process($out);
        }
        if (!empty($out['DROP'])) {
            $processor = new Drop_Processor($this->options);
            $out['DROP'] = $processor->process($out['DROP']);
        }
        if (!empty($out['RENAME'])) {
            $processor = new Rename_Processor($this->options);
            $out['RENAME'] = $processor->process($out['RENAME']);
        }
        if (!empty($out['SHOW'])) {
            $processor = new Show_Processor($this->options);
            $out['SHOW'] = $processor->process($out['SHOW']);
        }
        if (!empty($out['OPTIONS'])) {
            $processor = new Options_Processor($this->options);
            $out['OPTIONS'] = $processor->process($out['OPTIONS']);
        }
        if (!empty($out['WITH'])) {
            $processor = new With_Processor($this->options);
            $out['WITH'] = $processor->process($out['WITH']);
        }
        return $out;
    }
}