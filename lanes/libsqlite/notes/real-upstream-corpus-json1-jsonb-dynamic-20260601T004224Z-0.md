# real-upstream-corpus-json1-jsonb-dynamic-20260601T004224Z-0

## Scope

- Lane: libsqlite
- Accepted base: `5b87111468b46af8cd72097f10d11bf759b0ca92`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`
- Ported sections: `json101-5.3`, `json101-5.4`, `json101-5.5`, `json101-5.6`, `json101-5.7`, `json101-5.8`

## Behavior

This slice ports the upstream json101 hidden-source invariants into parser-level PHP SELECT execution:

- `json_tree(...).json` and `json_each(...).json` can be explicitly projected and compared against the host JSON/JSONB input.
- `json_tree(...).root` and `json_each(...).root` can be explicitly projected and filtered.
- `json_tree` path/key/fullkey rows preserve the upstream path-plus-key invariant.
- Scalar `value` equals `atom`; container `value` projects JSON text while the underlying table-valued source keeps subtype state.
- Hidden `json` and `root` remain omitted from `*` and `alias.*` wildcard output.

The previous parser-level source rows exposed only visible JSON table columns. A focused pre-fix probe for
`SELECT jt.json AS hidden_json FROM app_settings, json_tree(app_settings.doc) AS jt`
failed with `SQLite SELECT expression row is missing column jt.json`; the same hidden-source comparison also failed in `WHERE`.

## Focused Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101HiddenSourceSelectDynamic20260601Test.php`
  - `1 test files, 592007 assertions, 0 failures`
  - 1000 dynamic upstream hidden-source SELECT cases plus 2 citation/dependency cases.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonEachIndexedRegressionTest.php`
  - `1 test files, 42 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableIndexedConstraintCorpusTest.php`
  - `1 test files, 48 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`

## Countability

- New PHP PASS cases: `+1002`
- New focused assertions: `592007`
- `lane-status.json` `phpPass`: `4479562 -> 4480564`
- Mapped upstream coverage: unchanged at `1589 / 1589`

## Dependency Closure

No new support component is needed. The slice reuses `SQLiteSelectSql`, `SQLiteSelectProjection`, `SQLiteSelectPredicate`, `SQLiteSelectQuery`, `SQLiteSelectResult`, `SQLiteJsonTablePlan`, `SQLiteJsonTree`, `SQLiteJsonEach`, and existing JSONB/subtype helpers.

## Non-Overlap

This does not repeat accepted JSON table cursor/source wiring, hidden-constraint extraction, visible constraint pushdown, JSON host joins, JSON scalar-root behavior, JSONB update/subtype behavior, or JSON102 scalar/root corpus coverage. It is limited to upstream json101 hidden `json`/`root` source-column projection/predicate parity and wildcard omission semantics.
