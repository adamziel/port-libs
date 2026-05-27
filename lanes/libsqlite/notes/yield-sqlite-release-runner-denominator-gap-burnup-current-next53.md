### 2026-05-27 release-runner denominator gap burnup current-next53

Added `SQLiteUpstreamSuiteEvidence::releaseRunnerDenominatorGapBurnupCurrentNext53()` to classify current/next release-runner denominator movement without launching a broad upstream run. The record composes lane-local gap rows, mapped-count deltas, countable script deltas, active broad-runner suppression, and focused TestRunner phpPass admission.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteReleaseRunnerDenominatorGapBurnupCurrentNext53Test.php`
- Result: `1 test files, 1012 assertions, 0 failures`
- PASS-line delta: 58 new focused PASS cases
- `lane-status.json` phpPass: `19277 -> 19335`
- `benchmarkDenominator.mapped`: unchanged at `462 / 1589`; this slice does not claim newly hydrated upstream inventory units.

Dependency closure: no new support component needed. The slice reuses the lane-local upstream manifest, supplied current/next gap rows, supplied process snapshots, and local TestRunner output only.

Non-overlap: this avoids accepted release gap map/proof/audit/progress ledgers, guarded runner preflight, JSON table source/cursor/constraint work, SQL SELECT text/subquery/group/order clusters, VFS/WAL/B-tree/Unicode behavior clusters, batch23 runner countability preflight, and later accepted VFS/B-tree/WAL/JSON/SQL behavior surfaces. It only adds the current-next53 denominator burnup classification and focused PHP admission tests.
