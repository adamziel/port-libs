# SQLite Index Partial Covering Corpus Next

Session `port-dev-sqlite-yield-idxcover4` adds a focused upstream-style PHP
corpus for partial expression-index planning when the chosen index also covers
the requested columns.

Scope:

- partial `IS NOT NULL` implication for `lower()`, `upper()`, `length()`, and
  `CAST(... AS INTEGER)` expression indexes;
- covering-column cost preference for copied `wp_options` lookup shapes;
- ORDER BY compatibility on ascending and descending expression indexes;
- NULL and type guards that keep partial indexes out of unsafe scans;
- deterministic plan ranking by cost, estimated rows, and index name.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteIndexPartialCoveringCorpusTest.php`
  reported `1 test files, 47 assertions, 0 failures`.
- The focused file contributes 31 independent TestRunner PASS cases, so
  `lane-status.json` `phpPass` moves from `1336` to `1367`.
- `php lanes/libsqlite/examples/application-index-partial-covering-corpus.php`
  selected `idx_wp_options_name_covering` for the default copied
  `wp_options` lookup smoke.
- `php -l` passed for the new test and example files.
- `git diff --check -- lanes/libsqlite` passed.

Non-overlap:

- This does not repeat accepted expression-index range-cost ranking, SQL
  expression ORDER BY execution, JSON table constraints, VFS, WAL, or B-tree
  storage clusters. It adds a disjoint partial-covering planner corpus on top
  of the existing expression-index planner surface.

Dependency closure:

- No new support component is needed; the slice reuses the existing native PHP
  `SQLiteSelectExpressionIndexPlan` and `SQLiteCreateIndex` parser helpers.
