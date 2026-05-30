# real-upstream-corpus-select-core-dynamic-20260530T210143Z-0

Status: focused real-upstream SELECT core dynamic corpus growth.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectH.test`
- `selectH-1.2` / `selectH-1.3`: unused `counter(1)` projection columns in compound derived tables are omitted when the outer query only references ordinary projected columns and filter columns.

Implementation:

- `SQLiteSelectSql` now computes a bounded required-column hint from the outer SELECT list, WHERE, GROUP BY, HAVING, and ORDER BY text before materializing the FROM source.
- Derived compound SELECT tables use that hint to prune unused `counter()` projection terms before projection evaluation. This fixes the prior blocker where an unused upstream side-effect function was eagerly evaluated and failed before assertions.
- The change is intentionally narrow: ordinary projections are preserved, wildcard columns remain available, and selected side-effect functions are not replaced with a fake compatibility shim.

Focused coverage:

- Added `SQLiteRealUpstreamSelectHOmitUnusedDynamicTest.php`.
- 1,000 dynamic `selectH-1.2` variants over `c0` through `c65` target/filter columns.
- 5,005 focused behavior assertions.
- 1,001 focused TestRunner PASS lines.

Non-overlap:

- This owns the residual `selectH.test` omit-unused-derived-column blocker and does not repeat accepted `select1` through `selectG` batches, grouped SELECT text, expression ORDER BY, JSON table source/cursor/constraint work, WAL/VFS/B-tree surfaces, or metadata-only runner rows.
- A separate `selectH-2.1` ordered-compound probe exposed an existing compound `ORDER BY` ordering gap and is left for a follow-up SELECT slice rather than mixed into this fix.

Verification:

- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelectHOmitUnusedDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectHOmitUnusedDynamicTest.php`
  - `1 test files, 5005 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This reuses the lane-local SELECT parser, compound SELECT executor, derived-table materialization, projection evaluator, and hydrated upstream SQLite test corpus.
