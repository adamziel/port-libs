# Real Upstream Corpus UPSERT RETURNING Dynamic Yield Matrix

Slice: `real-upstream-corpus-upsert-returning-dynamic-20260530T230150Z-0`

Base accepted HEAD: `ee0f86482fec002ad61b846f39a1a36b0fe0ecc4`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test`
  - `upsert2-100` repeated VALUES source with `WHERE t1.b < excluded.b`
  - `upsert2-1200` false update gate
  - related `upsert1-400` count-changes true branch and `upsert1-1300` old-value regression behavior already cross-referenced by existing upsert corpus tests
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert3.test`
  - `upsert3-200` table literally named `excluded`, conflict target order `(b,a)`, and excluded pseudo-table assignment behavior
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - RETURNING streams yield changed rows in statement order and report the final post-change row image

Patch content:

- Added `SQLiteRealUpstreamCorpusUpsertReturningDynamicYieldMatrixTest.php`.
- The file expands 50 dynamic repeated-source UPSERT inputs across 4 upstream WHERE/action families.
- Each matrix point checks final table image, RETURNING stream, `changes()`, changed-row count, and source-row partitioning against a local PDO SQLite oracle.
- Added explicit `upsert3-200` checks for a real table named `excluded` and `(b,a)` conflict target behavior.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicYieldMatrixTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicYieldMatrixTest.php`
  - `1 test files, 1008 assertions, 0 failures`

Expected dashboard movement:

- Focused PHP PASS/assertion growth: `+1008`.
- Mapped denominator growth: none; mapped inventory is already complete at `1589 / 1589`.

Dependency closure:

- No new support component needed. The test reuses native `SQLiteUpsertDoUpdateWherePlan` behavior and the local PDO SQLite oracle for expected upstream parity.

Non-overlap:

- This does not repeat the accepted excluded-alias, target/tail, broad/upsert5, or select-input files. It owns a dynamic repeated-source yield matrix around `upsert2` update gates plus the `upsert3-200` literal `excluded` table behavior.
