# real-upstream-corpus-upsert-returning-dynamic-20260531T050803Z-0

Base accepted HEAD: `7174979f2808c9ccf08c3331545660695c77e192`.

## Scope

Implemented secondary UNIQUE constraint validation in
`SQLiteUpsertReturningDynamicPlan` for successful UPSERT inserts and
`DO UPDATE` row images. The targeted conflict still decides whether the UPSERT
arm fires, but the resulting inserted or updated row now aborts if it collides
with another declared unique target.

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test`
  - `upsert1-700` through `upsert1-780`: targeted UPSERT constraints fire
    before other unique constraints.
  - `upsert1-1000`: unresolved/secondary constraint failures abort instead of
    silently yielding rows.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - `returning1-1.1` through `returning1-1.4`: `RETURNING` emits successful
    row images only.

## Focused Growth

- Added `SQLiteRealUpstreamUpsertReturningDynamicSecondaryConflictTest.php`.
- New focused TestRunner PASS lines: `1002`.
- New focused assertions: `2504`.
- Non-overlap: this is not another broad `upsert4`/`upsert5` arm-order matrix,
  no-target row-stream batch, target-admission helper, or RETURNING projection
  variant. It covers the missing production behavior in the dynamic executor
  where a successful targeted arm must still check other unique constraints.

## Verification

- `php -l lanes/libsqlite/src/SQLiteUpsertReturningDynamicPlan.php`
  - passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicSecondaryConflictTest.php`
  - passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicSecondaryConflictTest.php`
  - `1 test files, 2504 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamUpsertReturningDynamicTest.php lanes/libsqlite/tests/SQLiteUpstreamUpsertReturningDynamicRealCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicWhereTest.php`
  - `3 test files, 2723 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite`
  - passed.

## Dependency Closure

No new support component is needed. This reuses the existing row-array
`SQLiteUpsertReturningDynamicPlan` executor and its declared conflict-target
metadata.
