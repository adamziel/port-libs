# expression-index-partial-collation-stat4-current-source-next114

Adds a bounded current-source planner diagnostic for partial expression indexes
whose first term has an explicit collation and fresh STAT4 samples. The new
helper selects the current source when schema cookie, STAT4 generation, or index
signature changes, proves the partial predicate, applies the index collation to
STAT4 range boundaries, and exposes current/next cursor evidence.

Verification:

- `php -l lanes/libsqlite/src/SQLiteExpressionIndexPartialCollationStat4CurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteExpressionIndexPartialCollationStat4CurrentSourceNext114Test.php`
- `php -l lanes/libsqlite/examples/wordpress-expression-index-partial-collation-stat4-current-source-next114.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteExpressionIndexPartialCollationStat4CurrentSourceNext114Test.php`
- `php lanes/libsqlite/examples/wordpress-expression-index-partial-collation-stat4-current-source-next114.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass` +65 focused PASS lines, mapped coverage
unchanged because this is focused PHP planner behavior over already mapped
expression-index/STAT4/collation inventory.

Dependency closure: no new support component is needed. This reuses native
expression-index parsing, partial-index predicate proof, STAT4 fixture
diagnostics, and built-in collation comparisons.

Non-overlap: avoids accepted expression-index range-cost ranking, expression
covering ORDER current-source materialization, STAT4 JSON/order-covering
current-source slices, partial-index proof/order slices, SQL expression
`ORDER BY`, JSON table/source/constraint work, VFS/WAL/B-tree clusters, and
encoding Unicode GLOB work. The new surface is partial expression-index STAT4
current/next boundaries under the expression term's collation after
current-source reprepare.
