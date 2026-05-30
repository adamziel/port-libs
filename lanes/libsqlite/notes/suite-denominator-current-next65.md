# suite-denominator-current-next65

Adds `SQLiteUpstreamSuiteEvidence::suiteDenominatorCurrentNext65()` as a lane-local countability gate for the current/next65 suite-denominator slice.

The record composes:

- current accepted HEAD and evidence HEAD equality;
- exact focused TestRunner PASS-line admission rather than assertion-total inflation;
- denominator row mapped-count and countable-script movement;
- row evidence, hydration, and regression blockers;
- duplicate broad upstream runner suppression;
- explicit `counts_release_parity = false`.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSuiteDenominatorCurrentNext65Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 397 assertions, 0 failures
```

PASS-line delta: 60 focused PASS lines.

Status impact:

- `phpPass`: 23976 -> 24036 for this lane handoff.
- `benchmarkDenominator.mapped`: unchanged at 463 / 1589 in lane status; the helper can admit a hydrated row, but this patch does not claim release/all parity or a new mapped inventory unit without integrator acceptance.

Non-overlap:

This avoids accepted release/all closure, focused runner artifact admission, current-next56 focused PASS admission, batch52-batch55 denominator burnup ledgers, artifact-directory provenance, SQL/JSON/WAL/B-tree/VFS behavior clusters, and Application runtime behavior smokes. The new surface is current-next65 suite-denominator countability with current-head focused PASS-line gating.

Dependency closure:

No new support component is needed. The slice composes existing lane-local manifest evidence, TestRunner output parsing, and active broad-runner gate logic.
