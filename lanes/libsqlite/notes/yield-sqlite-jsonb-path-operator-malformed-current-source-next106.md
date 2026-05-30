# JSONB Path Operator Malformed Current Source Next106

## Behavior

Adds `SQLiteJsonbPathOperatorMalformedCurrentSourceNextPlan` for current/next
`wp_options` diagnostics around SQLite JSON path operators `->` and `->>`.
The plan distinguishes:

- valid JSON text source rows;
- valid JSONB blob source rows;
- missing path rows;
- superficially JSONB or truncated malformed JSONB rows that would abort native
  SELECT execution;
- stable current-source retention while the next source tape is prepared.

The focused tests also prove parser-level SELECT behavior for the same operator
surface: malformed JSONB aborts projection/WHERE evaluation, while SQLite lazy
`AND`, `OR`, `CASE`, and row-restricted execution avoid evaluating the
malformed operator branch.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonbPathOperatorMalformedCurrentSourceNext106Test.php`
  - `1 test files, 76 assertions, 0 failures`
  - `62` PASS lines
- `php lanes/libsqlite/examples/application-jsonb-path-operator-malformed-current-source-next106.php`
  - valid JSON output; reports current/next signatures, malformed rowids,
    missing-path rowids, reprepare reason, and statement abort state.

## Non-Overlap

This slice avoids the accepted JSON aggregate DISTINCT/ORDER/window work, JSON
table hidden/lateral/visible constraints, JSON table cursor/source wiring, JSONB
CHECK optional-path admission, and existing JSONB path-index covering planner
tests. It is specifically about JSON path operator source diagnostics for
malformed JSONB blobs in current/next statement preparation and SELECT lazy
evaluation.

## Dependency Closure

No new support component is needed. The plan reuses existing native PHP
`SQLiteJsonB`, `SQLiteJsonInspection`, `SQLiteJsonPath`, `SQLiteSelectSql`, and
`SQLiteSelectPredicate` behavior.
