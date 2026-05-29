# SQLite upstream veryquick shard current-source next517-532

This slice extends upstream suite evidence current-source mapped coverage directly after integrated next501-516. Each next517 through next532 entry admits only its matching focused veryquick shard row when provenance, lane-local artifact evidence, zero runner errors, duplicate-runner guards, and focused PHP PASS-line movement are clean.

The implementation reuses the existing `upstreamVeryquickShardCurrentSourceNextEvidence()` helper and adds only thin public next517 through next532 wrappers. The focused test in `SQLiteUpstreamSuiteEvidenceTest.php` verifies that each shard advances mapped coverage by one unit, counts only its own admitted unit, keeps next516 and sibling next517-532 counters false, and does not claim release/all parity.
