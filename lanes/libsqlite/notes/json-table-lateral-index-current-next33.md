### 2026-05-27 JSON table lateral index current-next33

- Added parser/executor support for lateral `json_each()` / `json_tree()` joins
  to carry right-side `ON` constraints into the JSON virtual-table row
  generator as indexable constraints while retaining the `ON` predicate as the
  semantic residual filter.
- Covered `IN`, `IS NOT NULL`, range comparison, and rowid alias constraints
  over copied `wp_options` JSON text and JSONB settings rows.
- Added the Application smoke
  `lanes/libsqlite/examples/application-json-table-lateral-index-current-next33.php`
  for plugin settings queries that read current text JSON and next JSONB rows
  through the same lateral virtual-table index path.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableLateralIndexCurrentNext33Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 42 assertions, 0 failures

php lanes/libsqlite/examples/application-json-table-lateral-index-current-next33.php --self-test
application-json-table-lateral-index-current-next33 self-test passed
```

Non-overlap:

- This slice does not repeat accepted JSON table cursor/source wiring,
  hidden-constraint extraction, visible-column standalone pushdown, host-row
  joins, nested left joins, JSONB malformed planner diagnostics, or JSON table
  LIMIT/OFFSET/window clusters. It specifically wires lateral `ON` constraints
  into dynamic JSON row production for parser-level SELECT execution.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP
  JSON table, JSONB, SELECT expression, and SELECT query primitives.
