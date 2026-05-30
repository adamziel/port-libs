# yield-sqlite-planner-stat4-range-order-current-source-next97

This slice adds a bounded STAT4 range-scan planner for a leading indexed
column with `ORDER BY` satisfied directly by the same current source. It
compares prepared and current source metadata, invalidates stale plans on
schema-cookie, STAT4-generation, or range-bound changes, and records
current/next STAT4 samples for the selected range.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4RangeOrderCurrentSourceNext97Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
PASS ... 54 focused planner STAT4 range ORDER current-source cases

1 test files, 54 assertions, 0 failures
```

Application smoke:

```sh
php lanes/libsqlite/examples/application-planner-stat4-range-order-current-source-next97.php --self-test
```

Result:

```text
application-planner-stat4-range-order-current-source-next97 self-test passed
```

Expected dashboard movement: `phpPass` increases by the verified focused
PASS-line delta, `36750 -> 36804` (+54). No mapped-denominator change is
claimed.

Dependency closure: no new support component is needed. This reuses lane-local
PHP planner metadata and does not require ext/sqlite or a new native support
library.

Non-overlap: this is a leading-column STAT4 range/ORDER current-source planner.
It does not repeat accepted skip-scan STAT4 current-source work, expression
ORDER BY, expression-index range-cost ranking, JSON table source/cursor work,
B-tree overflow/root-collapse/page-move work, or accepted VFS/WAL application
clusters.
