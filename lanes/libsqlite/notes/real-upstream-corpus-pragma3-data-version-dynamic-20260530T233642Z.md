# Real Upstream Pragma3 Data Version Dynamic

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260530T233642Z-0`

Accepted base: `d7c5d7f50d0d0c3f24c91125036d23912559b628`

Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma3.test`

Ported sections:

- `pragma3-100` through `pragma3-102`: `PRAGMA data_version` starts at `1` for `main` and `temp`, and assignment is ignored.
- `pragma3-110` through `pragma3-150`: commits made on the same connection do not change that connection's observed `data_version`.
- `pragma3-160` through `pragma3-195`: another connection's commit advances a connection-local value once on the next read.
- `pragma3-200` through `pragma3-300`: process-writer and shared-cache changes follow the same generation behavior as another connection.

Implemented behavior:

- Added `SQLitePragmaDataVersionTracker`, a generic connection-local tracker for `PRAGMA data_version`.
- Added `SQLiteRealUpstreamPragma3DataVersionDynamicTest.php` with 250 variants plus a source-citation test.
- The first focused run was red: connection-local counters incorrectly seeded from file generation, and same-connection commits were observed as external changes. The tracker now starts every connection-local counter at `1` and records same-connection commit generations as already seen.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLitePragmaDataVersionTracker.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragma3DataVersionDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragma3DataVersionDynamicTest.php` passed: `1 test files, 5754 assertions, 0 failures`.
- PASS-line growth: 1001 focused TestRunner PASS cases.

Non-overlap:

- Avoids existing `SQLiteRealUpstreamPragmaSchemaDynamicShadowingTest.php` coverage for `pragma4.test`, `pragma5.test`, and `schema.test`.
- Avoids existing `SQLiteRealUpstreamPragmaSchema4NameCollisionDynamicTest.php` coverage for `schema4.test`.
- Does not add domain-specific source API, fixture names, or compatibility wrappers.

Dependency closure:

- No new support component is needed. The slice uses a small native PHP tracker under `lanes/libsqlite/src` and existing focused TestRunner infrastructure.

Next useful adjacent slice:

- Port `schema5.test` legacy table-constraint syntax only if it can be modeled as real DDL/constraint behavior with non-overlapping focused assertions.
