# yield-sqlite-jsonb-check-current-next64

Status: focused PHP behavior growth for direct table `CHECK` admission over
current and next JSONB `wp_options` rows.

This slice adds `SQLiteJsonbCheckCurrentNextPlan`, a bounded native-PHP planner
that extracts table `CHECK(...)` constraints and evaluates current rows plus
next INSERT/UPDATE candidates before storage/index maintenance. It covers
`json_valid(...,8)`, `json_type`, `json_extract`/`jsonb_extract`,
`json_array_length`, `IN`, `IS [NOT] NULL`, comparison terms, and AND-composed
range checks over JSONB BLOB payloads.

Focused verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext64Test.php
Focused test run: 1 selected test files (root lock skipped)
44 PASS lines
1 test files, 64 assertions, 0 failures

$ php -l lanes/libsqlite/src/SQLiteJsonbCheckCurrentNextPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteJsonbCheckCurrentNextPlan.php

$ php -l lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext64Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext64Test.php

$ php -l lanes/libsqlite/examples/application-jsonb-check-current-next64.php
No syntax errors detected in lanes/libsqlite/examples/application-jsonb-check-current-next64.php

$ php lanes/libsqlite/examples/application-jsonb-check-current-next64.php
printed changes=2, rejectedChanges=1, accepted rowids 201/203, rejected rowid 202, and the failed rank CHECK.

$ git diff --check -- lanes/libsqlite
clean
```

Dashboard delta: `phpPass` moves from `23341` to `23385` from the 44 verified
focused PASS lines. Mapped upstream coverage is unchanged because this is a
lane-local behavior slice, not a new upstream manifest denominator unit.

Non-overlap: avoids accepted JSONB generated CHECK/index maintenance
current-next54, JSONB generated index UPDATE/DELETE/upsert/cascade slices,
JSON hidden/visible constraints, JSON table source/cursor work, planner STAT4
JSONB covering ORDER work, and accepted WAL/B-tree/VFS clusters. The new
surface is direct table CHECK admission for current and next JSONB row images.

Dependency closure: no new support component is needed. The implementation
reuses existing native PHP JSONB, JSON validity, JSON inspection/extract, and
JSON mutation primitives.
