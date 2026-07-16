# Suite Release Runner Countability Rebase Current Source Next82

## Behavior

This slice adds lane-local admission logic for a release-runner countability artifact whose authoritative base is the launcher-printed accepted HEAD, while the dashboard, lane-status, and latest integrated implementation source heads are recorded as provenance instead of being treated as mutable current base.

The helper admits one new countability row only when:

- `launcher_base_head` matches `bd3c72c033cc76366294ed6e08431afa73ecb9af`.
- Dashboard/status/implementation source heads match the supervisor-provided current-source evidence.
- The artifact path is lane-local under `lanes/libsqlite/`.
- The runner command names `testfixture` and `testrunner.tcl`.
- The artifact has zero errors and at least one `.test` script.
- No duplicate broad release/all runner is active.
- Focused TestRunner output has exactly the expected PASS-line delta.

It explicitly does not claim release/all parity.

## Focused Evidence

Command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSuiteReleaseRunnerCountabilityRebaseCurrentSourceNext82Test.php
```

Output:

```text
Focused test run: 1 selected test files (root lock skipped)
90 PASS lines
1 test files, 1268 assertions, 0 failures
```

Expected dashboard movement:

- `phpPass`: `31014 -> 31104` from the verified focused PASS-line delta.
- Mapped upstream coverage: `465 / 1589 -> 466 / 1589` for the distinct current-source release-runner countability blocker row.

## Non-Overlap

This avoids accepted current-next75 release/all countability, current-next74/current-next72 runner admission, current-next69/current-next68 suite-denominator freshness gates, and accepted batch68-79 ATTACH, JSON, LIKE, recursive SELECT, VFS, WAL, B-tree, PRAGMA, trigger, and pager implementation clusters.

## Dependency Closure

No new support component is needed. The slice composes lane-local artifact rows, launcher Base accepted HEAD provenance, dashboard/status/source heads, guarded runner command metadata, duplicate-runner gates, and focused TestRunner PASS-line output only.
