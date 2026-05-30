# real-upstream-corpus-window-functions-dynamic-20260530T210532Z-0

- Base accepted HEAD: `6b3b48d963616c004933a32f66ee47ce4ec74885`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test`.
- Ported cluster: `windowE.test` section `3.1` dynamic `RANGE ... PRECEDING` max frames over the real `t2(c1,c2)` row set, plus exact numeric frame checks from sections `4.1`, `4.2`, `5.1`, and `5.2`.
- Non-overlap: avoids the accepted `window4`, `window8`, `windowerr`, `window2`, `window3`, `window7`, `window9`, `windowA` through `windowD`, grouped SELECT, JSON table, pager/WAL, B-tree, VFS, and PDO side API surfaces already recorded in current status.
- Countability: adds `1040` focused TestRunner PASS lines in one new real upstream corpus file. The `windowE.test` section `3.1` matrix owns `15` dynamic RANGE offsets over `69` upstream rows (`1035` row-level PASS cases), plus five exact source/citation numeric-frame PASS cases.
- Behavior assertions: `1121` assertions from focused verification.
- Dependency closure: no new support component is needed; the existing native `SQLiteWindowFunction` frame evaluator is reused.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowEDynamicRangeCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 1121 assertions, 0 failures
```

Additional required gates:

```text
php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindowEDynamicRangeCorpusTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamWindowEDynamicRangeCorpusTest.php

git diff --check -- lanes/libsqlite
passed with no output

php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php
included in combined focused run: 2 test files / 1124 assertions / 0 failures
```
