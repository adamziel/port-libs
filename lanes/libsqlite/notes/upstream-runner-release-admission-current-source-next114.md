# upstream-runner-release-admission-current-source-next114

Scope: lane-local upstream-runner release-admission blocker removal only.

This slice adds `SQLiteUpstreamSuiteEvidence::upstreamRunnerReleaseAdmissionCurrentSourceNext114()`. It admits one focused current-source runner row only when all of these gates are clean:

- launcher base `67b9065fe584e293134a85272e27bb677a0554af` is marked authoritative;
- dashboard/status source is `178c51ea36ed3508aafbb8913a32694e327e1da6`;
- implementation source is `1789166262039886c5a87db06de0843d211b94e2`;
- artifact path is lane-local under `lanes/libsqlite/`;
- guarded runner command includes `testfixture`, `testrunner.tcl`, and `--stop-on-error`;
- runner exit/errors are zero with concrete `.test` scripts and a positive test count;
- release scope is `focused-current-source`, not `release-all`;
- no duplicate broad runner is active in the supplied process snapshot;
- focused TestRunner PASS-line delta exactly matches the expected value.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamRunnerReleaseAdmissionCurrentSourceNext114Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 817 assertions, 0 failures
```

Additional checks:

```text
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
No syntax errors detected in lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php

php -l lanes/libsqlite/tests/SQLiteUpstreamRunnerReleaseAdmissionCurrentSourceNext114Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteUpstreamRunnerReleaseAdmissionCurrentSourceNext114Test.php
```

Expected dashboard movement:

- `phpPass`: `43574 -> 43643` from the 69 focused PASS lines in the verified TestRunner output.
- `benchmarkDenominator.mapped`: `604 -> 605 / 1589` for the newly admitted current-source focused release-admission row.
- release/all parity: unchanged and explicitly unclaimed.

Non-overlap:

This avoids accepted next102 admission, next104 gap burnup, next108 suite evidence rebase, batch107/108 runner evidence, accepted SQL/JSON/WAL/VFS/B-tree/PRAGMA/SELECT runtime clusters, and queued next112/113 runtime surfaces. It is a focused suite-admission/countability blocker artifact only.

Dependency closure:

No new support component is needed. The patch composes lane-local artifact rows, authoritative launcher-base provenance, current dashboard/status/implementation heads, focused release-scope decisions, active-runner gates, and local focused TestRunner PASS-line output.
