# real-upstream-corpus-pragma-schema-dynamic-20260531T060941Z-0

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260531T060941Z-0`

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema5.test`
  - `schema5-1.2`
  - `schema5-1.4`
  - `schema5-1.6`

Focused PHP coverage:

- Added `SQLiteRealUpstreamPragmaSchemaDynamicSchema5ConstraintFailuresTest.php`.
- Adds 1050 focused PASS cases and 15750 assertions over the missing legacy schema5 constraint-failure cases.
- The batch verifies that old CREATE TABLE grammar with adjacent table constraints still imports into stable schema records, generates the expected autoindex surfaces, and preserves `PRAGMA table_info` primary-key ordinals for the upstream duplicate-key and CHECK-failure cases.

Non-overlap:

- Existing `SQLiteRealUpstreamPragmaSchemaDynamicSchema5LegacyTest.php` covers upstream `schema5-1.1`, `schema5-1.3`, `schema5-1.5`, and `schema5-1.7`.
- This slice covers the complementary upstream failure cases `schema5-1.2`, `schema5-1.4`, and `schema5-1.6`.
- It does not touch PRAGMA table-valued, schema_version/data_version, schema invalidation, schema2/schema3/schema4/schema6 equivalence, temp-store, object-name-collision, JSON, B-tree, WAL, VFS, SELECT, trigger, or UPSERT surfaces.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicSchema5ConstraintFailuresTest.php`
  - `1 test files, 15750 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This reuses the existing bounded schema import executor, schema record model, and PRAGMA schema catalog.
