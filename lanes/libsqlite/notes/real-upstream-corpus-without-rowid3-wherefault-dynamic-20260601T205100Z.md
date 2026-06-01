# real-upstream-corpus-without-rowid3-wherefault-dynamic-20260601T205100Z

Base accepted HEAD: `c144809a94c645c49c7b403532a568b23ab72dd3`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/without_rowid3.test`
  - `without_rowid3-1.1` through `1.4`: immediate, deferrable, count_changes, and transaction-wrapped foreign-key checks against WITHOUT ROWID parent primary keys.
  - `without_rowid3-1.5` through `1.7`: parent-key affinity and parent collation control child matching without coercing stored child values.
  - `without_rowid3-9.1`, `11.1`, and `12.1`: SET DEFAULT, CASCADE, and immediate RESTRICT action behavior for WITHOUT ROWID parents.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wherefault.test`
  - `wherefault-1`: recoverable OOM while planning OR and range WHERE terms.
  - `wherefault-2`: recoverable OOM while counting a 1000-row OR range union, preserving the expected `993` count.
  - `wherefault-3.1`: recoverable OOM around generated-column schema reopen and nested NATURAL JOIN planning.

## Implementation Delta

- Added `SQLiteDynamicTriggerForeignKeyPlan::withoutRowid3ForeignKeyRuntimeCases()` with 1,200 dynamic cases over the real upstream WITHOUT ROWID FK runtime matrix.
- Added `SQLiteBTreeIndexDynamicCorpusPlan::whereFaultOrOptimizationCases()` with 800 dynamic cases over the real upstream WHERE OR/range fault corpus.
- Added `SQLiteRealUpstreamWithoutRowid3WhereFaultDynamicTest.php` with 2,004 focused TestRunner PASS cases and 46,530 assertions.

## Non-Overlap

This owns only `without_rowid3.test` FK runtime/affinity/collation/action cases and `wherefault.test` WHERE fault-retry behavior. It does not repeat accepted WITHOUT ROWID trigger-order (`without_rowid4`), `without_rowid5` requirements, `without_rowid2` catalog, `whereI` WITHOUT ROWID OR planning, JSON table, WAL/VFS, B-tree page relocation/freelist, PRAGMA table API, instr, pager/WAL empty-journal cleanup, selectA collation, PDO/native, source-neutral cleanup, or metadata-only runner admission batches.

## Verification

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php` passed.
- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWithoutRowid3WhereFaultDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWithoutRowid3WhereFaultDynamicTest.php` passed: `1 test files, 46530 assertions, 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: `1 test files, 8 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.

## Dependency Closure

No new support component is needed. This reuses lane-local WITHOUT ROWID foreign-key runtime modeling, parent affinity/collation comparison, FK action summaries, and WHERE OR fault-retry planner evidence.

## Expected Status Delta

- `phpPass`: `6256079 -> 6258083` (`+2004` focused TestRunner PASS cases).
- `benchmarkDenominator.mapped`: unchanged at `1589 / 1589`.
- Broad full-lane/release parity: unchanged, still recorded as 16 known failures.
