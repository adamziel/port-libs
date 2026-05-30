# yield-sqlite-planner-stat4-skipscan-covering-current-next48

This slice adds a bounded STAT4-aware covering skip-scan planner wrapper for
current/next `wp_options` maintenance shapes. It composes the existing
multicolumn skip-scan planner with per-prefix STAT4 current/next sample
evidence, then records whether the selected skip-scan is covering or must
defer table lookups for missing payload columns.

Focused behavior:

- chooses the covering `(autoload, blog_id, option_name, option_value)` index
  over a non-covering skip-scan and a legacy no-STAT4 index;
- records STAT4 current/next pairs, per-prefix loop estimates, and bounded
  range estimates for `>=`, `>`, `<=`, `<`, and `BETWEEN` constraints;
- preserves skipped leading-column, current range-column, order satisfaction,
  root-page, and detail-string evidence;
- rejects non-skip-scan predicates and malformed STAT4 sample input.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4SkipScanCoveringCurrentNext48Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 63 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/application-planner-stat4-skipscan-covering-current-next48.php --self-test
application-planner-stat4-skipscan-covering-current-next48 self-test passed
```

Non-overlap: this does not repeat batch37 expression-covering STAT4 estimates,
batch36 skip-scan partial-order/block-sort evidence, current/next31
multicolumn skip-scan admission, or current/next28 partial skip-scan row
materialization. The new behavior is the covering-vs-table-lookup decision and
STAT4 current/next loop evidence for skip-scan plans.

Dependency closure: no new support component is needed; the slice reuses the
existing native PHP `SQLiteCreateIndex`, `SQLiteMultiColumnRangePlan`, and
planner predicate helpers.
