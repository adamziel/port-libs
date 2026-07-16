# Compound SELECT Window Recursive LIMIT Current Source Next237

## Behavior

Adds a current-source recursive dequeue acknowledgement fence for compound
`WITH RECURSIVE` preview queries where the recursive queue uses `LIMIT/OFFSET`,
feeds `rank()` and `row_number()` window output through `UNION ALL`,
`INTERSECT`, `EXCEPT`, final `ORDER BY`, and final `LIMIT/OFFSET`.

The Application path is copied `wp_options` preview SQL. A next-source option row
can shift the final compound page, so the plan holds next-source exposure until
the current recursive dequeue acknowledgements are sealed.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext237Test.php`
  - `1 test files, 400 assertions, 0 failures`
  - `73` PASS lines
- `php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next237.php`
  - emitted `compound-select-window-recursive-limit-current-source-next237-ready`
  - `requiredAckCount` `6`, `dequeueTokenLength` `64`, `nextExposure` `held-until-current-recursive-dequeue-acks`

## Non-Overlap

Avoids accepted next233 final-order ordinal resume, next229 UNION DISTINCT to
EXCEPT dense_rank, next226 aggregate windows through EXCEPT/INTERSECT, next196
ntile/first_value, JSON table, WAL/VFS, B-tree, encoding, trigger, PRAGMA, and
suite evidence clusters. This slice adds a recursive dequeue acknowledgement
fence over a UNION ALL to INTERSECT to EXCEPT rank/row_number compound page.

## Dependency Closure

No new support component is needed. The slice reuses native PHP SELECT SQL
compound execution, recursive queue LIMIT/OFFSET tracing, rank/row_number
window output, INTERSECT/EXCEPT membership, final LIMIT helpers, and
current-source cursor fencing.
