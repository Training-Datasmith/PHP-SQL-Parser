<?php

declare(strict_types=1);
/**
 * issue248.php
 *
 * Test case for PHPSQLCreator.
 */

namespace PHPSQLParser\Test\Creator;

use PHPSQLParser\PHPSQLCreator;
use PHPSQLParser\PHPSQLParser;

class Issue248Test extends \PHPUnit\Framework\TestCase
{
    public function testIssue248()
    {
        /*
    	 * https://github.com/greenlion/PHP-SQL-Parser/issues/248
    	 * DROP INDEX doesn't get created.
    	 */
        $sql     = 'DROP INDEX test ON wp_posts';
        $parser  = new PHPSQLParser($sql);
        $creator = new PHPSQLCreator($parser->parsed);
        $this->assertEquals($creator->created, $sql, 'drop index statement');
    }
}
