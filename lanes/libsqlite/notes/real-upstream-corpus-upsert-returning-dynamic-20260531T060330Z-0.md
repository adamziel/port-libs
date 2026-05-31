# real-upstream-corpus-upsert-returning-dynamic-20260531T060330Z-0

Source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test`.

Ported upstream behavior cluster:

- `upsert4.test` section 7.1: `excluded` pseudo-table values win for `ON CONFLICT(z) DO UPDATE`.
- `upsert4.test` section 7.2: reordered primary-key conflict target `(y, x)` updates the current row.
- `upsert4.test` section 7.3: target-qualified `t1.w` resolves to the current row inside the update arm.
- `upsert4.test` section 7.4: target alias `tbl.w` resolves to the current row inside the update arm.

Handoff delta:

- Added `SQLiteRealUpstreamUpsert4ReturningTargetAliasDynamicTest.php`.
- 250 deterministic dynamic seeds x 4 upstream scenarios x 4 focused checks, plus source coverage and dependency-closure checks.
- Focused result: `1 test files, 4002 assertions, 0 failures`.
- Expected selected PASS-line growth: `+4002`.

Non-overlap:

- Avoids already accepted `upsert4.test` section 8 table-named `excluded` ambiguity coverage.
- Avoids accepted UPSERT catch-all, redundant-conflict, partial-predicate, insert-select, target-first, scope-matrix, trigger old-value, and broad `upsert5` matrix coverage.
- Uses generic `t1` upstream table names only; no new WordPress-specific API or scenario.

Dependency closure:

- No new support component needed.
- Reuses `SQLiteUpsertReturningSql` conflict target parsing, target alias binding, `excluded` pseudo-table evaluation, and `RETURNING` projection.
