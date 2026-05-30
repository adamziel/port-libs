# real-upstream-corpus-select-core-dynamic-20260530T203303Z-0

- Base accepted HEAD: `d5feb4b8c9f51e52c1a4ee4e369261ca23aa819e`.
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectE.test`
    - `selectE-1.0` through `selectE-1.3`: compound `EXCEPT` result collation remains independent from final `ORDER BY ... COLLATE`.
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectF.test`
    - `selectF-2`: compound `SELECT * ... UNION ALL ... ORDER BY 2, 1` must order by expanded wildcard output columns.
- Implementation:
  - `SQLiteSelectSql` now expands wildcard projection metadata when deriving compound SELECT output columns and compound `ORDER BY` ordinals.
  - This fixes the current-base failure where `ORDER BY 2, 1` after `SELECT * ... UNION ALL ...` treated wildcard projection as a single result column and threw `SQLite SELECT SQL compound ORDER BY ordinal is out of range`.
- Focused coverage:
  - Added 20 real upstream behavior PASS cases to `SQLiteRealUpstreamSelectCoreDynamicTest.php`.
  - Added approximately 254 behavior assertions over `selectE.test` and dynamic `selectF.test` variants.
  - Focused result: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicTest.php` -> `1 test files, 8910 assertions, 0 failures`.
- Non-overlap:
  - This slice does not repeat accepted single-table/JOIN/GROUP BY SELECT SQL text, expression ORDER BY, selectC alias in-list/grouping, select6 subquery LIMIT, or select1/select2/select3/select5 baseline coverage.
  - The new behavior is specifically compound SELECT wildcard output-width metadata for final ORDER BY ordinals, plus selectE compound EXCEPT collation ordering.
- Dependency closure:
  - No new support component needed; this reuses the existing PHP SELECT parser/executor and row-array compound executor.
