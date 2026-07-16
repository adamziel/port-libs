# real-upstream-corpus-pragma-schema-dynamic-20260531T034444Z-0

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260531T034444Z-0`
Base accepted HEAD: `ca2d3c3a4732734353ce27d70067c3ae40d81496`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema5.test`
- Ported scenarios: `schema5-1.1` through `schema5-1.7`
- Behavior: SQLite accepts legacy `CREATE TABLE` syntax where adjacent table
  constraints are not separated by commas. PRAGMA-backed schema metadata must
  still expose the generated primary-key and unique autoindex columns.

## Implementation

- Updated `SQLiteCreateTable::automaticIndexColumns()` to scan top-level
  adjacent `UNIQUE(...)` table constraints after another table constraint in
  the same comma-delimited definition.
- Added a reusable top-level keyword finder so the scan respects quoted strings,
  bracket-quoted identifiers, and parenthesis depth.

## Focused Evidence

- Red before source fix:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicLegacyConstraintsTest.php`
  - `1 test files, 5404 assertions, 300 failures`
  - Missing `UNIQUE(b)` autoindex metadata for the upstream `schema5-1.3`
    legacy named-constraint form.
- Green after source fix:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicLegacyConstraintsTest.php`
  - `1 test files, 5404 assertions, 0 failures`
  - `901` focused PASS lines.

## Non-Overlap

This slice does not repeat the earlier PRAGMA/table-list shadowing, runtime
state, schema-version, application-id, cache-spill, table-valued PRAGMA, or
corrupt-view coverage. It only covers the `schema5.test` legacy adjacent
constraint syntax and the resulting PRAGMA `index_list`, `index_info`, and
`index_xinfo` autoindex metadata.

## Dependency Closure

No new support component is needed. The existing PHP CREATE TABLE metadata
parser and PRAGMA schema catalog are reused.
