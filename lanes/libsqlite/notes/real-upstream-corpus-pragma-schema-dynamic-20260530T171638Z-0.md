# Real Upstream Corpus: PRAGMA Schema Dynamic

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260530T171638Z-0`

Accepted base: `7ae2bafb13ace2a8edf7ffe53e4f4d55f2e4902f`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
  - `pragma-6.2` through `pragma-6.8`
  - `pragma-7.1.1` and `pragma-7.1.2`
  - `pragma-8.1.*` and `pragma-8.2.*`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma3.test`
  - `pragma3-100` through `pragma3-190`

Behavior ported:

- `PRAGMA table_info` now strips bracket/double-quoted declared types, uppercases declared type tokens consistently with SQLite PRAGMA output, and strips the outer parentheses from parenthesized default expressions.
- Table-level `PRIMARY KEY(a,b,a,c)` ordinal mapping now preserves the first ordinal for duplicate columns while still counting the duplicate position for later columns.
- Added focused real-upstream coverage for schema PRAGMA result rows, index metadata rows, foreign-key rows, generated-column `table_xinfo`, partial/expression index metadata, `schema_version`, `user_version`, and local/external `data_version` semantics.

Focused evidence:

- Red-first focused run before the parser fixes exposed declared-type, default-expression, and duplicate primary-key ordinal failures.
- Passing focused run after fixes: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicFollowupTest.php`
  - `1 test files / 65 assertions / 0 failures`
- Coupled catalog guard: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicFollowupTest.php lanes/libsqlite/tests/SQLitePragmaSchemaCatalogTest.php lanes/libsqlite/tests/SQLitePragmaIndexTableInfoAnalysisCurrentSourceNext108Test.php lanes/libsqlite/tests/SQLitePragmaTableXinfoGeneratedCurrentNext31Test.php lanes/libsqlite/tests/SQLitePragmaSchemaDataVersionCurrentNext25Test.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `6 test files / 330 assertions / 0 failures`

Expected dashboard movement:

- `phpPass`: `207759 -> 207824` (`+65` focused PASS lines)
- `mapped coverage`: unchanged at `958 / 1589`; this slice ports real upstream behavior but does not claim new denominator manifest rows.

Dependency closure:

- No new support component is needed. The slice reuses existing lane-local PRAGMA schema catalog and schema/data-version state primitives.

Non-overlap:

- This does not repeat accepted `SQLitePragmaSchemaDataVersionCurrentNext25Test` coverage alone; it adds real upstream `pragma.test` schema-query cases that exposed parser behavior gaps in declared type/default/duplicate-PK handling.
- This avoids source-neutral/API cleanup surfaces and does not introduce domain-specific names.
