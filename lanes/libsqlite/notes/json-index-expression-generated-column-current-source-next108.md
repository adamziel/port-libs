# JSON Index Expression Generated Column Current Source Next108

This slice extends `SQLiteGeneratedJsonPathIndexPlan` so generated JSON columns
can be maintained through direct expression indexes such as
`CREATE INDEX ... ON wp_options(json_extract(option_value, '$.plugin.slug'))`
when the expression source column, JSON path, and JSON function match the
generated column definition.

Behavior covered:

- Matches direct `json_extract()` and `jsonb_extract()` expression indexes back
  to generated JSON columns by source column, path, and function family.
- Preserves current/next index-update metadata for expression indexes,
  including source column, expression function, collation, unique, partial, and
  descending flags.
- Allows multi-source generated JSON schemas by accepting an explicit update
  source column, while preserving the older single-source default.
- Carries expression-index metadata through update, B-tree yield, DELETE yield,
  and covering DELETE yield plans.
- Rejects mismatched JSON paths/functions and malformed expression paths rather
  than treating unsupported expression indexes as generated-column coverage.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonIndexExpressionGeneratedColumnCurrentSourceNext108Test.php`
  - `1 test files, 45 assertions, 0 failures`

Application smoke:

- `php lanes/libsqlite/examples/application-json-index-expression-generated-column-current-source-next108.php`

Non-overlap:

- This does not repeat accepted JSON aggregate/window, JSON table hidden/visible
  constraint, lateral rowid, malformed JSONB corpus, STAT4 expression range, or
  expression-covering ORDER planning surfaces. It is narrower: direct JSON
  expression-index maintenance for generated JSON columns on the current source.

Dependency closure:

- No new support component is needed. This reuses existing native PHP JSON path,
  JSONB, generated-column dependency, CREATE INDEX expression parsing, and B-tree
  cell/page helpers.
