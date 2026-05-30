# SQLite suite release-runner upstream current-next81

Status: focused upstream-runner countability blocker removal.

This slice adds `SQLiteUpstreamSuiteEvidence::suiteReleaseRunnerUpstreamAdmission()` as a bounded admission gate for release/all runner artifacts whose countability depends on the current upstream source identity. A zero-error artifact can move mapped evidence only when all of these are true:

- the artifact repository HEAD matches launcher base `8170714ed6c9fe68a85cc98f050b32864eb598a3`;
- the SQLite commit matches manifest commit `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`;
- the SQLite manifest UUID matches `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`;
- the artifact path is lane-local, the guarded runner command targets `release` or `all`, parsed exit/errors are zero, target `.test` scripts are explicit, no broad runner is active, and the focused TestRunner PASS-line delta is exact.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSuiteReleaseRunnerUpstreamCurrentNext81Test.php
Focused test run: 1 selected test files (root lock skipped)
PASS current next81 admits upstream runner source identity case 01
...
PASS current next81 records dependency closure and next gate

1 test files, 862 assertions, 0 failures
```

Expected dashboard movement: `phpPass` +98 from the new focused PASS lines (`29984 -> 30082`). `benchmarkDenominator.mapped` remains aligned with the existing lane manifest at `465 / 1589`; this slice proves one upstream-runner source-identity countability blocker and does not claim full release/all parity.

Non-overlap: avoids accepted current-next75 release/all countability, current-next72/current-next74 runner admission, pgrep self-probe filtering, suite denominator admission, release blocker closure ledgers, and accepted SQL/JSON/WAL/VFS/B-tree behavior clusters.

Dependency closure: no new support component is needed; this composes lane-local artifact metadata, accepted HEAD/source identity, duplicate-runner gating, and focused TestRunner output only.
