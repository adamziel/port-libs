# Real upstream corpus: UPSERT RETURNING dynamic yield

Slice: `real-upstream-corpus-upsert-returning-dynamic-20260530T174640Z-0`

Base accepted HEAD: `e12ceba2fd83282957420709bd781aee710bc7ca`

Upstream source files used from `/home/claude/port-libs/.upstream-cache/libsqlite/test`:

- `upsert1.test`: `upsert1-101`, `upsert1-120`, `upsert1-201`, and `upsert1-700` conflict target, DO NOTHING, and target-precedence behavior.
- `upsert2.test`: `upsert2-100` dynamic mixed insert/update/skip behavior with `WHERE` on the current row.
- `upsert3.test`: `upsert3-130`, `upsert3-200`, and `upsert3-210` reversed composite conflict targets and table named `excluded`.
- `returning1.test`: `returning1-4.2`, `returning1-4.5`, `returning1-7.7`, and `returning1-7.8` RETURNING post-update row image and table qualifier behavior.

Implementation:

- `SQLiteUpsertReturningSql` now validates parsed conflict targets against the supplied unique-constraint inventory, while accepting reversed composite targets as SQLite does.
- `DO NOTHING` now suppresses only target-conflict rows and still raises a secondary unique-conflict error when a non-target unique constraint fails.
- RETURNING projection qualifiers now accept unqualified columns or the real target table name, reject unrelated aliases, and continue to reject `excluded.*` references.
- `SQLiteUpsertDoUpdateWherePlan` keeps accepted row-array quoted-name style support while rejecting malformed unique-column names such as `bad-column!`.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicYieldTest.php` passed: `1 test files, 31 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicYieldTest.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicTest.php lanes/libsqlite/tests/SQLiteUpsertReturningMultirowCorpusTest.php lanes/libsqlite/tests/SQLiteUpsertReturningConflictCurrentTest.php lanes/libsqlite/tests/SQLiteUpsertDoNothingReturningCurrentTest.php` passed: `5 test files, 2820 assertions, 0 failures`.
- PHP lint passed for `SQLiteUpsertReturningSql.php`, `SQLiteUpsertDoUpdateWherePlan.php`, `SQLiteRealUpstreamUpsertReturningDynamicYieldTest.php`, and the restored `SQLiteRealUpstreamUpsertReturningDynamicTest.php`.
- `git diff --check -- lanes/libsqlite` passed.

PASS-line delta:

- New focused TestRunner PASS cases: `+31`.
- `lane-status.json` `phpPass`: `218357 -> 218388`.
- Mapped denominator remains `958 / 1589`; this slice adds behavior assertions but does not claim new manifest rows.

Non-overlap:

- This slice does not replace the existing large `SQLiteRealUpstreamUpsertReturningDynamicTest.php` upsert4/upsert5 arm-order coverage.
- It adds a distinct SQL-wrapper dynamic-yield file using generic `app_settings` rows and focuses on conflict-target validation, secondary unique conflicts under `DO NOTHING`, and RETURNING qualifier rules.
- No WordPress-specific API, class, method, or example was added.

Dependency closure:

- No new support component is needed. The patch reuses the existing native PHP SQL UPSERT RETURNING executor and row-array UPSERT plan.
