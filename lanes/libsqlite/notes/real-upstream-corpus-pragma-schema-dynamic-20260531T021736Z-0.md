# real-upstream-corpus-pragma-schema-dynamic-20260531T021736Z-0

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260531T021736Z-0`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/starschema1.test`
  - `starschema1-1.1`: creates a wide fact table `t1(a01..a63,d)`.
  - `starschema1-1.1`: creates dimension tables `x01` through `x63`.
  - `starschema1-1.1`: creates fact-table and dimension-table join-key indexes.

Behavior:

- Added focused PHP coverage for the upstream star-schema metadata shape through
  `SQLitePragmaSchemaCatalog`.
- The tests verify `PRAGMA table_info`, `PRAGMA index_list`,
  `PRAGMA index_info`, and `PRAGMA table_list` over 120 generic star-schema
  variants.
- Focused coverage added: 481 TestRunner PASS cases and 129484 assertions.

Non-overlap:

- This does not repeat accepted PRAGMA schema table-info/index-info batches
  over small table layouts, schema3 refresh, schema4 namespace/name-collision,
  schema5 legacy constraints, schema6 rowid equivalence, trusted-schema
  policy, runtime PRAGMA state, generated-column xinfo, or prior
  eighth/ninth-thousand schema refresh batches.
- The new surface is the real upstream `starschema1.test` wide star-schema
  catalog shape needed by planner work: one fact table with 63 join-key
  columns, 63 dimension tables, and 126 join-key indexes.

Dependency closure:

- No new support component is needed. This reuses the existing lane-local
  schema record model and `SQLitePragmaSchemaCatalog` PRAGMA row emitters.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicStarSchemaTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicStarSchemaTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicStarSchemaTest.php`
  - `1 test files, 129484 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed with no output
