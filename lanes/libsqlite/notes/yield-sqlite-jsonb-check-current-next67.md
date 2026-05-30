# yield-sqlite-jsonb-check-current-next67

Status: focused PHP behavior growth for logical JSONB table `CHECK` admission
over current and next copied `wp_options` rows.

This slice extends `SQLiteJsonbCheckCurrentNextPlan` beyond accepted next64
AND-only terms by evaluating nested logical `OR`, `NOT`, and grouped `AND`
terms. It covers Application plugin-setting imports where JSONB rows must satisfy
channel allow-lists, not-deprecated guards, optional version requirements, and
beta/rank exclusion rules before INSERT/UPDATE candidates are admitted.

Focused verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext67Test.php
Focused test run: 1 selected test files (root lock skipped)
42 PASS lines
1 test files, 71 assertions, 0 failures

$ php -l lanes/libsqlite/src/SQLiteJsonbCheckCurrentNextPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteJsonbCheckCurrentNextPlan.php

$ php -l lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext67Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext67Test.php

$ php -l lanes/libsqlite/examples/application-jsonb-check-current-next67.php
No syntax errors detected in lanes/libsqlite/examples/application-jsonb-check-current-next67.php

$ php lanes/libsqlite/examples/application-jsonb-check-current-next67.php
printed changes=2, rejectedChanges=1, accepted rowids 301/303, rejected rowid 302, and the failed beta/rank CHECK.

$ git diff --check -- lanes/libsqlite
clean
```

Dashboard delta: `phpPass` moves from `25055` to `25097` from the 42 verified
focused PASS lines. Mapped upstream coverage is unchanged because this is a
lane-local behavior slice, not a new upstream manifest denominator unit.

Non-overlap: avoids accepted JSONB direct table CHECK next64 simple AND terms,
JSONB generated CHECK/index maintenance, JSON hidden/visible constraints, JSON
table source/cursor work, planner STAT4 JSONB covering ORDER work, and accepted
WAL/B-tree/VFS clusters. The new surface is logical CHECK admission for current
and next JSONB row images.

Dependency closure: no new support component is needed. The implementation
reuses existing native PHP JSONB, JSON validity, JSON extract, and JSON mutation
primitives.
