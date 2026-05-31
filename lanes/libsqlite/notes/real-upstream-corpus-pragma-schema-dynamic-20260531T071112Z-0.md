# real-upstream-corpus-pragma-schema-dynamic-20260531T071112Z-0

Source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma3.test`
- Upstream sections: `pragma3-100` through `pragma3-201` and `pragma3-300` through `pragma3-340`.

Behavior ported:
- `PRAGMA data_version` initial rows for main/temp schemas.
- Assignment to `data_version` is a read-only no-op.
- Local commits keep the same connection's `data_version` stable while advancing the file change counter.
- Other connections, separate-process header observations, and shared-cache commit observers advance the reader-side `data_version`.
- Transaction rollback restores schema state without advancing the observer value.

Focused growth:
- Added `SQLiteRealUpstreamPragmaSchemaDynamicDataVersionCorpusTest.php`.
- 1000 distinct upstream-backed TestRunner PASS cases plus one source-citation case.
- 23005 focused assertions in the new file.

Non-overlap:
- This does not repeat accepted PRAGMA temp_store, cache_spill/freelist/schema_version, schema3/store-mode, table-valued PRAGMA, hidden constraint, integrity, or schema shadowing work.
- No new support component is needed; the test reuses `SQLitePragmaSchemaDataVersion`.

Verification:
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicDataVersionCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicDataVersionCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicFollowupTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicCacheSpillTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicTempStoreCorpusTest.php`
- `test -f lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php` returned `1`; API guard test is absent in this worktree.
- `git diff --check -- lanes/libsqlite`

Root harness: not run - isolated micro-slice.
