# Real Upstream Corpus: PRAGMA Schema Dynamic Missing Target

- Micro-slice: `real-upstream-corpus-pragma-schema-dynamic-20260531T122058Z-0`
- Accepted base: `82ffc15bcb109224eed304cd069ec63109a1767a`
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
- Upstream scenarios: `pragma-6.2.1` (`pragma table_info;`) and `pragma-6.3.2` (`pragma foreign_key_list;`)

Behavior ported:

- Direct no-target schema PRAGMAs `PRAGMA table_info`, `PRAGMA table_info()`, `PRAGMA foreign_key_list`, and `PRAGMA foreign_key_list()` now return empty rowsets instead of throwing.
- Table-valued no-target forms `pragma_table_info()` and `pragma_foreign_key_list()` now return empty rowsets.
- Empty quoted targets such as `pragma_table_info('', 'temp')` and schema-qualified direct forms stay schema-pinned and empty.
- Ordinary schema-qualified target resolution remains covered by dynamic main/temp/attached catalogs.

Focused evidence:

- New selected PASS growth: `+1001` TestRunner PASS cases.
- New selected behavior assertions: `31007` assertions in `SQLiteRealUpstreamCorpusPragmaSchemaDynamicMissingTarget20260531Test.php`.
- Combined focused verification: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicMissingTarget20260531Test.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicFollowupTest.php lanes/libsqlite/tests/SQLitePragmaForeignKeyListCurrentNext18Test.php lanes/libsqlite/tests/SQLitePragmaSchemaCatalogTest.php lanes/libsqlite/tests/SQLitePragmaIndexTableValuedCurrentNext21Test.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` => `6 test files, 31286 assertions, 0 failures`.
- Diff hygiene: `git diff --check -- lanes/libsqlite` passed.

Non-overlap:

- This handoff does not repeat accepted atof1 decimal REAL, types.test record storage, scalar-subquery arity, VFS superlock, row-value LIMIT introspection, app-WAL post-recovery recovery, JSON, VFS, WAL, btree, or planner slices.
- The mapped denominator remains `1589 / 1589`; this is selected PASS-line growth over already mapped upstream corpus inventory.

Dependency closure:

- No new support component is needed. The existing PHP schema PRAGMA catalog parser and attached-schema catalog dispatcher were extended in place.
