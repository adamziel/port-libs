# consolidate-final-numbered-methods-upstream-suite-fifteenth-pass

## Scope

Consolidated the remaining numbered method wrappers in
`SQLitePlannerCoveringStat4RangeCurrentSourceNextPlan` into descriptive
canonical method names:

- `materializeCoveringStat4Range`
- `coveredRangeRows`
- `rowSatisfiesPointTerms`
- `flattenAndTerms`
- `withinRange`
- `stat4RangeBuckets`
- `currentAndNextRows`
- `cursorProgram`
- `compareValues`
- `keySignature`

Direct production adapter, focused test, and Application example callers now use
the stable public entrypoint. Behavioral result keys and evidence labels were
left unchanged to preserve existing scenario assertions.

## Verification

- `php -l lanes/libsqlite/src/SQLitePlannerCoveringStat4RangeCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/src/SQLitePlannerStat4CoveringRangeCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerCoveringStat4RangeCurrentSourceNext138Test.php`
- `php -l lanes/libsqlite/examples/application-planner-covering-stat4-range-current-source-next138.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerCoveringStat4RangeCurrentSourceNext138Test.php`
  - `1 test files, 59 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4CoveringRangeCurrentSourceNext140Test.php`
  - `1 test files, 55 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-planner-covering-stat4-range-current-source-next138.php --self-test`
  - `application-planner-covering-stat4-range-current-source-next138 self-test passed`
- `php lanes/libsqlite/examples/application-planner-stat4-covering-range-current-source-next140.php --self-test`
  - `application-planner-stat4-covering-range-current-source-next140 self-test passed`

## Dependency Closure

No new support component is needed. This is a production method-name
consolidation only; it reuses the existing STAT4 range-order planner helpers.
