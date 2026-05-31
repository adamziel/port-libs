# real-upstream-corpus-upsert-returning-dynamic-20260531T013159Z-0

This slice ports a non-overlapping real upstream UPSERT/RETURNING behavior cluster from the hydrated SQLite upstream checkout:

- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test`
- Upstream sections: `upsert4` cases `7.1`, `7.2`, `7.4`, `8.1`, `8.2`, `8.3`, and `8.4`
- Ported behavior: `excluded` name resolution in UPSERT assignments and WHERE predicates, including the special case where the target table itself is named `excluded`, the aliased-target case where `excluded.column` resolves to the incoming row, composite conflict targets with quoted column names, and RETURNING yield suppression when the DO UPDATE WHERE predicate is false.

Focused PHP coverage:

- Added `SQLiteRealUpstreamUpsertReturningExcludedDynamicTest.php`
- New focused PASS cases: 441
- Focused assertions: 442

Verification:

- Red-first check before the alias-guard fix: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningExcludedDynamicTest.php` failed with `SQLite UPSERT RETURNING column excluded.w is missing` / `excluded.x is missing` for upstream `upsert4` aliased-target cases.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningExcludedDynamicTest.php` -> `1 test files, 442 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpsertReturningSqlTest.php lanes/libsqlite/tests/SQLiteUpstreamUpsertReturningDynamicRealCorpusTest.php lanes/libsqlite/tests/SQLiteUpstreamUpsert5ReturningYieldRealCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningExcludedDynamicTest.php` -> `4 test files, 3202 assertions, 0 failures`

Non-overlap:

- This does not repeat prior UPSERT/RETURNING yield-matrix, upsert5 conflict-arm, trigger/FK, or generic dynamic RETURNING batches. It specifically covers upstream `upsert4.test` `excluded` name-resolution behavior and quoted composite conflict-target parsing in the SQL UPSERT RETURNING executor.

Dependency closure:

- No new support component is needed. The slice reuses the lane-local SQL UPSERT RETURNING executor and extends its identifier parsing/name-resolution rules.
