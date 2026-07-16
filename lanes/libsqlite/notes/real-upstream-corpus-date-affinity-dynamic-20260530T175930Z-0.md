# real-upstream-corpus-date-affinity-dynamic-20260530T175930Z-0

Base accepted HEAD: `f66597de21a7c168178b6eec67c6e12b5daf324d`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date2.test`
- Ported scenarios:
  - `date2-100..140`: deterministic `date()` use in CHECK/generated columns.
  - `date2-200..430`: deterministic expression and partial indexes over `date()`/`datetime()`.
  - `date2-500..520`: deterministic modifier table, with `localtime`/`utc` rejected in schema indexes.
  - `date2-600..620`: `julianday('now')` rejected in CHECK, index, and generated-column contexts.

## Behavior

Adds generic `SQLiteCoreScalarFunction::isDeterministicSqlFunctionCall()` and
`assertDeterministicSqlFunctionCall()` so schema-building code can reject
clock-dependent date/time scalar calls while allowing literal, numeric, and
row-value date affinity calls. No domain-specific API names were added.

## Verification

- `php -l lanes/libsqlite/src/SQLiteCoreScalarFunction.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamDate2DeterministicSchemaGuardCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDate2DeterministicSchemaGuardCorpusTest.php`
  - `1 test files, 1423 assertions, 0 failures`
  - `712` focused PASS lines
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDate2DeterministicSchemaGuardCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamDate2DeterministicDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAutoUnixepochModifierCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicCorpusTest.php`
  - `4 test files, 5781 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
- No `SQLiteNoWordPressSpecificApiTest.php` file was present in this worktree.

## Dependency Closure

No new support component is needed. The slice reuses the existing
`SQLiteCoreScalarFunction` date/time parser and adds a bounded determinism guard
for schema contexts.
