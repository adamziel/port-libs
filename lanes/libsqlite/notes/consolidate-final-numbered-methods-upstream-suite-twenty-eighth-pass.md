2026-05-29 - consolidate-final-numbered-methods-upstream-suite-twenty-eighth-pass

Scope:
- Consolidated the upstream veryquick shard current-source production entry methods for shards 159, 160, 161, 162, 164, 166, 167, 169, and 172 through 178.
- Removed the per-shard numbered public production wrappers from `SQLiteUpstreamSuiteEvidence`.
- Migrated the direct focused tests to the existing canonical `upstreamVeryquickShardCurrentSource()` method with the shard label passed as data.

Verification:
- `php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php`
- `php -l` for the 15 changed upstream veryquick shard test files.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext159Test.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext160Test.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext161Test.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext162Test.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext164Test.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext166Test.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext167Test.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext169Test.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext172Test.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext173Test.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext174Test.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext175Test.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext176Test.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext177Test.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext178Test.php`
  - Result: 15 test files, 17180 assertions, 0 failures.

Dependency closure:
- No new support component needed. This is production-method consolidation only; upstream-suite evidence behavior stays lane-local and data-driven through the existing manifest/evidence helper.

Non-overlap:
- This pass does not add functional coverage or change phpPass/mapped coverage.
- It avoids reintroducing any numbered production class/file/helper and removes only upstream-suite per-shard production entry methods in the assigned family.
