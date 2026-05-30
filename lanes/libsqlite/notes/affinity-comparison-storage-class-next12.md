# Affinity Comparison Storage-Class Next12

## Scope

Adds a bounded native comparison-affinity primitive for SQLite storage-class
ordering before predicate/executor wiring. The covered behavior is SQLite's
pre-comparison affinity application: numeric affinity converts well-formed
text/BLOB numeric operands, text affinity converts numeric operands only when
the opposite side has no affinity, failed conversions preserve the original
storage class, and final comparison orders NULL, numeric, text, and BLOB
classes with text collations applied only to text values.

## Verification

Focused test output:

```text
Focused test run: 1 selected test files (root lock skipped)
...
PASS affinity comparison rejects unsupported affinity

1 test files, 123 assertions, 0 failures
```

PASS-line delta: +61 (`phpPass` 3796 -> 3857).

Additional verification:

- `php lanes/libsqlite/examples/application-affinity-comparison-storage-class.php --self-test` passed.
- `php -l lanes/libsqlite/src/SQLiteAffinityComparison.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteAffinityComparisonStorageClassCorpusTest.php` passed.
- `php -l lanes/libsqlite/examples/application-affinity-comparison-storage-class.php` passed.
- `git diff --check -- lanes/libsqlite` passed.

## Non-overlap

This does not repeat CAST expression execution, `IS DISTINCT FROM`, Unicode
GLOB ranges, expression ORDER BY, range-cost planning, JSON table constraints,
B-tree overflow/page-move/root-collapse, or VFS/WAL apply clusters. It isolates
the lower-level storage-class comparison rule needed by later predicate and
planner wiring.

## Dependency Closure

No new support component is needed. The slice reuses the existing PHP
autoload/test harness and `SQLiteBlobValue`.
