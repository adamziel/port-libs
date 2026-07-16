# Upstream-suite accepted-head method consolidation

This consolidation pass removes two numbered public upstream-suite evidence
wrappers from the canonical production class:

- `releaseRunnerAcceptedHeadBurnup()` is now
  `releaseRunnerAcceptedHeadBurnup()`.
- `releaseRunnerAcceptedHeadMap()` is now
  `releaseRunnerAcceptedHeadMap()`.

Direct focused tests were renamed to stable unsuffixed filenames and migrated
to the canonical method names. No compatibility shim was left behind.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php`
- `php -l lanes/libsqlite/tests/SQLiteReleaseRunnerUpstreamBurnupTest.php`
- `php -l lanes/libsqlite/tests/SQLiteReleaseRunnerUpstreamMapTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteReleaseRunnerUpstreamBurnupTest.php lanes/libsqlite/tests/SQLiteReleaseRunnerUpstreamMapTest.php`
  - `2 test files, 641 assertions, 0 failures`

Dependency closure: no new support component is needed; this only renames and
routes existing upstream-suite evidence composition APIs.
