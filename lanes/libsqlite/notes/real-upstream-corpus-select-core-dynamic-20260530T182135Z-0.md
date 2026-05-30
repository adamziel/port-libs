Micro-slice: real-upstream-corpus-select-core-dynamic-20260530T182135Z-0
Base accepted HEAD: 1be884bec4b3d8944d386430e62bb83a7a09f0ef

Source truth:
- /home/claude/port-libs/.upstream-cache/libsqlite/test/select4.test

Behavior added:
- Added a new focused PHP corpus file for upstream select4 compound SELECT
  behavior over the same logarithmic t1 data shape used by select4.test.
- Ported dynamic variants of select4-1.0, select4-1.1c/e/f, select4-1.2,
  select4-2.1/2.2, select4-3.1.1/3.1.3/3.2, and select4-4.1.1/4.2.
- The batch exercises UNION ALL, UNION, EXCEPT, INTERSECT, final ORDER BY
  direction, compound subquery IN membership, range predicates, modulo
  partitions, and sliding pivot windows through SQLiteSelectSql.

Focused delta:
- Added SQLiteRealUpstreamSelect4CompoundDynamicCorpusTest.php.
- Focused command:
  php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect4CompoundDynamicCorpusTest.php
- Result: 1 test files, 11922 assertions, 0 failures.
- PASS-line growth: +1987 distinct TestRunner PASS cases.
- Behavior assertion growth: +11922 focused assertions.
- Mapped denominator growth: none claimed.

Non-overlap:
- This slice owns real upstream select4.test compound SELECT behavior.
- It does not touch status-only runner admission, WAL, VFS, B-tree, JSON,
  source-neutral cleanup, or numbered production-source suffixes.
- It avoids accepted SELECT JOIN text, GROUP BY SQL text, expression ORDER BY,
  scalar WHERE operands, JSON table SELECT sources, select3/select5/select6
  aggregate batches, and suite ledger surfaces.

Parser limitation found and excluded:
- Nested IN subqueries whose inner SELECT arms include BETWEEN predicates, for
  example SELECT log FROM t1 WHERE n IN (SELECT DISTINCT log FROM t1 WHERE log
  BETWEEN ... UNION ...), currently fail in SQLiteSelectSql predicate parsing.
- The unsupported nested-BETWEEN membership variants were not admitted. The
  non-nested BETWEEN compound variants and nested membership variants using
  equality/modulo/limit predicates are included and passing.

Dependency closure:
- No new support component is needed. The existing bounded SQLiteSelectSql
  row-array executor and compound SELECT planner are reused.

Follow-up:
- Implement full subquery predicate parsing for BETWEEN inside compound
  subqueries, then admit the excluded select4 dynamic range-membership cases.
