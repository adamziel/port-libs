# Real Upstream JSON1/JSONB Dynamic Corpus

Status: focused PHP behavior growth for `real-upstream-corpus-json1-jsonb-dynamic-20260530T173941Z-0`.

Added 94 TestRunner PASS cases to `SQLiteRealUpstreamJson1JsonbDynamicTest.php`.
The new cases cite real upstream SQLite scripts:

- `json105.test`: reverse-index `json_remove()`, `json_insert()`, `json_set()`,
  and `json_replace()` cases from sections `json105-2.*` through `json105-5.*`,
  now additionally exercised through `SQLiteSelectExpression` function dispatch
  for text JSON and JSONB function variants.
- `jsonb01.test`: all 18 `jsonb01-1.2.*` `jsonb_remove()` / `json_remove()`
  JSONB removal cases, now additionally exercised through
  `SQLiteSelectExpression` function dispatch.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson1JsonbDynamicTest.php`
  passed: `1 test files, 684 assertions, 0 failures`.

Expected dashboard movement: `phpPass` +94, from `218357` to `218451`.
Mapped coverage remains `958 / 1589`; this ports additional behavior from
already hydrated upstream JSON scripts rather than claiming new denominator rows.

Non-overlap: this does not repeat the accepted JSON array-insert batch,
JSON table source/cursor/constraint work, JSONB CHECK cleanup, merge-patch
coverage, path operator coverage, or JSON table planner/current-source helpers.
The new surface is real upstream JSON1/JSONB dynamic mutator behavior through
the SELECT-expression function dispatcher.

Dependency closure: no new support component is needed. The slice reuses the
existing native JSONB encoder/decoder, JSON path evaluator, mutator helpers,
remove helper, and SELECT-expression dispatcher.
