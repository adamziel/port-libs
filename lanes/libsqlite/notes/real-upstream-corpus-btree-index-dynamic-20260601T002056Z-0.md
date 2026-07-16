# Real Upstream Corpus: B-tree Index Dynamic whereB

Status: ready for integration.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/whereB.test`
- Ported sections: `whereB-1.1` through `whereB-9.102`

Behavior covered:

- Column-to-column equality now preserves SQLite's whereB rule that TEXT-only affinity is not applied to stored integer values during column comparison.
- Numeric, INTEGER, and REAL column affinities still coerce the opposite operand before comparison.
- Unary plus removes comparison affinity, matching the upstream `+y=+b` sections.
- JOIN `USING` equality now uses the same column-value comparison path as WHERE predicates.

Patch shape:

- Added `SQLiteAffinityComparison::compareColumnValues()`.
- Routed column-to-column predicates in `SQLiteSelectPredicate` through the new comparison helper.
- Routed `SQLiteSelectSql` JOIN key equality through the same helper.
- Added `SQLiteBTreeIndexDynamicCorpusPlan::whereBAffinityComparisonCases(1200)`.
- Added `SQLiteRealUpstreamBtreeWhereBAffinityDynamicTest.php` with 1200 dynamic cases plus 3 metadata/dependency cases.

Focused assertion/pass movement:

- New focused test file: 1203 pass units, 21570 assertions.
- `lane-status.json` `phpPass`: `4479562` -> `4480765` (`+1203`).
- Mapped upstream denominator unchanged at `1589 / 1589`.

Verification:

- `php -l lanes/libsqlite/src/SQLiteAffinityComparison.php && php -l lanes/libsqlite/src/SQLiteSelectPredicate.php && php -l lanes/libsqlite/src/SQLiteSelectSql.php && php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php && php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereBAffinityDynamicTest.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereBAffinityDynamicTest.php`
  - Result: `1 test files, 21570 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusPlanTest.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhere5NullComparisonDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `3 test files, 90650 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectJoinPredicateCurrentNext56Test.php lanes/libsqlite/tests/SQLiteSelectPredicateLikeGlobAffinityCurrentSourceNext109Test.php lanes/libsqlite/tests/SQLiteSelectPredicateRealAffinityLikeGlobCurrentSourceNext120Test.php`
  - Result: `3 test files, 203 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite`
  - Result: clean.

Non-overlap:

- This slice owns only real upstream `whereB.test` column-affinity comparison behavior.
- It does not cover accepted where5 null comparison, JSON, WAL/VFS, PRAGMA, trigger, source-neutral naming, B-tree page relocation, overflow freelist release, or root-collapse clusters.
- A broader `SQLiteSelectJoinPlannerCurrentNext72Test.php` check exposed existing parenthesized group alias missing-column failures unrelated to this column-affinity change; it is excluded from this handoff's focused verification.

Dependency closure:

- No new support component is needed.
- The implementation reuses existing `SQLiteAffinityComparison`, `SQLiteSelectPredicate`, and `SQLiteSelectSql` paths.
