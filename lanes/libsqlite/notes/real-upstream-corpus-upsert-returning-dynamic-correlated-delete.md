real-upstream-corpus-upsert-returning-dynamic-correlated-delete

- Base accepted HEAD: de394d1a2a5407b1856e89f4b996c5ea3450f50d.
- Upstream source truth: /home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test.
- Ported sections: returning1.test 20.1, 20.2, and 20.3.
- Behavior: RETURNING subqueries over the table being deleted are correlated with the statement and recomputed after each row deletion instead of being cached once. Section 20.3 also verifies outer deleted-row references are applied to each recomputed aggregate.
- Focused growth: 1,053 selected TestRunner PASS cases in SQLiteRealUpstreamCorpusUpsertReturningDynamicCorrelatedDeleteTest.php.
- Non-overlap: this does not repeat existing upsert5 conflict-arm priority, UPSERT RETURNING trigger/savepoint, row-value RETURNING windows, or earlier dynamic UPSERT target-analysis batches. It specifically owns returning1 correlated DELETE RETURNING aggregate subqueries.
- Dependency closure: no new support component needed; the slice adds a bounded native PHP helper under lanes/libsqlite/src.
- Verification:
  - php -l lanes/libsqlite/src/SQLiteReturningCorrelatedDeletePlan.php: passed.
  - php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicCorrelatedDeleteTest.php: passed.
  - php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicCorrelatedDeleteTest.php: 1 test files, 1053 assertions, 0 failures.
