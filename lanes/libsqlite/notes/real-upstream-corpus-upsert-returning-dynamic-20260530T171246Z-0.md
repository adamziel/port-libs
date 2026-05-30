# Real Upstream Corpus: UPSERT / RETURNING Dynamic

Session: `port-dev-sqlite-yield-dyn-real-upsert-20260530T171246Z`
Base accepted HEAD: `6a6cf1aff10d18a35ed78eace2a787cb40f2b02d`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - `returning1-4.2`, `returning1-4.5`, and `returning1-17`: parser-level
    UPSERT RETURNING final row images, mixed insert/update return order, and
    repeated conflict rows returning the existing row identifier.

## Handoff Delta

- Added `SQLiteRealUpstreamUpsertReturningSqlDynamicTest.php`.
- Focused PASS-line growth: `+131` new TestRunner cases.
- Focused assertion growth: `653` assertions.
- Mapped coverage: unchanged; this ports real upstream behavior into PHP tests
  but does not claim new denominator rows.

## Non-Overlap

This batch avoids older domain-shaped UPSERT/RETURNING savepoint, row-value,
trigger, and generated current-next surfaces. It leaves the accepted
`SQLiteRealUpstreamUpsertReturningDynamicTest.php` coverage untouched and adds
separate parser-level `SQLiteUpsertReturningSql` coverage with generic row names
for RETURNING row-image behavior from hydrated SQLite `.test` files.

## Dependency Closure

No new support component is needed. The tests reuse existing native PHP
`SQLiteUpsertReturningSql` behavior.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningSqlDynamicTest.php`
  - `1 test files, 653 assertions, 0 failures`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningSqlDynamicTest.php`
  - no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - not run: guard path is absent in this worktree
