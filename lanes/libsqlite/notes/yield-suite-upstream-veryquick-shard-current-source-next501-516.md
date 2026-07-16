# SQLite upstream veryquick shard current-source next501-516

This slice extends the upstream suite evidence current-source mapped coverage directly after integrated next485-500. Each next501 through next516 entry admits only its matching focused veryquick shard row when provenance, lane-local artifact evidence, zero runner errors, duplicate-runner guards, and focused PHP PASS-line movement are all clean.

The implementation reuses the existing `upstreamVeryquickShardCurrentSourceNextEvidence()` helper and adds only the thin public next501 through next516 wrappers. The focused test in `SQLiteUpstreamSuiteEvidenceTest.php` verifies that each shard advances mapped coverage by one unit, counts only its own admitted unit, keeps next500 and sibling next501-516 counters false, and does not claim release/all parity.

Validation:

- `php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php`
- `php -l lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php`
- `git diff --check`
