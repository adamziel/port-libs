## real-upstream-corpus-pragma-schema-dynamic-application-id-20260531T032637Z

- Base accepted HEAD: `9f3a6190507c2ea8ee290883ee3ce143ab18c8c9`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`.
- Ported sections: `pragma-8.3.1` default `PRAGMA application_id` readback and `pragma-8.3.2` parenthesized `PRAGMA Application_ID(12345)` assignment/readback, expanded through the existing generic runtime PRAGMA state model for attached-schema isolation and transaction rollback/commit preservation consistent with adjacent `pragma-8.1`/`pragma-8.2` runtime-version behavior.
- Added focused PHP test file: `lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicApplicationIdTest.php`.
- Focused PASS cases: `2501` total in the changed file; net growth is `+1500` PASS cases over the accepted file's prior `1001` cases.
- Focused assertions: `11004`.
- Non-overlap: this does not repeat accepted `pragma3` data_version, `pragma-8.1/8.2` schema_version/user_version, `pragma4` result-column arity, generated-column `table_xinfo`, `pragma6` generated-schema integrity, or source-neutral cleanup work.
- Dependency closure: no new support component needed; this reuses `SQLitePragmaRuntimeState` and the existing lane test harness.

Verification:

```text
php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicApplicationIdTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicApplicationIdTest.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicApplicationIdTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 11004 assertions, 0 failures
```
