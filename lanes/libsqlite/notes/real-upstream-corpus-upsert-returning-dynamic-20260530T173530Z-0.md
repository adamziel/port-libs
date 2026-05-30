# real-upstream-corpus-upsert-returning-dynamic-20260530T173530Z-0

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test`
  - Ported the six real generalized UPSERT schema variants from the opening `foreach` table definitions: rowid primary-key first, int primary-key first, `WITHOUT ROWID`, and the three reversed-column-order variants.
  - Reused the existing real multi-arm `upsert5` conflict/order corpus against each schema variant so column order, unique-index order, and `WITHOUT ROWID` table shape remain independently asserted.

## Coverage Delta

- Added `SQLiteRealUpstreamUpsertReturningDynamicSchemaVariantsTest.php`.
- Focused command passed with `1 test files / 1680 assertions / 0 failures`.
- Expected dashboard movement: `+1680` PHP PASS lines if accepted with this base; mapped coverage unchanged because this ports additional behavior assertions for already mapped upstream source files.

## Non-Overlap

- This does not repeat the earlier `20260530T170825Z` and `20260530T171246Z` UPSERT/RETURNING dynamic slices that covered a single normalized table shape, dynamic `WHERE` oracle comparisons, RETURNING projections, and the `SQLiteUpsertReturningDynamicCorpusPlan` base cases.
- This slice specifically owns schema-layout expansion for `upsert5.test` table variants, including `WITHOUT ROWID` and reversed column-order definitions.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicSchemaVariantsTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicSchemaVariantsTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicSchemaVariantsTest.php`
  - `1 test files, 1680 assertions, 0 failures`

## Dependency Closure

- No new support component is needed. The slice reuses the existing native PHP `SQLiteUpsertDoUpdateWherePlan` and `SQLiteUpsertReturningDynamicCorpusPlan` helpers.
