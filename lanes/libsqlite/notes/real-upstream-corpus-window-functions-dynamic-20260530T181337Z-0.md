# Real Upstream Window GROUPS/RANGE Dynamic Corpus

Base accepted HEAD: `a9928e604a7d849ecf8aa28f83049e71a24f4b05`

Micro-slice: `real-upstream-corpus-window-functions-dynamic-20260530T181337Z-0`

Added `SQLiteRealUpstreamWindowGroupsRangeDynamicCorpusTest.php`, a focused
real upstream corpus batch covering:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window7.test`
  scenarios `1.2`, `1.3`, `1.4`, `1.5`, `1.6`, `1.7`, and `1.8.1`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window8.test`
  scenario families `1.1` and `1.2` for `EXCLUDE CURRENT ROW`, `EXCLUDE GROUP`,
  and `EXCLUDE TIES`.

The test ports the upstream `t3(a,b)` peer-group corpus into generic
application-shaped PHP rows and verifies `SQLiteWindowFunction` `GROUPS` and
`RANGE` frame sums with independent PHP oracles. It intentionally avoids the
already accepted window1/window2/window3/window4 dynamic row-frame coverage.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowGroupsRangeDynamicCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 13001 assertions, 0 failures
```

Expected dashboard movement: `+13001` focused PASS lines/assertions if accepted.
Mapped coverage remains `1189 / 1589`; this is PASS-line growth, not mapped
denominator growth.

Dependency closure: no new support component is needed; this reuses the
existing native `SQLiteWindowFunction` frame evaluator.
