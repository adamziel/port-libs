# Real Upstream Corpus: PRAGMA Schema/Data Version Dynamic

Base accepted HEAD: `ee0f86482fec002ad61b846f39a1a36b0fe0ecc4`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma3.test`
- Ported sections: `pragma3-100` through `pragma3-102`, `pragma3-110` through `pragma3-195`, `pragma3-300` through `pragma3-340`, `pragma3-400` through `pragma3-430`, and `pragma3-510` through `pragma3-520`.

Patch summary:

- Added `SQLiteRealUpstreamPragmaSchemaDataVersionDynamicTest.php`.
- Adds 5001 distinct focused TestRunner PASS cases.
- Adds 28003 focused behavior assertions.
- Exercises `SQLitePragmaSchemaDataVersion` behavior for initial `PRAGMA data_version`, ignored writes, local commit preservation, other-connection visibility, uncommitted writer isolation, shared-cache-equivalent visibility, WAL-equivalent visibility, and empty transaction preservation.
- Non-overlap: does not touch accepted `pragma2.test` cache-spill dynamic coverage, PRAGMA runtime/cache-spill status, schema catalog/table-valued PRAGMA files, or any domain-shaped API.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDataVersionDynamicTest.php`
- Result: `1 test files, 28003 assertions, 0 failures`, 5001 PASS lines.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDataVersionDynamicTest.php`
- Result: no syntax errors.

Expected dashboard movement:

- `phpPass`: `1040058 -> 1045059` after acceptance.
- `phpFail`: remains `0`.
- Mapped denominator: unchanged at `1589 / 1589`.

Dependency closure:

- No new support component is needed. This reuses the existing native `SQLitePragmaSchemaDataVersion` support class.
