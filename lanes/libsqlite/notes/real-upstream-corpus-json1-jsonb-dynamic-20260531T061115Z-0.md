# real-upstream-corpus-json1-jsonb-dynamic-20260531T061115Z-0

Base accepted HEAD: `cd24ba2f7b741bb89ced6cb6c27264084794565b`.

Added `SQLiteRealUpstreamJson101TrailingCommaDynamicTest.php`, a focused PHP
port of the hydrated upstream SQLite JSON lexical boundary section:

- Source truth:
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`
- Ported upstream sections: `json101-6.1` through `json101-6.11`.
- Behavior: strict `json_valid()` rejects object/array trailing commas,
  JSON5-flag validity accepts them, `json_error_position()` returns zero for
  JSON5 trailing-comma inputs, `json()` canonicalizes trailing-comma JSON5 to
  strict JSON, doubled commas report the SQLite-style one-based diagnostic
  offset, and `SQLiteSelectExpression` dispatch preserves the same
  `json_valid()` / `json_error_position()` behavior.

Focused coverage:

- `1000` distinct dynamic TestRunner behavior cases.
- `1002` focused PASS lines including source and dependency citations.
- `9004` assertions.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101TrailingCommaDynamicTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 9004 assertions, 0 failures
```

Non-overlap:

- Does not repeat the rejected `json_remove()` / no-edit mutation candidate
  from `real-json-20260531T054939Z`.
- Does not repeat JSON table source/cursor/hidden/visible constraint work,
  JSON103 aggregate/window behavior, JSON104 merge-patch behavior,
  JSON105 reverse-index mutation behavior, JSON106/108 invariant batches,
  JSON107 BLOB compatibility, JSON109 array-insert behavior, JSON501/502
  JSON5/path stress, or jsonb01 malformed/removal behavior.
- This slice owns the JSON101 lexical trailing-comma / doubled-comma boundary
  and SQL-expression dispatch parity.

Dependency closure:

- No new support component is needed. The slice reuses existing native
  `SQLiteJsonValidity`, `SQLiteJsonErrorPosition`, `SQLiteJsonCanonical`,
  `SQLiteJsonInspection`, and `SQLiteSelectExpression` behavior.
