# real-upstream-corpus-select-core-dynamic-20260530T193242Z-0

Added `SQLiteRealUpstreamSelectCAliasDynamicCorpusTest.php` as an additive real upstream SELECT core corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectC.test`
- `selectC-1.1` through `selectC-1.6`: SELECT result aliases and equivalent source expressions are visible to `WHERE`.
- `selectC-1.8` through `selectC-1.11`: SELECT result aliases and equivalent source expressions are visible through `GROUP BY` and `HAVING`.
- `selectC-1.12` through `selectC-1.14`: function result aliases participate in `DISTINCT`, `GROUP BY`, and `ORDER BY`.

Focused PHP coverage:

- 1,814 distinct TestRunner PASS cases.
- 7,264 focused behavior assertions.
- Dynamic generic application row sets vary tenant ids, key fragments, concatenated alias values, grouped output, and upper-case function aliases over 360 seeds.

Non-overlap:

- This slice owns the residual `selectC.test` alias-resolution cluster and does not repeat the accepted `select1` through `select9` projection/grouping/compound batches, `selectA`/`selectB` large dynamic batches, `selectE`/`selectF` compound collation/copy batches, expression `ORDER BY`, grouped SELECT text, JSON table source/cursor/constraint work, or metadata-only runner rows.
- Mapped denominator remains unchanged because `selectC.test` is already present in the hydrated upstream runner-map evidence.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCAliasDynamicCorpusTest.php`
  - Result: `1 test files, 7264 assertions, 0 failures`

Dependency closure:

- No new support component is needed. The batch reuses existing `SQLiteSelectSql` projection, alias resolution, expression evaluation, distinct, grouping, having, and ordering behavior.
