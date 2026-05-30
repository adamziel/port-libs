# real-upstream-corpus-btree-index-dynamic-20260530T192857Z-0

Base accepted HEAD: `de394d1a2a5407b1856e89f4b996c5ea3450f50d`.

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/indexexpr1.test`
  - `indexexpr1-110`, `120`, `130`, `141`, `150`, `160`, `170`, `171`
  - `indexexpr1-210`, `220`, `230`, `241`, `250`, `260`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/indexexpr2.test`
  - `indexexpr2-3.4.5`, `3.4.6`
  - `indexexpr2-4.110`, `4.120`, `4.130`

## Handoff Delta

- Added `SQLiteBTreeIndexDynamicCorpusPlan::indexExpressionDynamicCases()`.
- Added `SQLiteRealUpstreamBtreeIndexExprDynamicTest.php`.
- Focused PASS-line growth: `+1202` distinct TestRunner PASS cases.
- Focused behavior assertions: `20274`.
- Expected `lane-status.json` movement: `phpPass` `389246 -> 390448`; mapped coverage unchanged.

## Non-Overlap

This slice avoids the currently accepted B-tree/index dynamic surfaces for
`index2`, `index3`, `index4`, `index5`, `index6`, `index7`, `index8`,
`index9`, `indexA`, `indexedby`, `btree01`, and `btree02`. It ports a separate
real upstream expression-index behavior cluster: expression indexes over
`substr()` and `length()`, rowid and WITHOUT ROWID parity, collation-sensitive
ORDER BY use, and UPDATE recomputation rules when changed columns do or do not
feed the indexed expression.

## Verification

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexExprDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexExprDynamicTest.php`
  - `1 test files, 20274 assertions, 0 failures`

## Dependency Closure

No new support component is needed. The batch reuses existing lane-local
expression/index planning data structures and `TestRunner`; no external runner,
network service, or shell-out oracle is required for the PHP assertions.
