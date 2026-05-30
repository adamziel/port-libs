# real-upstream-corpus-trigger-fkey-dynamic-20260530T234137Z-0

Base accepted HEAD: `1e28a5dbe5f8813a907a64ec2d403f8339418de7`.

Implemented a non-overlapping real upstream `fkey1.test` dynamic corpus slice:

- `fkey1.test` `fkey1-4.0..4.2`: quoted identifier FK dequote-once behavior and ON DELETE CASCADE over quoted parent/child names.
- `fkey1.test` `fkey1-5.1..5.4`: self-referential `INSERT OR REPLACE` deletes the old row first, cascades descendants, then fails if the replacement parent was removed.
- `fkey1.test` `6.0..6.2`: partial UNIQUE parent indexes do not satisfy FK parent-key requirements until a full UNIQUE index exists.
- `fkey1.test` `7.1..7.2`: wide `PRAGMA foreign_key_check` key register allocation reports violations without over-reading table columns.
- `fkey1.test` `9.1`: quoted table names in FK delete processing are dequoted once for restrict/cascade routing.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicFkey1CorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicFkey1CorpusTest.php`
  - `1 test files, 9890 assertions, 0 failures`

Dependency closure: no new support component is needed. The slice reuses the existing native trigger/FK dynamic planner.

Non-overlap: avoids the already accepted fkey6/fkey8/triggerC, trigger7, trigger8, trigger9 statement-order, fkey5 foreign-key-check collation, and trigger2/4 view/program dynamic corpus files.
