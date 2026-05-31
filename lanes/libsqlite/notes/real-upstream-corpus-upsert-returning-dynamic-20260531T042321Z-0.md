# real-upstream-corpus-upsert-returning-dynamic-20260531T042321Z-0

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test`
  - `upsert4-8.5`: when the INSERT target is the table named `excluded` with an alias, a conflict-target `WHERE y=1` predicate is resolved before the DO UPDATE body and fails with `no such column: y`.

## Implementation

- Extended `SQLiteUpsertReturningSql::parse()` to recognize `ON CONFLICT(...) WHERE ... DO UPDATE/DO NOTHING`.
- Added bounded conflict-target WHERE validation against target-table columns so missing names fail before the update predicate is evaluated.
- Preserved existing excluded pseudo-table assignment/WHERE behavior and RETURNING projection behavior.

## Focused Test Evidence

- Added `SQLiteRealUpstreamUpsertReturningConflictTargetWhereDynamicTest.php`.
- The new file covers 1000 invalid upstream `WHERE y=1` variants plus 1000 valid target-column conflict-target WHERE variants.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningConflictTargetWhereDynamicTest.php`
  - Result: `1 test files, 3002 assertions, 0 failures`.
- Adjacent UPSERT/RETURNING guard:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicExcludedAliasSqlTest.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningExcludedDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsert4ConflictTargetDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicYieldTest.php`
  - Result: `4 test files, 13380 assertions, 0 failures`.
- `php -l lanes/libsqlite/src/SQLiteUpsertReturningSql.php`
  - Result: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningConflictTargetWhereDynamicTest.php`
  - Result: no syntax errors.
- `git diff --check -- lanes/libsqlite`
  - Result: no output.

## Non-Overlap

- This does not repeat accepted `upsert4-8.1` through `upsert4-8.4` excluded/table-alias assignment and RETURNING behavior.
- This does not repeat `upsert5` redundant-conflict integrity, `upsert4` conflict-target admission, `upsert2` SELECT-input/yield matrices, autoincrement UPSERT, trigger/FK RETURNING, or row-value RETURNING slices.
- This slice owns the remaining upstream `upsert4-8.5` conflict-target WHERE name-resolution error through the SQL wrapper.

## Dependency Closure

- No new support component is needed. The patch reuses the bounded native PHP UPSERT RETURNING SQL parser/executor and adds validation inside that existing component.
