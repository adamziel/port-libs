# real-upstream-corpus-upsert-returning-dynamic-20260530T185456Z-0

Status: ready focused real-upstream UPSERT/RETURNING corpus growth.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test`
  - Ported broad dynamic behavior from `upsert1-100` through `upsert1-102`, `upsert1-320`, `upsert1-400`, `upsert1-500`, `upsert1-700` through `upsert1-780`, and `upsert1-1100`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test`
  - Ported repeated `INSERT ... SELECT ... ON CONFLICT DO UPDATE ... WHERE` behavior from `upsert2-100`, `upsert2-110`, `upsert2-200`, `upsert2-201`, and `upsert2-210`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert3.test`
  - Ported composite conflict-target and table-named-`excluded` behavior from `upsert3-130`, `upsert3-140`, `upsert3-200`, and `upsert3-210`.

Focused coverage:

- Added `SQLiteRealUpstreamUpsertReturningDynamicBroadTest.php` with `1001` focused TestRunner assertions.
- The batch covers distinct shifted corpus variants for real upstream conflict-priority, catch-all `DO NOTHING`, partial unique target behavior, repeated source-row updates, `WHERE`-gated updates, composite conflict targets, reversed composite target matching, and `excluded` pseudo-row behavior when a user table is named `excluded`.

Non-overlap:

- Does not repeat the earlier compressed `upsert5.test` multi-arm matrix, `returning1.test` statement-stream/subquery batches, `upsert4.test` target-analysis coverage, redundant conflict corruption guards, trigger/view UPSERT, row-value RETURNING, recursive trigger RETURNING, or UPDATE/DELETE RETURNING window/savepoint slices.
- This slice expands real upstream `upsert1`, `upsert2`, and `upsert3` behavior around the existing generic UPSERT conflict-arm executor.

Dependency closure:

- No new support component is needed. The tests reuse existing native PHP `SQLiteUpsertDoUpdateWherePlan` conflict-arm, `DO NOTHING`, `WHERE`, and `RETURNING` row modeling.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicBroadTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicBroadTest.php` passed with `1 test files / 1001 assertions / 0 failures`.
- `lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php` is absent in this worktree.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` passed.
- `git diff --check -- lanes/libsqlite` passed.

Root harness: not run - isolated micro-slice.
