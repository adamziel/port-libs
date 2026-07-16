# SQLite upstream veryquick shard current-source next565-580

This slice extends upstream suite evidence current-source mapped coverage directly after integrated next549-564. Each next565 through next580 entry admits only its matching focused veryquick shard row when source provenance, lane-local artifact evidence, zero runner errors, duplicate-runner guards, and focused PHP PASS-line movement are all clean.

The implementation reuses the existing `upstreamVeryquickShardCurrentSourceNextEvidence()` helper and adds only thin public next565 through next580 wrappers. The focused test in `SQLiteUpstreamVeryquickShardCurrentSourceNext565580Test.php` verifies that each shard advances mapped coverage by one unit, counts only its own admitted unit, keeps next564 and prior counters false, and does not claim release/all parity.

Validation:

- `php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php`
- `php -l lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext565580Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext565580Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext549564Test.php`
- `git diff --check`
