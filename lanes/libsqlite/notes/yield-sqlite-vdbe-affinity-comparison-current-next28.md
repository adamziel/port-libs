# yield-sqlite-vdbe-affinity-comparison-current-next28

## Status

Adds cursor-level VDBE current/next record comparison for adjacent row boundary
detection. The new `SQLiteVdbeSorterCursor::nextRecord()` and
`compareCurrentToNext()` methods reuse the existing affinity, collation,
descending, and explicit NULL-placement comparison rules without advancing the
cursor.

## Focused Evidence

Command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeAffinityComparisonCurrentNext28Test.php
```

Output:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 211 assertions, 0 failures
```

The focused file reports 56 PASS lines, moving `phpPass` from 9342 to 9398 for
this isolated lane patch.

## Application Smoke

`lanes/libsqlite/examples/application-vdbe-affinity-current-next.php` reports
copied `wp_options` adjacent boundary decisions over `autoload` and `priority`
keys using NOCASE text affinity, numeric affinity, and explicit NULL placement.

## Non-Overlap

This slice avoids accepted SQL expression ORDER BY, SELECT SQL text dispatch,
GROUP BY/HAVING, predicate execution, Unicode GLOB, JSON table source/cursor
and hidden/visible constraints, VFS writer/locking/sync/rollback work, WAL
byte truncation/checkpoint transaction work, and B-tree page move/root
collapse/overflow freelist clusters. It covers only cursor current/next
affinity comparison for VDBE-style adjacent boundary checks.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP
`SQLiteAffinityComparison` and `SQLiteVdbeSortCompare` primitives.
