# Real Upstream Corpus Trigger/FK Dynamic

Base accepted HEAD: `8bf0d9f81b29a5601901bb34dfd730670ed39bbc`

This lane ports a focused generic behavior cluster from the hydrated upstream SQLite corpus:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey1.test`
  - `fkey1-5.1..5.4`: self-referencing FK `INSERT OR REPLACE` deletes the conflicting row first, cascades child rows, then fails the deferred commit when the replacement references a parent removed by the cascade.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger1.test`
  - `trigger1-1.10`: an `AFTER DELETE` trigger can delete additional rows without corrupting the outer DELETE.
  - `trigger1-1.2..1.8`: trigger duplicate detection, transaction rollback of created triggers, `DROP TRIGGER`, `DROP TABLE` trigger cleanup, and TEMP trigger separation from the main schema catalog.

Implementation:

- Adds generic `SQLiteDynamicTriggerForeignKeyPlan`.
- Adds `SQLiteRealUpstreamTriggerFkeyDynamicCorpusTest` with application-neutral table/row names.
- Adds 1,136 focused TestRunner assertions. This is PASS/assertion growth only; no mapped denominator rows or release/all parity are claimed.

Verification:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCorpusTest.php`
  - `1 test files, 1136 assertions, 0 failures`

Dependency closure: no new support component is required; this reuses lane-local PHP row-array/catalog modeling and does not require external services, SQLite extension calls, or upstream Tcl execution at test time.
