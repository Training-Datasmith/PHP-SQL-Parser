<?php

declare(strict_types=1);

/**
 * PHP-SQL-Parser — parse and reconstruct SQL example.
 *
 * Demonstrates: parsing SQL to an array, inspecting the structure, rebuilding SQL.
 *
 * Run:
 *   php examples/parse_and_create.php
 */

require __DIR__ . '/../vendor/autoload.php';

use PHPSQLParser\PHPSQLParser;
use PHPSQLParser\PHPSQLCreator;

$parser  = new PHPSQLParser();
$creator = new PHPSQLCreator();

// --- Example 1: SELECT with WHERE and ORDER BY ---
$sql = 'SELECT u.id, u.name, u.email FROM users u WHERE u.active = 1 ORDER BY u.name ASC LIMIT 10';

$parsed = $parser->parse($sql);

echo 'Parsed structure keys: ' . implode(', ', array_keys($parsed)) . PHP_EOL;

// Inspect the SELECT clause
echo PHP_EOL . 'SELECT columns:' . PHP_EOL;
foreach ($parsed['SELECT'] as $col) {
    echo '  ' . $col['base_expr'] . PHP_EOL;
}

// Reconstruct SQL from the parsed array
$rebuilt = $creator->create($parsed);
echo PHP_EOL . 'Rebuilt SQL:' . PHP_EOL . '  ' . $rebuilt . PHP_EOL;

// --- Example 2: INSERT ---
$insertSql = "INSERT INTO orders (user_id, total, created_at) VALUES (42, 99.99, '2024-01-15')";
$parsedInsert = $parser->parse($insertSql);

echo PHP_EOL . 'INSERT keys: ' . implode(', ', array_keys($parsedInsert)) . PHP_EOL;
echo 'Rebuilt INSERT: ' . $creator->create($parsedInsert) . PHP_EOL;
