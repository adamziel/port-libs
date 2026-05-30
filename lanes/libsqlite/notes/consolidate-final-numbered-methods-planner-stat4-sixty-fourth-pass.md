## Planner STAT4 Numbered Method Consolidation Sixty-Fourth Pass

Consolidated a bounded planner/stat4 fence cluster in
`SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` into these
descriptive entry points:

- `materializeCurrentPartialPredicateFence()`
- `materializeCurrentCoveringPayloadFence()`
- `materializeCurrentScanDirectionFence()`
- `materializeCurrentStat4PayloadFence()`

Private helpers in the same cluster were renamed to descriptive canonical
names, and direct tests/examples/notes were renamed away from numbered
`CurrentSourceNext250-253` filenames. Existing serialized payload keys such as
`next250Ready` are intentionally preserved for behavior compatibility with the
focused assertions.

Verification:

- `php -l` on changed PHP files.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentPartialPredicateFenceTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentCoveringPayloadFenceTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentScanDirectionFenceTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentStat4PayloadFenceTest.php`
- Application example self-tests for the four renamed examples.
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this reuses the
existing STAT4 expression-partial current-source fence implementation and only
removes numbered production method/helper surfaces plus direct numbered
test/example filenames.
