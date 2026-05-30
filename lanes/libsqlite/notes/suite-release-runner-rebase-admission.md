# SQLite suite release-runner rebase admission

## Behavior

`SQLiteUpstreamSuiteEvidence::suiteReleaseRunnerRebaseAdmission()` rebases release-runner artifact admission onto the launcher-printed Base accepted HEAD:

- authoritative base: `23caf4af795588a2d84150ed1585e33865ff2b76`
- shared dashboard/status source hashes are preserved as provenance only and cannot replace the launcher base
- lane-local artifact paths must remain under `lanes/libsqlite/`
- guarded runner commands must name both `testfixture` and `testrunner.tcl`
- countable artifacts must provide `.test` script names, evidence text, at least one parsed test, and zero errors
- current countable artifacts cannot regress in next-source rows
- focused TestRunner output must come from exactly one lane test file, and the exact PASS-line count must match the expected delta
- active broad-suite runner snapshots block duplicate broad-runner admission
- release/all parity remains explicitly unclaimed

This removes a current-source rebase/countability blocker for suite admission. It is not a runtime SQL, JSON, WAL, B-tree, pager, VFS, PRAGMA, or trigger behavior slice.

## Focused Evidence

Command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSuiteReleaseRunnerRebaseAdmissionTest.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
89 PASS lines
1 test files, 933 assertions, 0 failures
```

Expected lane movement:

- `phpPass`: `28200 -> 28289` from exact focused PASS lines
- mapped coverage: `464 -> 465 / 1589` for the newly admitted rebased current-next74 release-runner artifact row
- release/all parity: unchanged and unclaimed

## Non-Overlap

Avoids accepted current-next72/current-next69 suite admission, current-next68 denominator admission, release/all parity ledgers, active-runner pgrep filtering, suite73 overlapping evidence, and batch68/69/72/73 runtime behavior clusters.

## Dependency Closure

No new support component is needed. The slice composes lane-local artifact rows, launcher Base accepted HEAD provenance, guarded runner command strings, zero-error result metadata, active-runner gates, and focused TestRunner PASS-line output only.
