# real-upstream-corpus-select-core-dynamic-20260530T224325Z-0

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectH.test`

## Ported Scenarios

- `selectH-3.1`: `SELECT count(*) FROM v1 WHERE c60=60` over a four-arm
  `UNION ALL` view/subquery shape.
- `selectH-3.3`: `SELECT count(a) FROM v1 WHERE c60=60` over the same
  four-arm compound source.

The PHP batch expands those two real upstream cases into 500 dynamic
wide-table variants, changing the selected filter column, filter value, and
projected `a` columns while preserving the upstream four-arm `UNION ALL`
shape. This contributes 1001 distinct TestRunner PASS cases and 4005 behavior
assertions in `SQLiteRealUpstreamSelectHUnionCountDynamicTest.php`.

## Non-Overlap

This slice owns the residual `selectH.test` count-over-four-arm-union cluster.
It does not repeat accepted `selectH-1.2` DISTINCT filtering,
`selectH-2.1` ordered omit-unused behavior, `selectH-3.4` projected rows,
`selectH-5.2` distinct-union count coverage, the accepted `select1` through
`selectG` dynamic SELECT batches, SELECTD derived aggregate handling,
expression `ORDER BY`, grouped SELECT text, JSON table source/cursor/constraint
work, or metadata-only runner rows.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectHUnionCountDynamicTest.php`
  - `1 test files, 4005 assertions, 0 failures`
  - `1001` PASS lines

## Dependency Closure

No new support component is needed. The batch reuses the existing
`SQLiteSelectSql` compound SELECT executor and the hydrated upstream SQLite
Tcl corpus as source truth. Mapped coverage remains `1589 / 1589`; this is
PASS-line growth only.
