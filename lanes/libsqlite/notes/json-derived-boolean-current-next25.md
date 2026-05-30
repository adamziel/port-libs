# JSON derived boolean current next25

2026-05-27 isolated slice `yield-sqlite-json-derived-boolean-current-next25`.

- Behavior: parser-level SELECT predicates now accept bare scalar truth expressions plus `IS TRUE`, `IS FALSE`, `IS NOT TRUE`, and `IS NOT FALSE`. Truth conversion uses SQLite numeric-prefix semantics for text, BLOB, and JSON subtype values, so JSON booleans derived from `json_extract()`, `jsonb_extract()`, and `->>` can drive WHERE/ON/constant SELECT predicates without comparing every result to `1`.
- Focused tests: `SQLiteJsonDerivedBooleanCurrentNext25Test.php` adds 57 PASS cases covering strict JSON text, JSONB blobs, `TRUE`/`FALSE` literals, `NOT`, NULL truth behavior, JSON text operator differences, CTEs, joins, CASE truth consumption, arithmetic truth derivation, malformed path guards, and Application-style copied `wp_options` plugin settings.
- Application smoke: `examples/application-select-sql-json-derived-boolean-current-next25.php` reports enabled/network/inactive copied `wp_options` plugin rows selected through JSON-derived boolean predicates without requiring ext/sqlite.
- Non-overlap: avoids accepted JSON table cursor/source/hidden/visible constraint pushdown, JSON host joins, JSON malformed planner diagnostics, SELECT SQL subquery/JOIN/GROUP/ORDER expression dispatch, Unicode GLOB, B-tree overflow/freelist/page-move/root-collapse, WAL savepoint/checkpoint/rollback, and VFS writer/lock/sync clusters. This slice is limited to scalar JSON-derived boolean truth in parser-level SELECT predicates.
- Dependency closure: no new support component is needed; this reuses the existing SELECT parser/evaluator, JSON extraction, JSONB, and row-array query executor.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonDerivedBooleanCurrentNext25Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 57 assertions, 0 failures
```

Next task: extend this same parser-level SELECT path to broader planner/executor gaps only if they are non-overlapping with accepted JSON table source/cursor and SQL text dispatch clusters.
