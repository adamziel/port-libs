# real-upstream-corpus-pragma-schema-dynamic-20260530T201017Z-0

Implemented a real upstream PRAGMA/schema corpus batch from SQLite upstream:

- Source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema6.test`
- Upstream scenarios:
  - `schema6-110`: `INTEGER PRIMARY KEY UNIQUE`, duplicate `UNIQUE(a)`, and explicit `CREATE UNIQUE INDEX` rowid-table layout equivalence.
  - `schema6-130`: rowid table versus `WITHOUT ROWID` layout divergence.

Changed files:

- `lanes/libsqlite/src/SQLiteSchemaImportExecutor.php`
- `lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchema6RowidDynamicTest.php`

Behavior delta:

- Fixed schema import autoindex counting so `INTEGER PRIMARY KEY` is not treated as a rowid alias when the table is declared `WITHOUT ROWID`.
- Added 1,000 distinct focused TestRunner PASS cases and 7,250 assertions over imported schema records and PRAGMA metadata for the upstream `schema6-110` and `schema6-130` layout families.
- Red-first evidence: the first focused run failed with 500 failures because `WITHOUT ROWID` primary-key indexes were undercounted. After the importer fix and oracle-aligned metadata assertions, the focused corpus passed.

Verification:

- `php -l lanes/libsqlite/src/SQLiteSchemaImportExecutor.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteSchemaImportExecutor.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchema6RowidDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchema6RowidDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchema6RowidDynamicTest.php`
  - `1 test files, 7250 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaImportExecutorCurrentNext20Test.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaLegacyCreateDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchema6RowidDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `4 test files, 14805 assertions, 0 failures`

Non-overlap:

- This does not repeat the accepted PRAGMA table-info/index-info/table-list batches, `schema4` rename/drop coverage, `schema5` legacy constraint grammar, or the prior `schema6-100`/`schema6-120` dynamic tests.
- This owns the previously unported `schema6-110` rowid unique-primary-key layout family and the `schema6-130` rowid versus `WITHOUT ROWID` divergence.

Dependency closure:

- No new support component is required. This reuses the existing schema import executor and PRAGMA schema catalog.
