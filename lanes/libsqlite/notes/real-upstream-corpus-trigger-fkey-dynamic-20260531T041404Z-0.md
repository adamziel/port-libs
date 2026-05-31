# real-upstream-corpus-trigger-fkey-dynamic-20260531T041404Z-0

Implemented a real upstream fkey1 trigger/FK regression cluster from
`/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey1.test`.

Upstream sections:

- `fkey1.test` `fkey1-8.1`: corrupt `sqlite_stat1` system-table shape with a
  child foreign key referencing `sqlite_stat1`; dropping an unrelated shadow
  table must not leak or crash during FK nested-parse processing.
- `fkey1.test` `fkey1-8.2..8.3`: renamed corrupt `sqlite_stat1` autoindex is
  detected by `REINDEX` as `database disk image is malformed`.

Focused behavior:

- Added `SQLiteDynamicTriggerForeignKeyPlan::fkey1CorruptStatSchemaForeignKeyPlan()`.
- Added `SQLiteRealUpstreamTriggerFkeyDynamicCorruptStatSchemaTest.php` with
  500 deterministic generic child-table variants for the safe drop path and
  500 deterministic variants for the malformed reindex path.
- Focused assertion count: 12,507 assertions / 0 failures.

Non-overlap:

- Existing accepted fkey1 coverage already covered quoted cascades,
  self-referencing replace cascades, partial parent indexes, and wide
  `foreign_key_check` register allocation. This slice covers only the
  corrupt-stat-table FK nested-parse and malformed reindex regression.

Dependency closure:

- No new support component needed. The patch reuses the existing dynamic
  trigger/FK corpus helper and adds a bounded native PHP model for corrupt
  schema FK processing.

Verification:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCorruptStatSchemaTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCorruptStatSchemaTest.php`
- `git diff --check -- lanes/libsqlite`
