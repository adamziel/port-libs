# real-upstream-corpus-select-core-dynamic-20260530T205323Z-0

Added a real upstream SELECT core dynamic batch for nested parenthesized JOIN
name-resolution behavior from the hydrated SQLite upstream corpus.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectD.test`
- `selectD-1.2.1` through `selectD-1.2.7`: nested parenthesized JOINs resolve
  unqualified, schema-qualified, and aliased table names through ON clauses.
- `selectD-1.3` and `selectD-1.4`: nested parenthesized JOINs with `USING(a)`
  compare the coalesced join key from a right-side parenthesized join group.
- `selectD-1.5` through `selectD-1.7`: split parenthesized LEFT JOIN groups
  preserve NULL extension after name resolution.
- The dynamic batch mirrors the same behavior for the query-flattener-disabled
  `selectD-2.*` upstream repeat.

Behavior:

- `SQLiteSelectSql::usingPredicate()` now resolves both sides of a `USING`
  comparison to column sets and compares coalesced non-NULL values from each
  side. This fixes right-side parenthesized join groups where more than one
  qualified column shares the `USING` name.
- Added `SQLiteRealUpstreamSelectDNestedJoinDynamicTest.php` with 1,250 dynamic
  nested JOIN cases plus a source-citation case. Each dynamic case exercises
  nested `ON`, nested `USING`, split LEFT JOIN NULL extension, and qualified
  `main`/`aux1` table aliases.

Focused PHP coverage:

- 1,251 distinct TestRunner PASS cases.
- 22,504 focused behavior assertions.

Non-overlap:

- This slice extends the already accepted `selectD.test` simple parenthesized
  table-reference coverage into nested parenthesized JOIN, `USING`, LEFT JOIN,
  and schema-qualified alias sections.
- It does not repeat `selectC` alias resolution, `select8` LIMIT/OFFSET,
  `selectH` omit-unused-subquery-column work, grouped SELECT text, expression
  `ORDER BY`, JSON table source/cursor/constraint work, or metadata-only runner
  rows.
- Mapped denominator remains unchanged because `selectD.test` is already
  present in the hydrated upstream inventory.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectDNestedJoinDynamicTest.php`
  - Result: `1 test files, 22504 assertions, 0 failures`

Dependency closure:

- No new support component is needed. The batch reuses existing
  `SQLiteSelectSql` parser/executor support for parenthesized joins, qualified
  table resolution, JOIN predicates, and LEFT JOIN null-extension.
