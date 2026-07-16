# real-upstream-corpus-window-functions-dynamic-20260530T194816Z-0

- Base accepted HEAD: `4fa72fa71b26a19fe54f9ce85268cd96396282ab`
- Upstream source truth:
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/window8.test`

## Ported Upstream Scenarios

- `window8.test` `1.2.*`: `GROUPS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW`.
- `window8.test` `1.3.*`: `GROUPS BETWEEN UNBOUNDED PRECEDING AND 1 FOLLOWING`.
- `window8.test` `1.4.*`: `GROUPS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING`.
- `window8.test` `1.5.*`: inverted `GROUPS BETWEEN 1 PRECEDING AND 2 PRECEDING` empty-frame behavior.
- `window8.test` `1.6.*`: `GROUPS BETWEEN 1 PRECEDING AND CURRENT ROW`.

## Focused Evidence

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamWindow8GroupsExtendedDynamicTest.php`.
- Focused command:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow8GroupsExtendedDynamicTest.php`
- Result: `1 test files, 8401 assertions, 0 failures`.
- Focused PASS cases added: `2801` distinct TestRunner PASS lines.

## Non-Overlap

This extends the accepted `window8.test` coverage beyond the prior `1.1` and
`1.9` GROUPS slices and the existing `window7/window8` range/group subset. It
does not add metadata-only rows, fake upstream IDs, domain-shaped APIs, or
duplicate JSON/WAL/B-tree/VFS/source-neutral work.

## Dependency Closure

No new support component is needed. The test reuses lane-local
`SQLiteWindowFunction` GROUPS frame machinery and validates it against an
independent PHP oracle over the real upstream `t3(a,b,c)` rows.
