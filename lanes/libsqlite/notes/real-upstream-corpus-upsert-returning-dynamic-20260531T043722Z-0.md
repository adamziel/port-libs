# real-upstream-corpus-upsert-returning-dynamic-20260531T043722Z-0

Implemented an additive real upstream SQLite UPSERT/RETURNING corpus slice from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test`
  - `upsert1-700`, `upsert1-710`, `upsert1-720`
  - `upsert1-730`, `upsert1-740`, `upsert1-750`
  - `upsert1-760`, `upsert1-770`, `upsert1-780`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - `returning1.test-4` changed-row-only RETURNING stream semantics

The new focused test file is
`lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningTargetFirstDynamicTest.php`.
It exercises `SQLiteUpsertReturningDynamicCorpusPlan::upsert1TargetFirstReturningDynamicCases(1000)`
through `SQLiteUpsertDoUpdateWherePlan::executeConflictArms()`.

Coverage:

- 1000 deterministic target-first dynamic cases.
- 5002 focused TestRunner PASS cases.
- 15003 additive focused assertions.
- Named conflict targets `e`, `a`, and `b`.
- Rowid table, explicit unique-index rowid table, and WITHOUT ROWID variants.
- RETURNING rows are asserted to contain only the post-update changed row.

Non-overlap:

- This does not repeat the accepted `upsert5.test` catch-all priority,
  redundant conflict, or `upsert2.test` WHERE-false dynamic slices.
- This uses the existing native PHP multi-arm UPSERT executor and adds no
  metadata-only rows or fabricated upstream script names.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningTargetFirstDynamicTest.php`
  - `1 test files, 31204 assertions, 0 failures`
  - `12563` total PASS lines, including `5002` additive PASS lines in this handoff

Dependency closure:

- No new support component needed; this reuses the existing native PHP UPSERT
  conflict-arm executor and RETURNING projection helper.
