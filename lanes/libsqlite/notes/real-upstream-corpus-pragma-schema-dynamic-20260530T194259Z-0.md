# Real Upstream Corpus: PRAGMA Schema Legacy CREATE Dynamic

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260530T194259Z-0`

Accepted base: `4fa72fa71b26a19fe54f9ce85268cd96396282ab`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema5.test`
  - `schema5-1.1` adjacent `PRIMARY KEY(a) UNIQUE(a)` legacy table constraint syntax
  - `schema5-1.3` chained named table constraints with `PRIMARY KEY(a)`, `CHECK`, and `UNIQUE(b)` in one comma term
  - `schema5-1.5` trailing constraint-name syntax after `UNIQUE(a)` and `PRIMARY KEY(b,c)`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema6.test`
  - `schema6-100` equivalent rowid table layouts where `INTEGER PRIMARY KEY` is a rowid alias and does not create a separate autoindex
  - `schema6-120` equivalent `WITHOUT ROWID` primary-key/unique layouts

Behavior ported:

- `SQLiteSchemaImportExecutor` now counts multiple table constraints inside a single legacy comma term, so a term like `CONSTRAINT one PRIMARY KEY(a) CONSTRAINT two CHECK(b<10) UNIQUE(b)` creates distinct primary-key and unique autoindex records.
- The importer no longer creates a synthetic autoindex for a rowid-alias column declared as `INTEGER PRIMARY KEY` unless an additional `UNIQUE` constraint is present, matching SQLite schema layout behavior from `schema6.test`.
- The directly coupled schema-import tests were updated to preserve the same scenarios with corrected SQLite rowid-alias autoindex expectations.

Focused coverage:

- `SQLiteRealUpstreamPragmaSchemaLegacyCreateDynamicTest.php`
  - 250 dynamic `schema5-1.1` adjacent primary/unique legacy syntax cases
  - 250 dynamic `schema5-1.3` chained named constraint cases
  - 250 dynamic `schema5-1.5` trailing constraint-name cases
  - 250 dynamic `schema6-100` equivalent rowid autoindex layout cases
  - 250 dynamic `schema6-120` `WITHOUT ROWID` primary/unique layout cases
- Focused PASS cases: 1250
- Behavior assertions in the new file: 7500

Verification:

- Red-first: the new corpus initially failed `schema5-1.3` because the importer only counted the first table constraint in a chained legacy term, and failed `schema6-100` because `INTEGER PRIMARY KEY` rowid aliases were being treated as separate autoindexes.
- `php -l lanes/libsqlite/src/SQLiteSchemaImportExecutor.php` -> no syntax errors
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaLegacyCreateDynamicTest.php` -> no syntax errors
- `php -l lanes/libsqlite/tests/SQLiteSchemaImportExecutorCurrentNext20Test.php` -> no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaLegacyCreateDynamicTest.php` -> `1 test files, 7500 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaImportExecutorCurrentNext20Test.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaLegacyCreateDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `3 test files, 7555 assertions, 0 failures`

Expected dashboard movement:

- `phpPass`: countable as `+1250` focused PASS lines from a fresh real upstream PHP corpus file.
- `mapped coverage`: unchanged; this is behavior-backed PASS-line growth, not a denominator admission patch.

Dependency closure:

- No new support component is needed. This reuses the existing lane-local schema import executor and PRAGMA schema catalog.

Non-overlap:

- This does not repeat prior `pragma.test`, `pragma3.test`, `pragma4.test`, `schema.test`, `schema2.test`, `schema3.test`, or `schema4.test` dynamic batches. It owns previously unported real upstream `schema5.test` legacy CREATE TABLE syntax and `schema6.test` rowid/without-rowid equivalent layout behavior.
