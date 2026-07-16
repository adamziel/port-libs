# Release runner artifact hydration current next25

- Added `SQLiteUpstreamSuiteEvidence::boundedRunnerArtifactDirectoryHydration()` to scan a guarded runner artifact directory for `.md` / `.audit` audit files, pair each audit with its bounded runner log, and compose the existing artifact/countability/provenance gates.
- Added `SQLiteReleaseRunnerArtifactHydrationCurrentNext25Test.php` with 48 focused PASS cases covering missing/empty directories, countable release-like artifacts, focused artifacts, stale accepted-HEAD blockers, manifest mismatches, failed artifacts, active-runner blockers, mixed countable/blocked directories, audit extension support, ignored non-audit files, stdout pairing, next-gate text, and dependency-closure text.
- PASS delta: +48 verified focused PASS lines. `lane-status.json` `phpPass` moves from 8739 to 8787. `benchmarkDenominator.mapped` is unchanged at 461 / 1589 because this slice hydrates/counts guarded runner artifact directories and does not map a new upstream inventory unit.

Focused evidence:

```text
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
No syntax errors detected in lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php

php -l lanes/libsqlite/tests/SQLiteReleaseRunnerArtifactHydrationCurrentNext25Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteReleaseRunnerArtifactHydrationCurrentNext25Test.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteReleaseRunnerArtifactHydrationCurrentNext25Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 681 assertions, 0 failures
```

Dependency closure: no new support component is needed; this reuses lane-local bounded-runner audit/log artifacts, existing artifact parsing, accepted-HEAD provenance checks, and countability gates.

Non-overlap: avoids batch23 guarded runner countability preflight, focused runner artifact admission, accepted-head provenance batch reshaping, release ledger/status-only movement, and all accepted SQL/JSON/B-tree/WAL/VFS behavior clusters. This adds a narrower current-next25 directory hydration layer that makes completed guarded runner artifacts directly consumable by the release/suite lane.
