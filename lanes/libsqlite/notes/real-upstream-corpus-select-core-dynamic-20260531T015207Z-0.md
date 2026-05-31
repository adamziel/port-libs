## real-upstream-corpus-select-core-dynamic-20260531T015207Z-0

Ported the upstream `select7.test` correlated compound-subquery name-resolution
cluster into a focused PHP behavior test.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select7.test`
- Scenarios: `select7-4.1` and `select7-4.2`, the Ticket #2018 `NOT EXISTS`
  over `EXCEPT` correlated-name resolution cases.

Behavior change:

- `SQLiteSelectSql` now keeps qualified single-source rows when a predicate
  references that source alias, so predicates such as `P.pk` survive into
  correlated subqueries.
- `SQLiteSelectPredicate` now matches `SQLiteSelectExpression` for qualified
  column fallback on unqualified single-source rows while preserving exact
  qualified matches when present.

Focused coverage:

- Added `SQLiteRealUpstreamSelect7CorrelatedExceptDynamicTest.php`.
- The test contributes `1001` distinct TestRunner PASS cases and `5005`
  behavior assertions.
- The dynamic cases vary photo/tag counts, empty-tag photos, all-matching
  `%foo%` tags, and mixed non-matching tags around the upstream `NOT EXISTS
  (SELECT ... EXCEPT SELECT ...)` shape.

Non-overlap:

- This slice owns `select7.test` `select7-4` correlated `EXCEPT` alias
  resolution. It does not repeat accepted `select7-1`, `select7-7`, `select8`,
  `selectB`, `selectC`, grouped SELECT text, expression `ORDER BY`, JSON table
  SELECT source/cursor/constraint work, WAL/VFS/B-tree storage work, or
  metadata-only runner rows.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect7CorrelatedExceptDynamicTest.php`
  passed: `1 test files, 5005 assertions, 0 failures`.
- Dependency closure: no new support component is needed; this reuses the
  existing native `SQLiteSelectSql` and `SQLiteSelectPredicate` executor path.
