# libsqlite real upstream corpus date affinity dynamic modifier batch

## Scope

- Adds `SQLiteRealUpstreamDateAffinityDynamicModifierBatchTest.php`.
- Ports real upstream `date.test` Julian-day arithmetic modifier behavior from
  `date-13.11..13.20` into focused PHP TestRunner coverage.
- Owns a non-overlapping modifier batch separate from existing date/affinity
  coverage for `date-2.2c`, date3 auto-boundaries, deterministic date2 guards,
  floor/ceiling, and expression-affinity corpus rows.

## Count

- Focused PASS cases: `1026`.
- Focused assertions: `5126`.
- Expected `phpPass` movement: `355604 -> 356630`.
- Mapped coverage: unchanged at `1472 / 1589`.

## Verification

```text
$ php -l lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicModifierBatchTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicModifierBatchTest.php

$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicModifierBatchTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 5126 assertions, 0 failures
```

## Dependency Closure

No new support component is needed. The batch reuses
`SQLiteCoreScalarFunction::sqlFunctionArguments()` date/time handling and the
existing TestRunner harness.
