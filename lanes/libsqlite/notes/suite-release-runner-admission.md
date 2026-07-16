# SQLite suite release-runner admission

## Behavior

`SQLiteUpstreamSuiteEvidence::suiteReleaseRunnerAdmission()` admits a bounded release-runner artifact only when all countability gates are clean:

- current artifact head matches the accepted base `c1b3825e121841b3669ec7027e8adbacaebb6283`
- next artifact head matches the named current-next72 admission slice
- artifact path is lane-local under `lanes/libsqlite/`
- guarded runner command names both `testfixture` and `testrunner.tcl`
- countable artifacts provide `.test` scripts, evidence text, at least one test, and zero errors
- countable current artifacts do not regress in the next row
- focused TestRunner output is from exactly one lane test file and the exact PASS-line delta matches the expected value
- active broad-suite runner snapshots block duplicate broad-runner admission
- release/all parity remains explicitly unclaimed

This is a release-runner/countability blocker-removal slice, not a runtime SQL/JSON/WAL/B-tree/VFS behavior slice.

## Focused Evidence

Command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSuiteReleaseRunnerAdmissionTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
92 PASS lines
1 test files, 845 assertions, 0 failures
```

Expected lane movement:

- `phpPass`: `26631 -> 26723` from exact focused PASS lines
- mapped coverage: `464 -> 465 / 1589` for the newly admitted current-next72 release-runner artifact row
- release/all parity: unchanged and unclaimed

## Non-Overlap

Avoids accepted current-next68/current-next69 suite denominator freshness, current-next65 focused PASS admission, release/all parity ledgers, active-runner pgrep filtering, batch68/batch69 runtime clusters, and queued ATTACH/JSON/pager/select/VFS/WAL behavior handoffs.

## Dependency Closure

No new support component is needed. The slice composes lane-local artifact rows, guarded runner command strings, zero-error result metadata, active-runner gates, and focused TestRunner PASS-line output only.
