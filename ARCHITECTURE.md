# Architecture: PHP-SQL-Parser

## Purpose

Pure PHP SQL parser that converts SQL statements into a structured PHP array representation, and a companion SQL builder that reconstructs SQL strings from those arrays.

## Directory Structure

```
src/PHPSQLParser/
  PHPSQLParser.php        Entry point: parse(sql) -> array
  PHPSQLCreator.php       Entry point: create(parsed) -> sql string
  builders/               Per-clause SQL builders (Select_Builder, From_Builder, Where_Builder, etc.)
  processors/             Per-clause SQL processors that parse token arrays into structured arrays
  lexer/                  Tokeniser: splits SQL string into tokens
  utils/                  Shared helpers (expression type detection, etc.)
  exceptions/             Domain exceptions
  enums/                  String constant groups (token types, etc.)
tests/
  PHPSQLParserTest.php    Data-driven tests with SQL fixtures
  PHPSQLCreatorTest.php   Round-trip tests (parse -> create -> re-parse)
```

## Key Design Decisions

- **Symmetric parse/create**: The parsed array format is designed so that `create(parse(sql))` produces semantically equivalent SQL. This round-trip property is tested explicitly.
- **Clause-per-processor**: Each SQL clause (SELECT, FROM, WHERE, GROUP BY, etc.) has its own processor class. The main parser delegates to processors after keyword detection.
- **Pure PHP, no extensions**: No SQL client library or PDO required. The parser works on SQL as a string.
- **Array format stability**: The output array format is a documented contract; callers can manipulate the array before passing it to the creator.

## Extension Points

- Extend `PHPSQLParser` and override `processStatement()` to add support for custom SQL dialects.
- Add new builder classes under `builders/` for new clause types.

## Dependency Flow

```
SQL string
  -> Lexer::tokenize() -> token array
  -> PHPSQLParser::process() -> delegates per-clause to Processor classes
  -> structured PHP array

Structured PHP array
  -> PHPSQLCreator::create() -> delegates per-key to Builder classes
  -> SQL string
```
