# suite-denominator-current-next67

Adds `SQLiteUpstreamSuiteEvidence::suiteDenominatorCurrentNext67()` for a
bounded suite-denominator admission record that separates current accepted
anchors, queued next denominator rows, and stale duplicate accepted rows.

The helper admits only current-head focused TestRunner PASS-line evidence. It
blocks stale repository heads, inflated assertion-count deltas, active broad
runner snapshots, missing row evidence, hydration gaps, count regressions,
duplicate accepted rows that claim fresh movement, and next-ready rows with no
countable delta. It explicitly keeps release/all parity gated.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSuiteDenominatorCurrentNext67Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 437 assertions, 0 failures
```

The focused run emitted 67 PASS lines, so `lane-status.json` `phpPass` moves
from `25055` to `25122`. Mapped upstream coverage is unchanged because this
slice records current-next67 denominator admission/countability only; it does
not claim release/all parity or a new hydrated upstream inventory unit.

Non-overlap: avoids accepted batch64/batch65 suite admission, current-next56
focused PASS admission, batch52-batch55 denominator burnup ledgers, release/all
parity claims, and SQL/JSON/WAL/B-tree/VFS behavior clusters.

Dependency closure: no new support component is needed; the slice composes
lane-local denominator rows, stale accepted-row classification, active-runner
gating, and current-head focused TestRunner output only.
