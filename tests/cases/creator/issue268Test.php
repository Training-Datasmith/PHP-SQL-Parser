<?php

declare(strict_types=1);
/**
 * issue268.php
 *
 * Test case for PHPSQLCreator.
 */

namespace PHPSQLParser\Test\Creator;

use PHPSQLParser\PHPSQLCreator;
use PHPSQLParser\PHPSQLParser;

class Issue268Test extends \PHPUnit\Framework\TestCase
{
    public function testIssue268()
    {
        /*
    	 * https://github.com/greenlion/PHP-SQL-Parser/issues/268
    	 */
        $sql     = 'UPDATE wp_rg_form_view SET count = count + 1,test = count(*) WHERE id = 239';
        $parser  = new PHPSQLParser($sql);
        $creator = new PHPSQLCreator($parser->parsed);
        $this->assertEquals($creator->created, $sql);

        $sql     = 'UPDATE wp_rg_form_view SET count = count + 1 WHERE id = 239';
        $parser  = new PHPSQLParser($sql);
        $creator = new PHPSQLCreator($parser->parsed);
        $this->assertEquals($creator->created, $sql);

        $sql     = 'UPDATE wp_rg_form_view SET total = count(test) WHERE id = 239';
        $parser  = new PHPSQLParser($sql);
        $creator = new PHPSQLCreator($parser->parsed);
        $this->assertEquals($creator->created, $sql);
    }
}
