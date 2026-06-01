# Real Upstream JSON101 NULL Semantics SELECT SQL Dynamic Slice

Micro-slice: `real-upstream-corpus-json1-jsonb-dynamic-20260601T205737Z-0`

Base accepted HEAD: `c144809a94c645c49c7b403532a568b23ab72dd3`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`
- Covered sections: `json101-21.1-correct` through `json101-21.27`

Behavior added:

- Adds parser-level `SQLiteSelectSql` corpus coverage for SQLite JSON NULL input semantics.
- Exercises SQL text dispatch for `json_valid`, `json_error_position`, `json`, `json_array`, `json_extract`, `json_insert`, `->`, `->>`, `json_patch`, `json_remove`, `json_replace`, `json_set`, `json_type`, `json_quote`, `json_each(NULL)` / `json_tree(NULL)` empty-rowset counts, `json_group_array`, `json_group_object`, and `json_object` NULL-label error handling.
- Uses 1000 dynamic row variants so NULL propagation, unchanged-document cases for NULL paths, aggregate ordering, `2.0` preservation, NULL aggregate values, and NULL object labels are verified through SELECT execution rather than direct helper calls only.

Focused evidence:

- First probe failed on an incorrect worker expectation for upstream `json101-21.13`; upstream requires `json_patch(doc, NULL)` to return SQL NULL.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101NullSemanticsSelectSqlDynamic20260601Test.php`
- Result: `1 test files, 33006 assertions, 0 failures`
- PASS-line count: `1002` focused cases

Non-overlap:

- Avoids accepted JSON table cursor/source/constraint pushdown slices, `json102` lexical JSON/JSONB/subtype coverage, `json103` aggregate/window coverage, `json104` patch/merge/quoted-path coverage, `json105` reverse index coverage, `json106` invariant coverage, `json107` legacy BLOB coverage, `json108` pretty-printing coverage, `json109` atomic error coverage, `json501`/`json502`, and `jsonb01` malformed JSONB corpus.
- This slice specifically covers hydrated upstream `json101.test` NULL JSON input semantics through parser-level SELECT SQL execution.

Dependency closure:

- No new support component is needed. The slice reuses `SQLiteSelectSql`, JSON scalar dispatch, JSON operator dispatch, JSON aggregate dispatch, and the existing `TestRunner`.

Status delta:

- `phpPass` moves `6256079 -> 6257081` from `1002` focused PASS cases.
- Focused assertion count is `33006`.
- `phpFail` remains `16`; broad full-lane/release parity was not run in this isolated micro-slice.
