# Release Runner Suite Denominator Burn-Up Current Next54

## Scope

Adds `SQLiteUpstreamSuiteEvidence::releaseRunnerSuiteDenominatorBurnup()` for lane-local upstream runner denominator evidence. The helper classifies current versus next mapped denominator units, runner-countable rows, family totals, advanced/preserved/open/regressed IDs, and focused PHP admission without counting release/all parity.

## Focused Evidence

Command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteReleaseRunnerSuiteDenominatorBurnupTest.php
```

Output:

```text
Focused test run: 1 selected test files (root lock skipped)
PASS current next54 suite denominator burnup maps case 1
PASS current next54 suite denominator burnup maps case 2
PASS current next54 suite denominator burnup maps case 3
PASS current next54 suite denominator burnup maps case 4
PASS current next54 suite denominator burnup maps case 5
PASS current next54 suite denominator burnup maps case 6
PASS current next54 suite denominator burnup maps case 7
PASS current next54 suite denominator burnup maps case 8
PASS current next54 suite denominator burnup maps case 9
PASS current next54 suite denominator burnup maps case 10
PASS current next54 suite denominator burnup maps case 11
PASS current next54 suite denominator burnup maps case 12
PASS current next54 suite denominator burnup maps case 13
PASS current next54 suite denominator burnup maps case 14
PASS current next54 suite denominator burnup maps case 15
PASS current next54 suite denominator burnup maps case 16
PASS current next54 suite denominator burnup maps case 17
PASS current next54 suite denominator burnup maps case 18
PASS current next54 suite denominator burnup maps case 19
PASS current next54 suite denominator burnup maps case 20
PASS current next54 suite denominator burnup maps case 21
PASS current next54 suite denominator burnup maps case 22
PASS current next54 suite denominator burnup maps case 23
PASS current next54 suite denominator burnup maps case 24
PASS current next54 suite denominator burnup maps case 25
PASS current next54 suite denominator burnup maps case 26
PASS current next54 suite denominator burnup maps case 27
PASS current next54 suite denominator burnup maps case 28
PASS current next54 suite denominator burnup maps case 29
PASS current next54 suite denominator burnup maps case 30
PASS current next54 suite denominator burnup maps case 31
PASS current next54 suite denominator burnup maps case 32
PASS current next54 suite denominator burnup maps case 33
PASS current next54 suite denominator burnup maps case 34
PASS current next54 suite denominator burnup maps case 35
PASS current next54 suite denominator burnup maps case 36
PASS current next54 suite denominator burnup maps case 37
PASS current next54 suite denominator burnup maps case 38
PASS current next54 suite denominator burnup maps case 39
PASS current next54 suite denominator burnup maps case 40
PASS current next54 suite denominator burnup maps case 41
PASS current next54 suite denominator burnup maps case 42
PASS current next54 suite denominator burnup maps case 43
PASS current next54 suite denominator burnup maps case 44
PASS current next54 suite denominator burnup maps case 45
PASS current next54 suite denominator burnup maps case 46
PASS current next54 suite denominator burnup maps case 47
PASS current next54 suite denominator burnup maps case 48
PASS current next54 suite denominator burnup maps case 49
PASS current next54 suite denominator burnup maps case 50
PASS current next54 suite denominator burnup maps case 51
PASS current next54 suite denominator burnup maps case 52
PASS current next54 suite denominator burnup maps case 53
PASS current next54 suite denominator burnup maps case 54
PASS current next54 advances mapped denominator units cleanly
PASS current next54 preserves mapped denominator units without release parity
PASS current next54 reports open next denominator gaps
PASS current next54 blocks regressed denominator units
PASS current next54 blocks runner countability without mapped denominator row
PASS current next54 blocks unfocused php output
PASS current next54 rejects missing heads
PASS current next54 rejects empty denominator rows
PASS current next54 reports invalid denominator rows as blockers

1 test files, 457 assertions, 0 failures
```

## Dashboard Delta

- `phpPass`: `19277 -> 19340` (`+63` verified focused PASS lines)
- `phpFail`: remains `0`
- `benchmarkDenominator.mapped`: unchanged; this is denominator burn-up/countability evidence, not a newly mapped static upstream inventory unit.

## Non-Overlap

This avoids accepted current-next48 suite progress mapping, current-next49 upstream gap mapping, current-next34 denominator audit, release admission/countability closure records, and all accepted SQL/JSON/WAL/B-tree/VFS behavior clusters. It only maps supplied denominator rows into current/next unit burn-up with focused PHP admission and explicit release-parity exclusion.

## Dependency Closure

No new support component is needed. The slice reuses lane-local manifest evidence and focused TestRunner admission only.
