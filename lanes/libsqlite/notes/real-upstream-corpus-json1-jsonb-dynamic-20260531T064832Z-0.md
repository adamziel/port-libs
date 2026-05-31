## real-upstream-corpus-json1-jsonb-dynamic-20260531T064832Z-0

This isolated libsqlite slice ports real upstream JSON1/JSONB edit behavior
from the hydrated SQLite upstream checkout:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`
- `json101-22.1` and `json101-22.2`: repeated `json_set()` /
  `json_replace()` path edits keep the last value written to the same path.
- `json101-23.1` and `json101-23.2`: parsed and edited arrays remain
  addressable after repeated `$[#]` appends.
- `json101-24.*`: `json_insert()`, `json_set()`, and `json_replace()`
  behavior when the target path requires missing object/array substructure.

The new focused PHP test file is
`lanes/libsqlite/tests/SQLiteRealUpstreamJson101NestedEditDynamic20260531Test.php`.
It adds 1,002 distinct TestRunner PASS cases and 13,508 focused behavior
assertions. The batch exercises text JSON, JSONB BLOBs, `SQLiteSelectExpression`
dispatch, JSON tree visibility, and JSON extraction after mutation.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101NestedEditDynamic20260531Test.php`
  - `1 test files, 13508 assertions, 0 failures`
  - 1,002 PASS lines

Dependency closure: no new support component is needed. The slice reuses the
existing native JSON5 parser, JSONB codec, JSON mutation helpers, JSON tree
helpers, and SELECT expression dispatcher.

Non-overlap: this does not repeat accepted JSON table cursor/source/hidden or
visible constraint work, JSON102 mutation dynamic coverage, JSON107 BLOB
compatibility, JSON109 array insert, JSON105 reverse-index coverage, JSON108
pretty invariants, JSONB remove coverage, JSON501/502 coverage, or JSON
aggregate/window behavior.
