# Real Upstream Corpus: PRAGMA Schema Dynamic Version

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260531T072826Z-0`

Base accepted HEAD: `49647c646cee956ed1d4c9609a0c5aac0efc4e84`

Added `SQLiteRealUpstreamPragmaSchemaDynamicVersionRollbackCorpusTest.php`, a focused
real-upstream corpus test file covering SQLite upstream
`test/pragma.test` sections `pragma-8.1.1` through `pragma-8.1.18` and
`pragma-8.2.1` through `pragma-8.2.15`, plus `pragma3.test` `data_version`
read-only behavior.

Behavior covered:

- `PRAGMA schema_version` reads and writes the main schema header cookie.
- `PRAGMA aux.schema_version` remains isolated to the attached schema.
- Defensive mode ignores direct `schema_version` writes.
- Schema changes bump the schema cookie and change counter without changing the
  local `data_version`.
- `PRAGMA user_version` remains independent from `schema_version`.
- Transaction rollback restores main and attached `user_version` plus attached
  `schema_version` edits.
- `PRAGMA data_version` assignment is ignored as read-only.
- Signed negative `user_version` values are preserved.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicVersionCorpusTest.php`
  - `1 test files, 32004 assertions, 0 failures`
  - `1001` focused PASS lines

Dependency closure: no new support component is needed. The existing
`SQLitePragmaSchemaDataVersion` helper already models the upstream version
state and transaction rollback behavior needed for this corpus slice.
