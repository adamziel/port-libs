# real-upstream-corpus-window-functions-dynamic-20260531T004426Z-0

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window7.test`
- Ported sections: `1.0` t3 cyclic peer fixture, `1.2` through `1.8.2`
  GROUPS/RANGE peer-frame `sum(b)` behavior.

## Handoff

- Added `SQLiteRealUpstreamWindow7GroupsRangeDynamicTest.php`.
- The batch covers 10 GROUPS/RANGE frame variants over 100 upstream fixture
  rows for 1,000 row-level behavior cases, plus one source-citation case.
- Focused movement: `+1001` distinct TestRunner PASS lines,
  `4001` behavior assertions.
- Non-overlap: existing accepted/current window corpus already covers
  window1/2/5/6/8/9/A/B/C and selected fault/pushdown cases. This handoff
  owns `window7.test` peer-group GROUPS/RANGE sums only.

## Dependency closure

No new support component is needed. The batch reuses existing native
`SQLiteWindowFunction::aggregateFrameBetweenRows()` GROUPS/RANGE support and
adds upstream corpus coverage around it.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow7GroupsRangeDynamicTest.php`
  - `1 test files, 4001 assertions, 0 failures`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow7GroupsRangeDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamWindow7GroupsRangeDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed with no output
