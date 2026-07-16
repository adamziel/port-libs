# real-upstream-corpus-select-core-dynamic-20260531T033621Z-0

Session: `port-dev-sqlite-yield-dyn-real-select-20260531T033621Z`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select2.test`
- Upstream scenarios: `e_select-2.2.1` subquery table-or-subquery handling,
  especially `SELECT count(*) AS y FROM t4`, `SELECT x AS y FROM t4`, and
  `USING` joins against a peer table.

## Behavior

Added `SQLiteRealUpstreamESelect2SubqueryUsingAffinityDynamicTest.php` with
1,250 dynamic cases over the upstream subquery/USING shape. Each case verifies:

- count-subquery `USING` joins;
- text numeric subquery result `USING` joins when the peer value is numeric;
- both source orders for the subquery/table join;
- a nonmatching text subquery result that must not join.

The shared `SQLiteSelectSql` JOIN `USING` comparison now accepts numeric string
and numeric scalar equality while preserving BLOB and exact nonnumeric scalar
comparison behavior.

## Non-Overlap

This slice does not repeat accepted SELECT core projection, JOIN text dispatch,
parenthesized selectD joins, grouped SELECT text, subquery predicate behavior,
expression ORDER BY, JSON table source/cursor/constraint work, VFS/WAL/B-tree
clusters, or metadata-only runner rows. It owns the e_select2 subquery
table-or-subquery plus `USING` affinity comparison gap.

Mapped denominator coverage remains unchanged because `e_select2.test` is
already present in the hydrated upstream manifest/runner map. Countable focused
growth is 1,251 TestRunner PASS cases and 18,755 assertions in the new PHP
test file.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamESelect2SubqueryUsingAffinityDynamicTest.php`
  - `1 test files, 18755 assertions, 0 failures`

## Dependency Closure

No new support component is needed. The slice reuses `SQLiteSelectSql` subquery
FROM handling, JOIN `USING` planning, scalar expression evaluation, and
existing PHP test harness support.
