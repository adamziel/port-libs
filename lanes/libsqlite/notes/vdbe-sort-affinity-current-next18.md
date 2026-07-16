# VDBE Sort Affinity Current/Next18

This slice extends the existing VDBE sorter cursor with current-row accessors
and a current-then-next yield primitive. The comparator and sorter ordering
logic are unchanged: current rows are yielded after the accepted affinity,
collation, NULL-placement, descending, and stable tie-break ordering has already
been applied.

Focused verification:

```text
php -l lanes/libsqlite/src/SQLiteVdbeSorterCursor.php
No syntax errors detected in lanes/libsqlite/src/SQLiteVdbeSorterCursor.php

php -l lanes/libsqlite/tests/SQLiteVdbeSortAffinityCurrentNext18Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteVdbeSortAffinityCurrentNext18Test.php

php -l lanes/libsqlite/examples/application-vdbe-sort-affinity-current-next.php
No syntax errors detected in lanes/libsqlite/examples/application-vdbe-sort-affinity-current-next.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeSortAffinityCurrentNext18Test.php
Focused test run: 1 selected test files (root lock skipped)
30 PASS lines
1 test files, 45 assertions, 0 failures

php lanes/libsqlite/examples/application-vdbe-sort-affinity-current-next.php
sortedOptionIds: [104, 103, 102, 106, 101, 105]
cursorPosition: 6
eof: true
```

Dashboard delta: `phpPass` increases by the verified focused PASS-line delta,
from 6121 to 6151. `benchmarkDenominator.mapped` is unchanged because this is a
focused PHP behavior slice, not a newly admitted upstream inventory unit.

Non-overlap: this does not repeat accepted SQL expression `ORDER BY`, grouped
SELECT text, SELECT/JOIN text dispatch, JSON table source/cursor/constraint
clusters, Unicode GLOB ranges, VFS/WAL/B-tree storage clusters, or the prior
VDBE compare/null-collation sorter cases. It only adds the bounded cursor
current/next surface for already-sorted VDBE-style row scans.

Dependency closure: no new support component is needed. The slice reuses the
lane-local VDBE sorter comparator, SQLite affinity comparison, BLOB value, and
TestRunner support.
