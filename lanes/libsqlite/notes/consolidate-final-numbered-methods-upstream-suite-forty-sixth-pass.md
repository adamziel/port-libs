# Upstream Suite Prepared Window Consolidation

This pass consolidates the upstream veryquick prepared-window wrappers that were still exposed as numbered `CurrentSourceNext` method and test names. The canonical production entry points are now:

- `SQLiteUpstreamSuiteEvidence::upstreamVeryquickShardPreparedWindowAlpha()`
- `SQLiteUpstreamSuiteEvidence::upstreamVeryquickShardPreparedWindowBeta()`

The direct tests were migrated to stable prepared-window filenames and helper names, and their fixture rows now use descriptive shard labels instead of numbered `CurrentSourceNext` test names.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php`
- `php -l lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardPreparedWindowAlphaTest.php`
- `php -l lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardPreparedWindowBetaTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardPreparedWindowAlphaTest.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardPreparedWindowBetaTest.php` -> `2 test files, 84 assertions, 0 failures`

Dependency closure: no new support component is needed; this is a production API/test-name consolidation over existing lane-local upstream-suite evidence admission logic.
