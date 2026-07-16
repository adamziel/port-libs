# Release runner current/next count current next28

- Added `SQLiteUpstreamSuiteEvidence::releaseRunnerCurrentNextCountRecord()` to compare current accepted bounded-runner artifact directories with next candidate directories before dashboard count movement.
- The record reuses existing bounded-runner audit/log hydration and countability gates, then reports current/next countable artifact counts, count deltas, new/lost labels, test-total deltas, blocked evidence, missing paired logs, and next-gate guidance.
- Added `SQLiteReleaseRunnerCurrentNextCountCurrentNext28Test.php` with 50 focused PASS cases covering preserved counts, next-count increases, focused/release/all artifacts, `.audit` artifacts, relative/basename log pairing, distinct next heads, missing current/next directories, count regression, stale next artifacts, failed next artifacts, manifest mismatches, missing next logs, dirty current baselines, and invalid head guards.
- PASS delta: +50 verified focused PASS lines. `lane-status.json` `phpPass` moves from 10028 to 10078. `benchmarkDenominator.mapped` is unchanged because this slice makes existing bounded runner artifacts countable/current-vs-next comparable and does not map a new upstream inventory unit.

Focused evidence:

```text
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
No syntax errors detected in lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php

php -l lanes/libsqlite/tests/SQLiteReleaseRunnerCurrentNextCountCurrentNext28Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteReleaseRunnerCurrentNextCountCurrentNext28Test.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteReleaseRunnerCurrentNextCountCurrentNext28Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 515 assertions, 0 failures
```

Dependency closure: no new support component is needed; this composes lane-local bounded-runner audit/log artifacts, accepted-HEAD provenance, and existing countability gates only.

Non-overlap: avoids batch23 guarded runner preflight, batch25 release artifact hydration, accepted-head provenance batch reshaping, release-blocker closure records, and all accepted SQL/JSON/B-tree/WAL/VFS behavior clusters. This slice only adds the current-vs-next runner-count admission layer for suite handoff review.
