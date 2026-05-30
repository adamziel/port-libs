# SQLite planner STAT4 expression partial current-source next242

Status: focused PHP behavior growth for `sqlplanner-stat4-expression-partial-current-source-next242`.

This slice adds a current-source STAT4 histogram fence for partial expression
indexes. After the accepted next239 cardinality-estimate fence, a reused plan
is still rejected when the current `sqlite_stat4` `neq`, `nlt`, or `ndlt`
counters no longer match the current partial expression-index rowset. The fence
validates duplicate expression keys, rows preceding each sample, and distinct
prefix counts before appending a cursor validation opcode.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext242Test.php`
- Result: `1 test files, 72 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next242.php --self-test`
- Result includes: `stat4-expression-partial-current-source-next242-ready`

Application smoke:

`application-sqlplanner-stat4-expression-partial-current-source-next242.php`
models copied `wp_options` plugin rows with duplicate case-varied option names.
The current source has the same partial row estimate as the prepared plan, but
the new fence proves the STAT4 histogram counters are current before reusing
the descending partial expression index for plugin option pagination.

Dependency closure:

No new support component is needed. The slice reuses lane-local STAT4
expression partial current-source rows, partial predicate proof, and cardinality
estimate fencing.

Non-overlap:

This avoids accepted next239 partial cardinality estimates, next238 covering
payload staleness, next235 vector counters, next233 sample-row guards,
expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, UTF,
and suite-runner clusters. The new behavior is current-source STAT4
`neq`/`nlt`/`ndlt` histogram validation for partial expression indexes.
