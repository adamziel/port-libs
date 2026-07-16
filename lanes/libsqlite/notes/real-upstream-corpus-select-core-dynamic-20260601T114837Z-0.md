# real-upstream-corpus-select-core-dynamic-20260601T114837Z-0

Slice: `real-upstream-corpus-select-core-dynamic-20260601T114837Z-0`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectC.test`
- Ported scenario: `selectC-5.3`, with `selectC-5.1` and `selectC-5.2` as the upstream setup/readback context.
- Behavior covered: a direct compound derived source in the `FROM` list feeds `SELECT *` wildcard projection, then `ORDER BY 1,2` resolves against the expanded wildcard output columns.

## Patch

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamSelectCCompoundDerivedWildcardDynamic20260601T114837ZTest.php`.
- Adds `1,003` distinct TestRunner PASS cases:
  - 1 upstream source-truth citation case.
  - 1 canonical upstream-shaped `selectC-5.3` case.
  - 1,000 dynamic generic application-table cases.
  - 1 non-overlap/dependency-closure case.
- Focused behavior assertions in the new file: `5,015`.
- Mapped denominator remains `1,589 / 1,589`; `selectC.test` is already in the hydrated upstream manifest.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelectCCompoundDerivedWildcardDynamic20260601T114837ZTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCCompoundDerivedWildcardDynamic20260601T114837ZTest.php`
  - `1 test files, 5015 assertions, 0 failures`

## Non-Overlap

This slice owns the exact `selectC-5.3` wildcard/ordinal planner shape. Earlier `selectC` coverage used explicit outer columns for the compound-derived join and explicitly left this wildcard/ordinal path as a follow-up. This avoids accepted `selectC` alias visibility, `selectC-4.2` distinct-derived projection, grouped SELECT text, expression `ORDER BY`, scalar subqueries, JSON table source/cursor/constraint work, WAL/VFS/B-tree/PRAGMA storage surfaces, source-neutral cleanup, and metadata-only runner rows.

## Dependency Closure

No new support component is needed. The batch reuses lane-local `SQLiteSelectSql` wildcard expansion, compound SELECT source materialization, comma join row production, and `ORDER BY` ordinal resolution against the hydrated upstream SQLite `selectC.test` source truth.
