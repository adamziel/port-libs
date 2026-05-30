# real-upstream-corpus-pragma-schema-dynamic-20260530T195730Z-0

Base accepted HEAD: `688b5b5b02ee30d2a82f4468b5b909f17254ae0e`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`, section `pragma-6.2`, `PRAGMA table_info(t5)`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test`, section `7.3`, `RIGHT JOIN pragma_table_info('t4') ... pragma_table_info('t3')`.

Patch summary:

- Corrected the existing `pragma.test 6.2` t5 table-info expectation for table-level primary-key columns to upstream SQLite's `notnull=0` output.
- Added 250 dynamic application-schema pairs for `pragma4.test 7.3`.
- Added 4 focused TestRunner cases per pair covering right-side row preservation, wide-only column omission, matched-column default handling, and right-side row order.

Focused evidence:

- Before patch: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicCorpusTest.php` failed with 1 stale upstream expectation at `1 test files, 18848 assertions, 1 failures`.
- After patch: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicCorpusTest.php` passed with `1 test files, 20351 assertions, 0 failures`.
- Honest PASS-line delta: `+1001` distinct TestRunner PASS cases (`+1000` new `pragma4.test 7.3` cases and `+1` repaired upstream-expectation case).

Non-overlap:

- This slice does not add metadata-only runner rows or fabricated upstream script ids.
- It avoids already accepted PRAGMA schema table_info/index_list/foreign_key_list baseline coverage by focusing on upstream table-valued PRAGMA rowsets as RIGHT JOIN inputs.
- No WordPress-specific APIs, examples, or source names were added.

Dependency closure:

- No new support component is needed. The existing `SQLitePragmaSchemaCatalog` and `SQLiteAttachedSchemaCatalog` table-valued PRAGMA row APIs are reused.
