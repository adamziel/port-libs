# sqlplanner-stat4-skipscan-covering-current-source-next120

This slice adds a bounded native PHP planner edge for SQLite STAT4 skip-scan
covering behavior:

- reuses the accepted STAT4 skip-scan current/next range evidence;
- proves a multi-column covering index can return copied `wp_options` payload
  columns directly from the index for suffix-range skip scans;
- emits current/next covering-row evidence and a cursor program that reads
  `Column` values from the index without `DeferredSeek`;
- rejects the covering fast path when a requested payload column is absent, and
  keeps a deferred-table-seek diagnostic for that fallback.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4SkipScanCoveringCurrentSourceNext120Test.php
1 test files, 57 assertions, 0 failures

php lanes/libsqlite/examples/application-planner-stat4-skipscan-covering-current-source-next120.php --self-test
application-planner-stat4-skipscan-covering-current-source-next120 self-test passed
```

Dependency closure: no new support component is needed. This composes existing
lane-local STAT4 skip-scan and partial-index predicate helpers.

Non-overlap: this avoids accepted STAT4 partial skip-scan ORDER evidence,
accepted STAT4 expression covering/range-cost evidence, accepted expression
ORDER BY, JSON table source/constraint/cursor work, WAL/VFS transaction
application, B-tree page-move/freelist work, and encoding LIKE/GLOB slices.
