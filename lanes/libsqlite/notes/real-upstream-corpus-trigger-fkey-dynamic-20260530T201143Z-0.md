# real-upstream-corpus-trigger-fkey-dynamic-20260530T201143Z-0

- Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260530T201143Z-0`
- Base accepted HEAD: `c1a0d2c80ea721e0595b20a5cbe43c5043856066`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey5.test`
- Ported sections:
  - `fkey5-1.1..8.7`: `PRAGMA foreign_key_check` violation row shape, child table filtering, scalar parent keys, parent collation use, composite parent keys, and NULL child-key suppression.
  - `fkey5-9.1.1..9.4`: missing parent tables report violations for non-NULL child keys without making all-NULL child rows violations.
  - `fkey5-10.1..10.3`: child `WITHOUT ROWID` violations report a NULL rowid.
  - `fkey5-11.0..11.1`: invalid parent key definitions produce the exact `foreign key mismatch - "c11" referencing "tt"` diagnostic.
  - `fkey5-12.0..13.12`: schema/table-scoped `foreign_key_check` behavior.

## Focused Coverage

- Added `SQLiteDynamicTriggerForeignKeyPlan::foreignKeyCheckCorpus()` as a bounded native PHP model for the upstream `fkey5.test` foreign-key-check behavior.
- Added `SQLiteRealUpstreamTriggerFkeyCheckDynamicTest.php` with 1,593 focused TestRunner PASS cases and 2,676 assertions.
- Non-overlap: this does not repeat accepted `fkey1` replacement cascade, `fkey2` action/savepoint/nocase/composite behavior, `fkey3` self-reference, `fkey4` autocommit cleanup, `fkey6` deferred pragma checks, `fkey8` action journal behavior, trigger2 view behavior, trigger3 RAISE behavior, or existing PRAGMA foreign-key catalog/index-xinfo families. The new surface is `fkey5.test` `PRAGMA foreign_key_check` corpus semantics.

## Verification

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyCheckDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyCheckDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyCheckDynamicTest.php`
  - `1 test files, 2676 assertions, 0 failures`

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP trigger/FK corpus helper and the hydrated upstream SQLite Tcl test cache as source truth.

## Next

Continue trigger/FK real-corpus work only with a distinct unported upstream surface, such as `trigger4.test`/`trigger5.test` behavior, or pivot to another high-yield domain if the remaining trigger/FK ranges overlap accepted batches.
