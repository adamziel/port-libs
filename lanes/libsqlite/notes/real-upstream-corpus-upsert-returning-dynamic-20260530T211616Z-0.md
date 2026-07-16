# real-upstream-corpus-upsert-returning-dynamic-20260530T211616Z-0

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - Ported non-overlapping RETURNING name-resolution behavior from `returning1-6.0`, `returning1-7.2` through `returning1-7.8`, and `returning1-8.4`.
  - Focus: `RETURNING` rejects `TABLE.*`, `new.*`, `old.*`, target aliases, and `FROM` table columns, while target-table qualified columns resolve against the modified post-UPSERT row.

Implementation:

- Added `SQLiteUpsertDoUpdateWherePlan::returningRowsWithScope()` as a scoped RETURNING projection helper over existing post-UPSERT row images.
- Added `SQLiteRealUpstreamUpsertReturningScopeDynamicTest.php` with 1,201 focused assertions / PASS lines over dynamic variants derived from the upstream name-resolution cases.

Non-overlap:

- This does not repeat the accepted `upsert5.test` multi-arm ordering/catch-all corpus, the redundant-conflict corruption guard, `returning1-4.2` / `returning1-4.5` mixed insert/update RETURNING rows, or prior generic row-value RETURNING/savepoint/window helpers.
- No generated fake upstream script IDs, metadata-only admission rows, or domain-specific API names were added.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUpsertDoUpdateWherePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningScopeDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningScopeDynamicTest.php`
  - Result: `1 test files, 1201 assertions, 0 failures`

Dependency closure:

- No new support component is needed. The slice reuses the native UPSERT row-array executor and RETURNING projection path.
