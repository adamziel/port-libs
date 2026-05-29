## Consolidation

Renamed the final `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan`
generated 248-258 tail methods and their private helpers/cursor keys to stable
descriptive names:

- `compareNextSourcePromotionFence()`
- `compareRecursiveWindowPromotionEpoch()`
- `compareNextPageAdmission()`
- `compareDeltaAudit()`
- `compareFinalPageYieldWatermark()`
- `compareCurrentSourceAdmission()`
- `comparePromotionReceipt()`
- `compareContinuationResume()`
- `compareCurrentLimitResumeFence()`
- `compareSourceSwitchCheckpoint()`
- `compareCurrentSourceHandoff()`

Direct compound-select tests and WordPress examples now call the descriptive
entry points. No compatibility shims or numbered production wrappers were
introduced.

## Verification

- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php -l` for the 11 migrated compound-select tail test files passed
- `php -l` for the 11 migrated compound-select WordPress example files passed
- Focused compound-select tail test run passed: `11 test files, 5395 assertions, 0 failures`
- `for f in lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next{248,249,250,251,252,253,254,255,256,257,258}.php; do php "$f" --self-test || exit $?; done` passed

## Dependency Closure

No new support component is needed. This consolidation reuses the existing
canonical compound SELECT current-source plan implementation and only removes
generated numeric method/helper names from the final tail.
