## real-upstream-corpus-pragma-schema-dynamic-20260530T214355Z-0

Added `SQLiteRealUpstreamPragmaSchemaDynamicDataVersionMatrixTest.php`, a
real upstream PRAGMA/schema dynamic corpus matrix based on hydrated SQLite
`test/pragma3.test`.

Upstream source sections:

- `pragma3.test` `pragma3-100` through `pragma3-102`: initial
  `PRAGMA data_version` and ignored writes.
- `pragma3.test` `pragma3-110` through `pragma3-130`: local writes and local
  commits keep the same connection-local `data_version`.
- `pragma3.test` `pragma3-140` through `pragma3-190`: other connection commits
  advance the observed `data_version`.
- `pragma3.test` `pragma3-160` through `pragma3-190`: transaction-local reads
  remain stable until an external change is observed.

Focused coverage:

- 5,001 distinct TestRunner PASS cases.
- 27,003 behavior assertions.
- Generic schema names only: `tenantN`, `schema_version`, `data_version`, and
  connection/header terms.

Non-overlap:

- This does not repeat existing PRAGMA schema catalog/table-info/index-info,
  schema4 object collision, schema invalidation, thousand-row table metadata,
  or cache-spill coverage. It owns a fresh `pragma3.test` connection-local
  data-version matrix using `SQLitePragmaSchemaDataVersion`.
- Countable movement is PASS-line and assertion growth only. Mapped upstream
  denominator remains unchanged at `1589 / 1589`.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicDataVersionMatrixTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicDataVersionMatrixTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicDataVersionMatrixTest.php`
  - `1 test files, 27003 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Dependency closure:

- No new support component is needed. This reuses the existing
  `SQLitePragmaSchemaDataVersion` bounded PRAGMA state model.
