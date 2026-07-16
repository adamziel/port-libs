# SQLite release-runner upstream gap proof current-next37

This slice adds `SQLiteUpstreamSuiteEvidence::releaseRunnerUpstreamGapProofCurrentNext37()` as a bounded release-runner admission record for the current/next accepted-source gap.

It composes existing accepted-HEAD artifact provenance, explicit focused subset hydration, duplicate broad-runner detection, and focused PHP `phpPass` admission into one decision:

- `current-artifact-gap-proof-next-ready` when a current accepted zero-error artifact exists, the next source has no duplicate countable artifact, focused subsets are runnable, no broad runner is active, and the focused PHP output is admissible.
- `current-artifact-preserved-next-blocked` when the current artifact is preserved but hydration, active-runner, next-artifact, or PHP admission blockers remain.
- `blocked` when the current accepted artifact baseline is missing.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteReleaseRunnerUpstreamGapProofCurrentNext37Test.php
Focused test run: 1 selected test files (root lock skipped)
PASS current next37 proves current artifact and next runner gap with focused php admission
PASS current next37 blocks duplicate next artifact rather than relaunching broad runner
PASS current next37 reports focused subset hydration blockers
PASS current next37 preserves current artifact while broad runner is active
PASS current next37 blocks missing php pass admission
PASS current next37 matrix gap proof json-single
PASS current next37 matrix gap proof json-pair
PASS current next37 matrix gap proof wal-btree
PASS current next37 matrix gap proof pager-corrupt
PASS current next37 matrix gap proof select-index
PASS current next37 matrix gap proof pragma-trigger-fk
PASS current next37 matrix gap proof json-wal-btree
PASS current next37 matrix gap proof two-groups
PASS current next37 matrix gap proof three-groups
PASS current next37 matrix gap proof four-groups
PASS current next37 validates required inputs

1 test files, 116 assertions, 0 failures
```

Status delta:

- `phpPass`: `12903 -> 13019` from the 116 focused assertions above.
- `benchmarkDenominator.mapped`: unchanged; this is runner admission/countability proof, not a new upstream inventory unit.
- Root harness: not run; isolated micro-slice.

Non-overlap:

This avoids accepted release-runner parity ledger, current/next count, audit extension, artifact hydration, hydration cluster, guarded countability preflight, focused runner artifact admission, and release-blocker closure wrappers. It also avoids accepted SQL, JSON, WAL, VFS, B-tree, encoding, and Application behavior clusters; the only new surface is the current-next37 upstream gap proof before launching a next-source guarded runner.

Dependency closure:

No new support component is needed. The helper reuses lane-local manifest data, bounded runner artifact provenance parsing, explicit focused subset file hydration checks, process snapshots, and focused TestRunner output only.
