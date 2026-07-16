# Release Runner Artifact Directory Current Next27

- Slice: `yield-sqlite-release-runner-gap-close-current-next27`.
- Behavior: `SQLiteUpstreamSuiteEvidence::boundedRunnerArtifactDirectoryRecord()` now exposes directory-level audit file count, missing-directory counters, missing-log labels, countable/blocked artifact totals, and accepted-HEAD zero-error totals while reusing the existing bounded runner artifact-set countability gate.
- Focused tests: added 2 TestRunner PASS cases to `SQLiteUpstreamSuiteEvidenceTest.php` covering a mixed guarded artifact directory and a missing directory.
- Verified command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteEvidenceTest.php`
- Verified output: `1 test files, 937 assertions, 0 failures`.
- PASS-line delta: focused file moved from 61 PASS lines before this slice to 63 PASS lines after this slice, so `lane-status.json` `phpPass` is updated from `9342` to `9344`.
- Non-overlap: this does not repeat batch23/batch24 release-runner hydration, active-runner pgrep filtering, focused artifact admission, accepted-head provenance batches, or broad release/all execution. It closes the next directory-level artifact collection/countability gap for already-produced guarded runner output.
- Dependency closure: no new support component is needed; the slice parses lane-local bounded runner audit/log artifacts only.
- Next gate: feed the next guarded release/all artifact directory through the directory record, publish only zero-error accepted-HEAD entries, and keep stale, failed, active, missing-log, or missing-directory records explicit.
