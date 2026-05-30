# real-upstream-corpus-select-core-dynamic-20260530T215053Z-0

Added `SQLiteRealUpstreamSelectHOmitUnusedDynamicCorpusTest.php` as an additive real upstream SELECT core corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectH.test`
- `selectH-1.2`: `SELECT DISTINCT` over a compound subquery with wildcard columns and an outer filter.
- `selectH-2.1`: compound subquery output ordering by a column not projected by the outer query.
- `selectH-3.4`: multi-arm `UNION ALL` subquery materialization with an outer filter.
- `selectH-5.1` and `selectH-5.2`: `DISTINCT` left arm plus empty right arm and aggregate count over the compound subquery.

Focused PHP coverage:

- 1,002 distinct TestRunner PASS cases.
- 4,005 focused behavior assertions.
- Dynamic generic application row sets vary the wide `c0` through `c65` upstream shape over 250 seeds and assert flattened results, counts, fingerprints, source citations, wildcard preservation, compound ordering, and aggregate counts.

Non-overlap:

- This slice owns the residual `selectH.test` omit-unused-subquery-column cluster and does not repeat the accepted `selectC.test` alias-resolution batch, `select1` through `select9` projection/grouping/compound batches, `selectA`/`selectB` large dynamic batches, `selectE`/`selectF` compound collation/copy batches, expression `ORDER BY`, grouped SELECT text, JSON table source/cursor/constraint work, or metadata-only runner rows.
- Mapped denominator remains unchanged because the lane already reports complete mapped coverage at `1589 / 1589`; this is PASS-line growth only.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectHOmitUnusedDynamicCorpusTest.php`
  - Result: `1 test files, 4005 assertions, 0 failures`
  - PASS lines: `1002`

Dependency closure:

- No new support component is needed. The batch reuses existing `SQLiteSelectSql` compound subquery, wildcard expansion, outer filtering, ordering, distinct, and aggregate-count behavior.
