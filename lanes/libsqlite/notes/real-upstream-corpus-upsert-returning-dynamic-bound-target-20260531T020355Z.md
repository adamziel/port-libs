# Real Upstream Corpus: UPSERT Bound Conflict Target

Slice: `real-upstream-corpus-upsert-returning-dynamic-20260531T020355Z-0`

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test`
- Sections `upsert1-1200` and `upsert1-1210`

Behavior ported:

- A UNIQUE expression index on `b+3` admits literal-equivalent conflict targets such as `b+3`, parenthesized `b+3`, and case/whitespace variants.
- A conflict target containing a bound parameter, such as `b+?1`, must not match the UNIQUE expression index and returns the SQLite diagnostic `ON CONFLICT clause does not match any PRIMARY KEY or UNIQUE constraint`.
- Nearby literal expressions like `b+4` also remain non-matching.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteUpsertConflictTargetExpressionPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicBoundTargetTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicBoundTargetTest.php`
- Result: `1 test files, 3518 assertions, 0 failures`
- New focused PASS cases: `1258`

Non-overlap:

- Avoids already accepted dynamic UPSERT/RETURNING coverage for `upsert1-100`, `upsert1-320`, `upsert1-700`, `upsert1-1300`, `upsert4` target analysis, `returning1-4.5`, and `returning1-20`.
- This slice owns only the `upsert1-1200/1210` bound-parameter conflict-target expression identity behavior.

Dependency closure:

- No new support component needed. The bounded analyzer is native PHP and lane-local.
