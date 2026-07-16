# STAT4 Unsampled Equality Bracket Suffix Cleanup

Consolidated the STAT4 expression-partial unsampled equality bracket production
entry point from `materializeNext171()` into the stable
`materializeUnsampledEqualityBracket()` method on
`SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`.

The private `*Next171()` helpers in that production block now use descriptive
unsuffixed names. Returned status strings, dependency keys, proof labels, and
diagnostic array keys are intentionally preserved for existing evidence
compatibility.

Direct callers were migrated to the stable method name:

- `SQLitePlannerStat4ExpressionPartialUnsampledEqualityBracketTest.php`
- `application-sqlplanner-stat4-unsampled-equality-bracket.php`

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialUnsampledEqualityBracketTest.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-unsampled-equality-bracket.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialUnsampledEqualityBracketTest.php`
- STAT4 family gate: `php tools/run-tests.php $(find lanes/libsqlite/tests -maxdepth 1 -name 'SQLitePlannerStat4ExpressionPartial*Test.php' | sort)`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-unsampled-equality-bracket.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this is production
helper-name consolidation over existing STAT4 expression-partial behavior.
