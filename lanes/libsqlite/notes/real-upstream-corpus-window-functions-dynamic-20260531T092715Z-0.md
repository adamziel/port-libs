# real-upstream-corpus-window-functions-dynamic-20260531T092715Z-0

## Scope

- Lane: libsqlite.
- Base accepted HEAD: `505e973c7fba58525b7fffcb767bf99390508892`.
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/window6.test`
    sections `9.5`, `9.6`, `9.7.1`, `9.7.3`, `9.7.4`, `9.8.1`, and
    `9.8.2`.
  - Positive non-overlap guards from upstream same-class empty-frame behavior
    in `window2.test`, `window4.test`, `window1.test`, `windowB.test`, and
    `window8.test`.

## Behavior

- `SQLiteWindowFunction` now rejects impossible window frame boundary forms
  before frame evaluation:
  - a starting boundary of `UNBOUNDED FOLLOWING`;
  - an ending boundary of `UNBOUNDED PRECEDING`;
  - a boundary class that ends before it starts, such as `CURRENT ROW` to
    `4 PRECEDING` or `4 FOLLOWING` to `2 PRECEDING`.
- The change deliberately preserves valid same-class empty frames such as
  `1 PRECEDING` to `2 PRECEDING` and `1 FOLLOWING` to `0 FOLLOWING`, which are
  covered by existing upstream window cases.

## Evidence

- Red-first probe before the patch:
  - `SQLiteWindowFunction::aggregateFrameBetweenValues("count", [1,2,3], [1,2,3], "ROWS", "CURRENT ROW", "1 PRECEDING")`
    returned `[0, 0, 0]` instead of rejecting the invalid frame specification.
- New focused corpus:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow6FrameSyntaxDynamic20260531Test.php`
  - Result: `1 test files, 1145 assertions, 0 failures`.
- Related window regression set:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow6FrameSyntaxDynamic20260531Test.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowErrDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow6DynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow8DynamicGroupsCorpusTest.php lanes/libsqlite/tests/SQLiteWindowDynamicUpstreamCorpusTest.php lanes/libsqlite/tests/SQLiteWindowDynamicRealCorpusNextTest.php`
  - Result: `6 test files, 50566 assertions, 0 failures`.

## Status Delta

- New focused TestRunner PASS/assertion cases: `+1145`.
- `lane-status.json` `phpPass`: `2838123 -> 2839268`.
- Mapped upstream denominator remains `1589 / 1589`; this is PASS-line corpus
  growth over already mapped upstream window files.

## Dependency Closure

- No new support component is needed. The patch reuses the existing native PHP
  `SQLiteWindowFunction` helper and existing `TestRunner` harness.

## Non-Overlap

- This does not repeat accepted window aggregation, JSON object windows,
  GROUPS/RANGE execution, windowB/windowC/windowE data cases, or parser-level
  SELECT-source work. The slice is specifically the missing helper-level
  frame-boundary syntax rejection from upstream `window6.test`.
