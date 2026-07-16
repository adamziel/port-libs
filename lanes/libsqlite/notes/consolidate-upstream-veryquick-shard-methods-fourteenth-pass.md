Consolidated upstream veryquick shard production wrappers for the fourteenth
pass.

- Removed numbered production methods
  `upstreamVeryquickShardCurrentSourceNext411`,
  `upstreamVeryquickShardCurrentSourceNext412`,
  `upstreamVeryquickShardCurrentSourceNext414`, and
  `upstreamVeryquickShardCurrentSourceNext416` through
  `upstreamVeryquickShardCurrentSourceNext420` from
  `SQLiteUpstreamSuiteEvidence`.
- Migrated the direct tests for those shards to the stable canonical
  `upstreamVeryquickShardCurrentSourceShard($shard, ...)` entrypoint.
- No production compatibility shims or numbered replacement helpers were
  added.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php`
- `php -l lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext{411,412,414,416,417,418,419,420}Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext411Test.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext412Test.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext414Test.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext416Test.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext417Test.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext418Test.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext419Test.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext420Test.php`
  passed with `8 test files, 11264 assertions, 0 failures`.

Dependency closure: no new support component is needed; this is a production
method consolidation over existing upstream-suite evidence plumbing.
