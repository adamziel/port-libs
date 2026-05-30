# SQLite planner STAT4 partial covering order current/next38

This slice adds bounded STAT4-style sample costing to
`SQLiteCoveringIndexPlan` after ordinary partial-index proof and current/next
prefix selection. Partial covering indexes can now carry `stat4Samples` with
sample column values and row counts; matching samples replace the coarse row
estimate only when every usable current/next prefix constraint matches the
sample.

Focused behavior:

- Preserves existing partial-index implication before consulting STAT4 samples.
- Applies STAT4 row estimates to covering partial indexes with equality prefix
  plus current range scans.
- Keeps `estimatedRowsBeforeStat4`, `stat4Used`, and
  `stat4MatchedSamples` evidence in the selected/ranked plan.
- Requires all constraints on a sampled column to match, so bounded
  lower/upper ranges narrow current/next estimates correctly.
- Keeps ORDER BY satisfaction based on the post-equality index suffix.
- Validates malformed STAT4 sample lists, row counts, column maps, missing
  sample columns, and invalid sample literal values.

Application smoke:

- `examples/application-planner-stat4-partial-covering-order-current-next38.php`
  reports a copied `wp_options` autoload/plugin `option_name` scan choosing a
  partial covering index with `ORDER BY option_name, option_id DESC`, where
  STAT4 samples reduce the fallback estimate from 240 rows to 24 rows.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteCoveringIndexPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteCoveringIndexPlan.php

php -l lanes/libsqlite/tests/SQLitePlannerStat4PartialCoveringOrderCurrentNext38Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLitePlannerStat4PartialCoveringOrderCurrentNext38Test.php

php -l lanes/libsqlite/examples/application-planner-stat4-partial-covering-order-current-next38.php
No syntax errors detected in lanes/libsqlite/examples/application-planner-stat4-partial-covering-order-current-next38.php

php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4PartialCoveringOrderCurrentNext38Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 53 assertions, 0 failures
```

Non-overlap:

This avoids accepted partial-index WHERE proof, partial skip-scan,
multicolumn skip-scan, expression-index range cost, expression ORDER BY,
JSON/VFS/WAL/B-tree clusters, and batch31 multicolumn skip-scan planning. The
new surface is STAT4 sample selectivity for already-proved partial covering
current/next order plans.

Dependency closure:

No new support component is needed. This reuses the existing native PHP
covering-index planner, CREATE INDEX parser, partial predicate proof, and SQL
value comparison rules.
