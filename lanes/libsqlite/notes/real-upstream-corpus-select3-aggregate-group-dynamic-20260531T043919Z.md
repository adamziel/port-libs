# real-upstream-corpus-select-core-dynamic-20260531T043919Z-0

Base accepted HEAD: `0b81729d69877023d4b2607c8a1ffc5fac25bee0`

Added a focused real-upstream SELECT batch from hydrated SQLite source:

- Upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/select3.test`
- Upstream sections cited: `select3-1.2`, `select3-2.6`, `select3-4.4`, `select3-5.1`
- Ported behavior in this patch: `select3-2.1` grouped `count(*)` ordered by grouping key and `select3-2.7` `GROUP BY` expression alias ordered by aggregate count.
- Added focused PHP cases: 1 source-citation case plus 1,000 dynamic behavior cases over generic `application_rows` data.

Focused verification:

- Initial broader attempt exposed current executor blockers: multi-aggregate SELECT-list projection fails with `SQLite SELECT SQL GROUP BY supports one aggregate value column`, and HAVING on output alias fails with `SQLite SELECT predicate row is missing column y`.
- Final focused run: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect3AggregateGroupDynamicTest.php`
- Result: `1 test files, 4006 assertions, 0 failures` and 1,001 PASS lines.

Non-overlap:

- Avoids accepted parser-level SELECT JOIN, grouped SELECT SQL text, expression `ORDER BY`, scalar subquery, JSON table source/cursor/hidden/visible constraint, B-tree, WAL, and VFS clusters.
- Uses generic application table/column names only; no new domain-specific API or fixture surface.

Dependency closure:

- No new support component is needed. The existing `SQLiteSelectSql` row-array executor is reused.
- Follow-up blocker for a larger future batch: implement grouped SELECT support for multiple aggregate value expressions and HAVING predicates that reference aggregate output aliases, then admit the remaining `select3.test` aggregate/HAVING/order-expression sections.
