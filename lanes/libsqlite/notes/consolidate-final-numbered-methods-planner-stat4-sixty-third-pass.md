2026-05-29 - planner STAT4 numbered helper consolidation, sixty-third pass

Scope:
- Consolidated the duplicate prepared-handoff range helpers for the 846-861
  continuation window and the prepared handoff resume-window in
  SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php.
- Routed both windows through the existing canonical preparedHandoffFenceForRange()
  and preparedHandoffCursorProgramForRange() helpers.
- Removed the two duplicate private helper pairs:
  preparedHandoffContinuationFence()/preparedHandoffContinuationCursorProgram()
  and preparedHandoffResumeFence()/preparedHandoffResumeCursorProgram().

Evidence:
- php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php
  => no syntax errors.
- php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext846861Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedHandoffResumeWindowTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedHandoffValidationContinuationTest.php
  => 3 test files, 117 assertions, 0 failures.
- git diff --check -- lanes/libsqlite
  => clean.

Dependency closure:
- No new support component is needed. This reuses the canonical range-handoff
  helper already used by later STAT4 prepared-handoff windows.

Counters:
- phpPass and mapped coverage are unchanged because this is production helper
  consolidation with focused regression coverage, not new behavior coverage.
