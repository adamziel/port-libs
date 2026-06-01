# Source-neutral option table defaults

Slice: `source-neutral-src-option-table-defaults-dynamic-20260601T060315Z-0`

Base accepted HEAD: `e2c270ed3a9929039fa26f779e2d74a975c61aa8`

## Scope

Neutralized a bounded production-source option-table default group:

- `SQLiteMultiColumnRangePlan` no longer falls back to `wp_options` in
  no-usable-plan diagnostics. It derives the scan table from the indexed table
  SQL when available and falls back to `app_settings`.
- `SQLiteTriggerSavepointReturningViewCurrentSourceNextPlan` now defaults
  view/savepoint/trigger/key identifiers to generic application settings names.
- `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan` now reports the source
  pattern expression with `app_settings`, `key_name`, and `key_value`.

`SQLiteCreateIndex::tableName()` was added as a small parser helper so the
multi-column planner can reuse the existing `CREATE INDEX` parser instead of
hardcoding an application table name.

No compatibility alias, hidden legacy map, or WordPress-specific wrapper was
added.

## Verification

- `php -l lanes/libsqlite/src/SQLiteCreateIndex.php`
- `php -l lanes/libsqlite/src/SQLiteMultiColumnRangePlan.php`
- `php -l lanes/libsqlite/src/SQLiteTriggerSavepointReturningViewCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteSourceNeutralOptionTableDefaultsDynamicTest.php`
- `php -l lanes/libsqlite/tests/SQLiteSourceNeutralDomainSpecificOptionClassesDynamicTest.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext202Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralOptionTableDefaultsDynamicTest.php lanes/libsqlite/tests/SQLiteSourceNeutralDomainSpecificOptionClassesDynamicTest.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext202Test.php lanes/libsqlite/tests/SQLitePlannerStat4RangeOrderCurrentSourceNext92Test.php lanes/libsqlite/tests/SQLiteTriggerSavepointReturningViewCurrentSourceNext134Test.php lanes/libsqlite/tests/SQLiteTriggerSavepointReturningViewCurrentSourceNext146Test.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` => `7 test files, 395 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCreateIndexExpressionCollationCorpusTest.php lanes/libsqlite/tests/SQLitePlannerMultiColumnRangeCurrentNext25Test.php` => `2 test files, 172 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` => passed.

No examples were changed, so no example smoke was needed.

## Dependency Closure

No new support component is needed. This cleanup preserves the existing native
PHP planner, trigger/view RETURNING, and UTF-16 LIKE/RTRIM behavior while
removing the owned production-source option-table defaults.

## Exclusions

A broad `rg 'wp_options|wp_option|wp_' lanes/libsqlite/src` still reports older
trigger/recursive-view source defaults outside this bounded option-table
default group. Those were not renamed in this patch because they belong to a
separate source-neutral trigger-family slice.
