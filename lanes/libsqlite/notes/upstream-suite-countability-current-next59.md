# Upstream suite countability current-next59

This slice adds `SQLiteUpstreamSuiteEvidence::currentNext59AdmissionPlan()` as a bounded upstream-suite admission record for the next focused runner pass.

It removes a runner/countability ambiguity before launching another SQLite upstream subset:

- candidate groups become runnable only when the hydrated `testfixture` and `testrunner.tcl` inputs exist;
- duplicate broad `all`/`release`/`mptest` runners block launch even when focused groups are otherwise ready;
- focused PHP output must be a single focused lane test with zero failures before the plan can move `phpPass`;
- ready focused subset commands never count as release/all parity until parsed zero-error upstream artifacts exist.

## Focused evidence

Command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamSuiteCountabilityCurrentNext59Test.php
```

Output:

```text
Focused test run: 1 selected test files (root lock skipped)
PASS blocks current next59 when hydrated upstream runner inputs are missing
PASS marks hydrated current next59 focused groups ready without claiming release parity
PASS blocks current next59 launch while a broad upstream runner is active
PASS blocks current next59 when focused php admission is not countable
PASS current next59 candidate script 01 remains concrete and safe
...
PASS current next59 candidate script 38 remains concrete and safe

1 test files, 168 assertions, 0 failures
```

New focused PASS-line delta: `42`.

## Dashboard movement

- `phpPass`: `21435 -> 21477`.
- `phpFail`: unchanged at `0`.
- `benchmarkDenominator.mapped`: unchanged at `463 / 1589`; this is admission/countability behavior, not a new upstream inventory unit.

## Non-overlap

This avoids release-runner burnup, parity ledgers, focused artifact admission, suite progress maps, current/next gap ledgers, and accepted SQL/JSON/WAL/B-tree/VFS/encoding behavior clusters. The new surface is current-next59 focused-runner admission gating over concrete candidate subsets.

## Dependency closure

No new support component is needed. The planner composes existing lane-local focused subset planning, active-runner detection, and focused TestRunner admission only.
