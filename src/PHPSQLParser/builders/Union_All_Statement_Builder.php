<?php

declare (strict_types=1);
namespace Phpsql_Parser\builders;

/**
 * This class implements the builder for the whole UNION ALL statement. You can overwrite
 * all functions to achieve another handling.
 *
 * @author  George Schneeloch <george_schneeloch@hms.harvard.edu>
 * @license http://www.debian.org/misc/bsd.license  BSD License (3 Clause)
 *
 */
class Union_All_Statement_Builder implements Builder
{
    public function build(array $parsed)
    {
        $sql = '';
        $select_builder = new Select_Statement_Builder();
        $first = true;
        foreach ($parsed['UNION ALL'] as $clause) {
            if (!$first) {
                $sql .= ' UNION ALL ';
            } else {
                $first = false;
            }
            $sql .= $select_builder->build($clause);
        }
        return $sql;
    }
}