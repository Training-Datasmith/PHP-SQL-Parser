<?php

declare(strict_types=1);

/**
 * Test the support for positions in ORDER BY expressions.
 */

namespace PHPSQLParser\Test\Parser;

class OrderByPositionTest extends \PHPSQLParser\Test\AbstractTestCase
{
    public function testOrderByPosition()
    {
        $query = 'SELECT c1, c2 FROM t ORDER BY 1';

        $parsed = $this->parser->parse($query);
        $created = $this->creator->create($parsed);
        $expected = getExpectedValue(dirname(__FILE__), 'orderbyposition.sql', false);
        $this->assertEquals($expected, $created, 'creating ORDER BY with positions is not supported');
    }
}
