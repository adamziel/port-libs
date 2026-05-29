## Consolidation

Renamed the remaining public `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan`
compound SELECT handoff entry points in the 237-247 range to stable descriptive
names:

- `compareCurrentSourceDequeue()`
- `compareSourceGenerationSeal()`
- `compareCompoundLimitResumeFence()`
- `compareFinalPageSpilloverDrain()`
- `compareResumeAdmissionReceipt()`
- `compareRecursiveLimitWindowCommitFence()`
- `compareCompoundWindowReplayFence()`
- `compareRecursiveLimitExhaustionFence()`
- `compareNextSourcePromotionSnapshot()`
- `compareRecursiveLimitSourceHandoff()`
- `compareRecursiveOffsetYieldSeal()`

Direct tests, WordPress examples, and later canonical compound-select methods
now call the descriptive entry points. No numbered compatibility wrappers were
added; serialized cursor/result keys are preserved to avoid changing accepted
behavior.

## Verification

- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php` passed.
- `php -l` for the 11 migrated compound-select test files passed.
- `php -l` for the 11 migrated compound-select WordPress example files passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext{237,238,239,240,241,242,243,244,245,246,247}Test.php` passed: `11 test files, 5384 assertions, 0 failures`.
- `for f in lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next{237,238,239,240,241,242,243,244,245,246,247}.php; do php "$f" --self-test || exit $?; done` passed.
- `git diff --check -- lanes/libsqlite` passed.

## Dependency Closure

No new support component is needed. This consolidation reuses the existing
canonical compound SELECT current-source plan and only removes generated public
numbered entry-point names from the 237-247 method family.
