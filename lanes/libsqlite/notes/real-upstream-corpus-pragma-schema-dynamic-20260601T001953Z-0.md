# real-upstream-corpus-pragma-schema-dynamic-20260601T001953Z-0

- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`.
- Ported sections: `pragma-24.0`, `pragma-24.1`, and `pragma-24.2`.
- Behavior: a 1024-byte-page database with a schema-backed `t1` table remains clean before corruption, then a tail-corrupted `t1` root leaf reports `database disk image is malformed` through both row materialization for `SELECT * FROM t1` and `PRAGMA integrity_check`.
- Patch: `SQLiteDatabase` now normalizes malformed table leaf record materialization to SQLite's disk-image error; `SQLitePragmaIntegrityCheck` now validates record payloads for schema table root leaf pages while preserving synthetic b-tree fixture behavior.
- Focused growth: new test file adds 1,022 distinct TestRunner PASS cases and 4,088 focused assertions.
- Verification: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicMalformedLeaf20260601Test.php` passed with `1 test files, 4088 assertions, 0 failures`.
- Regression verification: `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityQuickCheckCorpusTest.php lanes/libsqlite/tests/SQLitePragmaIntegrityBtreeOrderCurrentNext68Test.php lanes/libsqlite/tests/SQLitePragmaIntegrityCheckTableScopeExecuteTest.php lanes/libsqlite/tests/SQLiteBTreePageMoveTableOverflowCurrentNext17Test.php lanes/libsqlite/tests/SQLiteBTreePageSplitPointerMapCurrentNext34Test.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicMalformedLeaf20260601Test.php` passed with `6 test files, 5949 assertions, 0 failures`.
- Non-overlap: this does not repeat prior PRAGMA catalog/table-info/index-xinfo/foreign-key/table-list, `pragma.test` `20.*`, `22.*`, `23.*`, or `25.0` coverage. The new surface is specifically `pragma.test` `24.0..24.2` malformed table leaf payload detection.
- Dependency closure: no new support component is needed; the slice reuses existing native page, record, schema, and PRAGMA integrity helpers.
