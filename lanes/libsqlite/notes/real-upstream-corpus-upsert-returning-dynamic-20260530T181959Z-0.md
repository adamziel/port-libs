# Real upstream UPSERT/RETURNING dynamic priority corpus

Slice: `real-upstream-corpus-upsert-returning-dynamic-20260530T181959Z-0`

Base accepted HEAD: `1be884bec4b3d8944d386430e62bb83a7a09f0ef`

## Upstream source truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test`
- Ported scenarios: `upsert1-700`, `upsert1-710`, `upsert1-720`,
  `upsert1-730`, `upsert1-740`, `upsert1-750`, `upsert1-760`,
  `upsert1-770`, and `upsert1-780`.

## Behavior

Added `SQLiteRealUpstreamUpsertReturningDynamicPriorityTest.php`, a focused
dynamic corpus matrix for upstream UPSERT conflict-target priority. Each case
uses one incoming row that violates several uniqueness constraints at once and
asserts that the named UPSERT conflict target is selected first before any other
violated constraint. The test also checks the final row image, matched arm,
RETURNING row projection, changed-row count, insert/update partition, and
unique-key preservation.

The matrix covers rowid primary-key, unique-index primary-key, and WITHOUT
ROWID layouts from `upsert1.test`, with 112 dynamic row-image variants for each
of the 9 upstream scenarios.

## Countability

- Focused PASS cases added: `1009`.
- Focused behavior assertions: `19153`.
- Expected dashboard movement: `phpPass +1009`; mapped coverage unchanged.
- Non-overlap: this extends the existing UPSERT/RETURNING dynamic corpus beyond
  prior statement lifecycle, correlated RETURNING, schema variants, trigger
  lifecycle, generalized multi-arm `upsert5`, and `upsert4` null/replace
  behavior. This slice specifically owns upstream `upsert1-700` through
  `upsert1-780` multiple-constraint target-priority behavior.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicPriorityTest.php`
  - Passed: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicPriorityTest.php`
  - Passed: `1 test files, 19153 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Passed: `1 test files, 3 assertions, 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - Passed: `lane-status json ok`.
- `git diff --check -- lanes/libsqlite`
  - Passed: no output.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
`SQLiteUpsertDoUpdateWherePlan` row-array conflict executor and RETURNING
projection helper.
