# sqlplanner-expression-partial-covering-current-source-next148

This slice adds bounded current-source planner behavior for non-skip-scan
expression partial covering indexes. A prepared `wp_options` import lookup over
`lower(option_name)` is reparsed when schema/STAT4/index metadata changes, the
current partial predicate is proven from query terms, and the covering cursor
returns payload columns without a deferred table seek.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteExpressionPartialCoveringCurrentSourceNext148Test.php`
- `php lanes/libsqlite/examples/application-expression-partial-covering-current-source-next148.php --self-test`

Dependency closure: no new support component is needed. The patch reuses
lane-local expression metadata, partial predicate implication, current-source
fences, and covering cursor diagnostics.

Non-overlap: avoids accepted expression partial skip-scan next141, partial
range covering next131/next136, STAT4 partial covering next142, expression
ORDER BY, expression-index range-cost ranking, JSON table, VFS/WAL, and B-tree
clusters. The new surface is non-skip-scan expression partial covering
current-source selection and table-seek elision.
