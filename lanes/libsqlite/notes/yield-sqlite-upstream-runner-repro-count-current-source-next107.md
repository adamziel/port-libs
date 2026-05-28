# SQLite upstream runner repro count current-source next107

## Scope

- Lane/session: `port-dev-sqlite-yield-suite107`
- Base accepted HEAD: `432eeef3a780a882f63963e1ddad168744b946dd`
- Behavior: current-source guarded runner repro artifacts can be counted as preserved accepted-source evidence with exact focused TestRunner PASS-line admission, while next-source movement and release/all parity remain explicitly unclaimed.

## Non-overlap

This avoids accepted batch104/105 upstream-runner gap burnup and release/all countability surfaces. It does not duplicate current-source next94/99/102/104 admission or gap-burnup rows; this slice only removes the narrower blocker where a zero-error artifact from the launcher base needs to be preserved as current-source repro evidence before next-source artifacts are available.

## Verification

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamRunnerReproCountCurrentSourceNext107Test.php
Focused test run: 1 selected test files (root lock skipped)
PASS admits current-source repro artifacts without claiming next-source or release parity
PASS blocks next-source artifacts from current-source repro countability
PASS keeps stale current-source repro artifacts blocked with explicit blocker ids

1 test files, 43 assertions, 0 failures
```

## Dependency closure

No new support component is needed. The next107 repro count composes lane-local guarded runner audit/log artifacts, current accepted source provenance, manifest UUID gates, and focused TestRunner PASS-line output only.
