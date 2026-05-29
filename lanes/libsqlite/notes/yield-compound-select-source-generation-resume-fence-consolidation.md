# Compound SELECT Source-Generation/Resume-Fence Consolidation

Timestamp: 2026-05-29T17:06:04Z

Scope:
- Consolidated the remaining numbered helper/result/cursor/status surface in `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php` for source-generation seals and compound LIMIT resume fences.
- Renamed the direct compound SELECT test/example files to descriptive unsuffixed names.
- Updated the dependent resume-admission receipt path to consume the descriptive source-generation seal keys.

Verification:
- `php -l` passed for the changed compound source, three changed/affected tests, and two changed examples.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitSourceGenerationSealTest.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCompoundLimitResumeFenceTest.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext241Test.php` passed: `3 test files, 1529 assertions, 0 failures`.
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-source-generation-seal.php --self-test` passed.
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-compound-limit-resume-fence.php --self-test` passed.

Dependency closure:
- No new support component needed. This is a consolidation-only patch that reuses the existing SELECT SQL compound, recursive CTE trace, window-ranking, source-generation seal, and resume-fence helpers.

Non-overlap:
- This patch removes numbered compound SELECT helper/test/example names only for the source-generation-seal and compound-limit-resume-fence surfaces. It does not add new functional coverage and does not repeat JSON, WAL/VFS, B-tree, planner STAT4, trigger, rowvalue, or suite-evidence consolidation surfaces.
