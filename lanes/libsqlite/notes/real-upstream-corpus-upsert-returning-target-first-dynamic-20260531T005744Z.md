# Real upstream corpus: UPSERT target-first RETURNING dynamic

Slice: `real-upstream-corpus-upsert-returning-dynamic-20260531T005515Z-0`

Base accepted HEAD: `452a6f6fbb9dca50b40370a18b13b7d77ca03385`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test`
- Scenarios `upsert1-700`, `upsert1-710`, `upsert1-720`, `upsert1-730`, `upsert1-740`, `upsert1-750`, `upsert1-760`, `upsert1-770`, and `upsert1-780`
- Cross-check dependency: `returning1.test` section 4, RETURNING emits rows changed by the statement

Behavior added:

- Dynamic corpus for UPSERT conflict target priority when the candidate row conflicts with multiple unique constraints.
- Covers rowid integer primary key, rowid table with explicit unique indexes, and WITHOUT ROWID variants.
- Verifies the selected conflict target is applied first, the updated row image preserves non-target unique values, and RETURNING yields exactly the updated row.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningTargetFirstDynamicTest.php`
- Result: `1 test files, 15004 assertions, 0 failures`
- PASS-line growth: `5002` focused TestRunner cases from real upstream UPSERT/RETURNING behavior.

Non-overlap:

- Does not repeat the accepted UPSERT/RETURNING WHERE-false dynamic sweep.
- Uses `upsert1.test` target-first multi-constraint cases, not `upsert2.test` 320/321.
- Uses generic `setting_key` rows and adds no domain-specific libsqlite API.

Dependency closure:

- No new support component is needed. The existing `SQLiteUpsertDoUpdateWherePlan` conflict-arm executor and RETURNING projection helper are reused.
