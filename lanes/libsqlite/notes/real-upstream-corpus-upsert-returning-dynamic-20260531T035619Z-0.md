# real-upstream-corpus-upsert-returning-dynamic-20260531T035619Z-0

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test`
  - `upsert4-2.*`: conflict target matching with collation-sensitive compound unique indexes.
  - `upsert4-3.*`: expression-index conflict target matching, including redundant parentheses and collation identity.
  - `upsert4-4.*`: partial unique-index conflict target matching with exact predicate identity.
  - `upsert4-5.0`: mismatched expression collation rejection.
  - `upsert4-6.*`: `INSERT OR REPLACE` processing defers replace-style deletion until after the matching `ON CONFLICT` arm.

## Implementation

- Added `SQLiteRealUpstreamUpsert4ConflictTargetDynamicTest.php` with 2300 focused TestRunner PASS cases and 4900 behavior assertions over generic application rows.
- Fixed `SQLiteUpsertDoUpdateWherePlan::normalizeConflictTargetExpression()` to peel balanced redundant outer parentheses before comparing expression-index conflict targets.
- No new WordPress-specific API, fixture, class, method, function, or example was added.

## Red-first evidence

- Before the source fix, the new focused test failed `120` cases from `upsert4-3` redundant expression parentheses:
  - `1 test files, 4780 assertions, 120 failures`
- After the fix:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsert4ConflictTargetDynamicTest.php`
  - `1 test files, 4900 assertions, 0 failures`

## Non-overlap

This does not repeat the accepted `upsert5` arm-priority/yield matrix, `returning1-17` duplicate row stream, `upsert4-1` primary/secondary conflict update/move behavior, SELECT-source UPSERT RETURNING, autoincrement rowid sequence behavior, trigger/FK RETURNING, or row-value UPDATE/DELETE RETURNING slices. This slice owns upstream `upsert4.test` conflict-target admission and `INSERT OR REPLACE` precedence behavior.

## Dependency closure

No new support component is needed. The slice reuses the existing native PHP UPSERT conflict-target admission and row-array conflict-arm executor.
