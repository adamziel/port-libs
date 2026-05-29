# Upstream Shard Method Suffix Consolidation

2026-05-29 isolated lane: `consolidate-remaining-numbered-upstream-shard-methods-cleanup`.

## Scope

- Consolidated the direct upstream exact-shard and veryquick-shard production entrypoints for `Next148`, `Next155`, `Next156`, `Next157`, and `Next158`.
- Replaced the numbered production methods with stable unsuffixed methods:
  - `upstreamExactShardRunnerCurrentSource`
  - `upstreamVeryquickShardCurrentSource`
  - `upstreamVeryquickShardRunnerCurrentSource`
- Migrated the direct focused tests to the stable methods while preserving the existing shard rows, provenance checks, runner gates, focused PHP admission checks, and release-parity exclusions.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php`
- `php -l` for the five changed direct upstream-shard tests
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamExactShardRunnerCurrentSourceAdmissionTest.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardRunnerCurrentSourceAdmissionTest.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceAdmissionTest.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceProvenanceTest.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceRunnerGateTest.php`
  - Result: `5 test files, 4042 assertions, 0 failures`

## Dependency Closure

No new support component is needed. This is source consolidation only; it reuses existing lane-local upstream-suite evidence parsing, focused PHP admission, duplicate-runner gates, and manifest-backed provenance helpers.
