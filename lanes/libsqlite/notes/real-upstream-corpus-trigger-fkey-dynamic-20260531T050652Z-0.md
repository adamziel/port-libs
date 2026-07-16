# real-upstream-corpus-trigger-fkey-dynamic-20260531T050652Z-0

Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/without_rowid3.test`.

Ported sections:

- `without_rowid3-4.1..4.4`: recursive foreign-key `ON DELETE CASCADE` actions must recurse through descendants even when `PRAGMA recursive_triggers = off`, while an ordinary recursive user trigger obeys the pragma.
- `without_rowid3-17.1.1..17.1.14`: `PRAGMA count_changes = 1` reports immediate FK failures before a row-count row, while deferred FK failures return the row count before the constraint result/finalize failure.

Implementation:

- Added generic `SQLiteDynamicTriggerForeignKeyPlan::withoutRowidRecursiveCascadePragmaPlan()`.
- Added generic `SQLiteDynamicTriggerForeignKeyPlan::withoutRowidCountChangesForeignKeyStatement()`.
- Added `SQLiteRealUpstreamTriggerFkeyDynamicWithoutRowid3CorpusTest.php` with 10,066 focused assertions over dynamic WITHOUT ROWID variants.

Verification:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicWithoutRowid3CorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicWithoutRowid3CorpusTest.php`
  - Result: `1 test files, 10066 assertions, 0 failures`

Dependency closure: no new support component is needed. The slice reuses existing native trigger/FK dynamic planning and adds WITHOUT ROWID source tagging plus assertion coverage for the real upstream sections above.

Non-overlap: this batch cites `without_rowid3.test` and avoids the accepted `fkey2.test` recursive pragma and count-change test files by adding WITHOUT ROWID-specific wrappers and assertions rather than changing the earlier fkey2 behavior.
