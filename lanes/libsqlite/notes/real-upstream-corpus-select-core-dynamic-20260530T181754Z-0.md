# real-upstream-corpus-select-core-dynamic-20260530T181754Z-0

- Base accepted HEAD: `1be884bec4b3d8944d386430e62bb83a7a09f0ef`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/select9.test`.
- Added PHP test file: `lanes/libsqlite/tests/SQLiteRealUpstreamSelect9CompoundLimitDynamicTest.php`.

## Ported Upstream Behavior

- `select9.test` compound SELECT setup with `t1(a,b,c)` and `t2(d,e,f)`.
- `select9-1.3`: `UNION ALL` over two-column arms with `ORDER BY 1`, plus the upstream `test_compound_select` `LIMIT`/`OFFSET` sweep.
- `select9-1.5`: `UNION ALL` with `ORDER BY 1, 2`, plus the same `LIMIT`/`OFFSET` sweep.
- `select9-1.6`: `UNION ALL` with `ORDER BY 2, 1`, plus the same `LIMIT`/`OFFSET` sweep.
- `select9-1.18`: `EXCEPT` with `ORDER BY 2`, plus a bounded `LIMIT`/`OFFSET` sweep.

## Counts

- New focused TestRunner PASS cases: `1434`.
- New focused behavior assertions: `5716`.
- Expected dashboard classification: PASS-line growth only; mapped denominator remains unchanged because `select9.test` is already part of the hydrated upstream inventory.

## Non-Overlap

- This does not repeat existing select-core dynamic coverage for `select1.test` through `select8.test`, accepted grouped SELECT text, expression `ORDER BY`, JSON table source/cursor/constraint work, or WAL/VFS/B-tree clusters.
- The batch targets `select9.test` compound SELECT `LIMIT`/`OFFSET` behavior, using hardcoded upstream full-result vectors and slicing expected results the same way upstream `test_compound_select` does.

## Red-First Finding

- Initial probing also tried `select9-1.9` (`UNION` distinct with `ORDER BY 2`).
- Current `SQLiteSelectSql` returns the duplicate `"two"` tie rows in the opposite order from upstream when only the second column is an `ORDER BY` term.
- That real ordering gap was excluded from this passing handoff and should be a follow-up behavior fix before admitting `select9-1.9` and adjacent `UNION` distinct tie-order cases.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect9CompoundLimitDynamicTest.php`
  - `1 test files, 5716 assertions, 0 failures`
- Dependency closure: no new support component needed; existing `SQLiteSelectSql` compound SELECT executor is reused.
