# real-upstream-corpus-select-core-dynamic-20260531T032210Z-0

Added `SQLiteRealUpstreamSelect3AggregateOrderDynamicTest.php` and fixed grouped SELECT expression `ORDER BY` alias resolution in `SQLiteSelectSql`.

Real upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select3.test`
- `select3-2.8`: `SELECT log*2+1 AS x, count(*) AS y ... GROUP BY x ORDER BY 10-(x+y)`.

Focused behavior:

- Parser-level grouped SELECT now rewrites unqualified result aliases inside aggregate/grouped expression `ORDER BY` terms back to their source expressions before aggregate-summary rewrite.
- The dynamic corpus varies source row ranges and group cardinalities across 1000 seeds, recomputes expected grouped counts and alias-derived ordering from the PHP fixture rows, and checks flat result, value count, edge values, and fingerprint for each seed.

Verification:

- Red before fix: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect3AggregateOrderDynamicTest.php` failed with `SQLite SELECT expression row is missing column x`.
- Green after fix: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect3AggregateOrderDynamicTest.php` -> `1 test files, 6003 assertions, 0 failures`.

Non-overlap:

- This owns upstream `select3-2.8` grouped result-alias expression ordering. It does not repeat accepted SELECT SQL expression `ORDER BY`, grouped SELECT text, SELECT subquery/JOIN/LIMIT clusters, selectC alias WHERE/HAVING coverage, selectB derived compound coverage, JSON table source/cursor/constraint work, WAL/VFS/B-tree behavior, or metadata-only runner rows.
- Mapped denominator remains unchanged because `select3.test` is already present in the hydrated upstream corpus manifest.

Expected movement:

- `+1001` focused TestRunner PASS cases if admitted as a new selected test file.
- No new support component is needed; the slice reuses existing native PHP SELECT SQL, grouped aggregate summaries, expression evaluation, and result ordering machinery.

Follow-up:

- Upstream `select3-5.1` and `select3-5.2` still expose a broader executor limitation for multiple aggregate value expressions in one grouped SELECT. This patch intentionally leaves that larger behavior as a follow-up instead of weakening or faking those cases.
