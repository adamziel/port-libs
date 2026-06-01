# real-upstream-corpus-upsert-returning-dynamic-20260601T182906Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/indexexpr1.test`
- Ported sections:
  - `indexexpr1-1900`: create a table with text rows and an expression index on `+y COLLATE NOCASE`.
  - `indexexpr1-1910`: `DELETE FROM t1 INDEXED BY i1 WHERE x IS +y COLLATE NOCASE IN (SELECT z FROM t1) RETURNING *` emits only the row whose expression-index comparison matches.
  - `indexexpr1-1920`: the nonmatching row remains after the indexed `DELETE ... RETURNING`.

Behavior added:

- Added `SQLiteReturningIndexedDeletePlan`, a bounded source helper for the indexed DELETE/RETURNING expression predicate and old-row `RETURNING *` image.
- Added `SQLiteRealUpstreamReturningIndexedDeleteDynamicTest.php` with 1000 deterministic dynamic cases plus source, malformed-input, non-overlap, and dependency-closure checks.
- The dynamic cases use generic `app_returning_indexed_delete`/settings-shaped rows only.

Focused coverage:

- 1004 focused TestRunner PASS cases.
- 15010 focused behavior assertions.

Non-overlap:

- This is not another `upsert1`/`upsert2`/`upsert3`/`upsert4`/`upsert5` conflict-arm matrix.
- It avoids existing `returning1` row-stream, `qrf05` formatter, `changes2` prepared-counter, `bestindexB` virtual-table side-effect, trigger/FK, row-value, JSON, WAL, pager, and B-tree page-move coverage.
- The new owned behavior is specifically upstream `indexexpr1.test` `DELETE INDEXED BY` expression-index predicate evaluation with `RETURNING *`.

Dependency closure:

- No new support component is needed. The slice reuses lane-local RETURNING row-image modeling and adds a bounded expression-index DELETE predicate helper.
