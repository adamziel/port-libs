# Covering index current next20

This slice adds a bounded native PHP `SQLiteCoveringIndexPlan` for ordinary
multi-column indexes. It ranks current-source `CREATE INDEX` definitions by
usable equality/range prefix, covering projection columns, partial-index
predicate proof, ORDER BY compatibility after equality prefixes, estimated
rows, and deterministic index name ordering.

Focused behavior:

- ordinary `wp_options(option_name, autoload, option_value)` covering lookup
  beats a narrow `option_name` index when projection can avoid table b-tree
  fetches;
- partial `WHERE autoload='yes'` indexes are admitted only when query
  constraints prove the predicate, including reversed equality operands;
- `IS NOT NULL`, `IN`, `BETWEEN`, and reversed range constraints participate in
  bounded prefix planning without accepting all-NULL probes;
- ORDER BY is satisfied only by the remaining index suffix after equality
  prefix columns and with matching direction;
- unsupported expression indexes and unsupported predicates are ignored rather
  than misclassified as covering ordinary indexes.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCoveringIndexCurrentNext20Test.php
Focused test run: 1 selected test files (root lock skipped)
31 PASS lines
1 test files, 40 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/application-covering-index-current-next20.php
```

Dependency closure: no new support component is needed. The slice reuses the
existing native `SQLiteCreateIndex` parser and `SQLiteIndexPredicate` partial
predicate implication helpers.

Non-overlap: this avoids accepted expression-index range-cost ranking, SQL
expression ORDER BY, SELECT SQL subquery/grouped dispatch, JSON table
constraint/source/cursor work, VFS/WAL apply slices, B-tree page relocation,
index-interior merge, overflow freelist release, and Unicode GLOB work. The new
behavior is ordinary multi-column covering-index ranking for current-source
query planner decisions.
