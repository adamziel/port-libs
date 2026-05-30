Micro-slice: real-upstream-corpus-select-core-dynamic-20260530T175449Z-0
Base accepted HEAD: e35ca6042fb93bdd5d8709bbc17efa06e6d9c2b0

Source truth:
- /home/claude/port-libs/.upstream-cache/libsqlite/test/select3.test

Behavior added:
- Ported additional upstream select3 aggregate/GROUP BY coverage into
  SQLiteRealUpstreamSelectCoreDynamicTest.php.
- Added computed GROUP BY projection cases from select3-2.5 through select3-2.7.
- Added HAVING over grouped alias projection from select3-4.5.
- Added indexed-order grouped min scenarios from select3-6.5 through select3-6.8.
- Added dynamic computed GROUP BY alias shifts and grouped HAVING thresholds to
  exercise the same upstream behavior over varied inputs.

Focused delta:
- Before: php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicTest.php
  => 1 test files, 1533 assertions, 0 failures.
- After: php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicTest.php
  => 1 test files, 1693 assertions, 0 failures.
- PASS-line growth: +12 focused TestRunner PASS cases.
- Assertion growth: +160 focused assertions.
- Mapped denominator growth: none claimed.

Non-overlap:
- This is real upstream select-core behavior from select3.test and does not
  touch WAL, VFS, B-tree, JSON table, source-neutral API cleanup, or runner
  admission metadata.
- It avoids accepted SELECT JOIN text, GROUP BY SQL text, expression ORDER BY,
  scalar WHERE operands, JSON table SELECT sources, and suite ledger surfaces.

Dependency closure:
- No new support component is needed. The existing bounded SQLiteSelectSql
  row-array executor is reused.

Follow-up:
- select3.test cases with multiple aggregate value columns, HAVING aliases over
  aggregate output aliases, and aggregate ORDER BY expressions over arithmetic
  arguments still expose broader executor limitations and were not admitted in
  this bounded corpus slice.
