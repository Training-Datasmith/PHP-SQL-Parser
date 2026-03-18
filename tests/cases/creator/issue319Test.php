<?php

declare(strict_types=1);

namespace PHPSQLParser\Test\Creator;

use PHPSQLParser\PHPSQLCreator;
use PHPSQLParser\PHPSQLParser;

class issue319Test extends \PHPUnit\Framework\TestCase
{
    public function testIssue319()
    {
        $sql = 'SELECT start_date FROM users INNER JOIN vacation ON DATE(start_date) <= DATE(end_date)';

        $parser = new PHPSQLParser();
        $creator = new PHPSQLCreator();

        $parser->parse($sql, true);

        $this->assertEquals($sql, $creator->create($parser->parsed));
    }
}
