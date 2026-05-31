# real-upstream-corpus-select-core-dynamic-20260531T021224Z-0

Base accepted HEAD: `b8677cf94d5b050eacc055d83ba1f29b3739b6f1`

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectH.test`
- `selectH-5.1`: `SELECT DISTINCT val1 FROM t1 UNION ALL SELECT val2 FROM t2` with an empty right arm returns the distinct left-arm rows directly.
- Adjacent existing coverage already covers `selectH-5.2` count-wrapper behavior over the same upstream compound shape; this slice adds the direct result-row behavior.

## PHP coverage

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamSelectHDistinctUnionEmptyDynamicTest.php`.
- Focused PASS cases: 1002.
- Focused assertions: 7009.
- Dynamic cases: 1000 distinct left-arm duplicate matrices with a guaranteed empty right arm, preserving the upstream `UNION ALL` shape and asserting direct row values, result counts, and fingerprints.

## Non-overlap

This does not repeat accepted `selectH` omit-unused projection/count coverage, `selectH-5.2` count wrapper coverage, `selectG` VALUES coverage, `selectD` parenthesized JOIN coverage, `selectF` register-copy coverage, `selectA`/`selectE` compound collation coverage, grouped SELECT text, expression `ORDER BY`, JSON table source/cursor/constraint work, or metadata-only runner rows.

Mapped denominator remains unchanged because `selectH.test` is already represented in the hydrated upstream manifest/runner map.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectHDistinctUnionEmptyDynamicTest.php`
  - `1 test files, 7009 assertions, 0 failures`

## Dependency closure

No new support component is needed. This reuses the existing lane-local `SQLiteSelectSql` compound SELECT executor over row-array inputs and the hydrated SQLite upstream checkout as source truth.
