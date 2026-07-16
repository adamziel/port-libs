# JSONB Path Operator Index Current Next15

## Scope

- Added parser-level `->` and `->>` expression execution for `SQLiteSelectSql` over text JSON, JSONB blobs, and JSON subtype values.
- Covered SQLite path normalization for full paths, root array integer operands, bracket operands, bare labels, dotted bare labels, reverse array paths, missing paths, and JSON null.
- Covered SELECT projection, WHERE, IN/NOT IN, BETWEEN/NOT BETWEEN, LIKE/GLOB, ORDER BY, COLLATE ordering, concatenation, arithmetic composition, canonical JSON fragments from `->`, and SQL scalar extraction from `->>`.
- Added a copied Application settings smoke with JSON text and JSONB option rows.

## Evidence

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonbPathOperatorIndexCurrentNext15Test.php
Focused test run: 1 selected test files (root lock skipped)
31 PASS lines, 32 assertions, 0 failures
```

Example smoke:

```text
php lanes/libsqlite/examples/application-jsonb-path-operator-index-current-next15.php
2 copied wp_options-style rows returned with `->>` plugin names and `->` canonical channel JSON fragments.
```

## Non-overlap

This does not repeat accepted JSON path function extraction, JSON table cursor/source/constraint work, JSON visible/hidden constraint pushdown, JSON host joins, expression-index metadata parsing, SQL expression ORDER BY, or SELECT SQL subquery/grouped execution. The new behavior is parser-level JSON path operator expression dispatch inside `SQLiteSelectSql`.

## Dependency closure

No new support component is needed. The slice reuses existing native PHP JSON parsing, JSONB decoding, JSON canonical encoding, JSON path validation, SELECT expression evaluation, and SELECT predicate/order machinery.
