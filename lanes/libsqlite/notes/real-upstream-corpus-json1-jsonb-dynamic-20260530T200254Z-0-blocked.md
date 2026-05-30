# real-upstream-corpus-json1-jsonb-dynamic-20260530T200254Z-0 blocked

## Upstream source truth inspected

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json103.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json104.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json105.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json106.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json107.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json108.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json109.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json501.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json502.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/jsonb01.test`

## Blocker

The current accepted base already contains focused real-upstream JSON corpus
files for the viable hand-portable sections of the assigned JSON1/JSONB dynamic
domain:

- `SQLiteRealUpstreamJson1JsonbDynamicTest.php`
- `SQLiteRealUpstreamJson1JsonbDynamicCorpusTest.php`
- `SQLiteRealUpstreamJson1JsonbDynamicExpansionTest.php`
- `SQLiteRealUpstreamJson1JsonbDynamicFollowupTest.php`
- `SQLiteRealUpstreamJson1JsonbDynamicHighYieldTest.php`
- `SQLiteRealUpstreamJson1JsonbDynamicNoEditCorpusTest.php`
- `SQLiteRealUpstreamJson102JsonbDynamicCorpusTest.php`
- `SQLiteRealUpstreamJson103AggregateDynamicCorpusTest.php`
- `SQLiteRealUpstreamJson104MergePatchDynamicCorpusTest.php`
- `SQLiteRealUpstreamJson106InvariantBulkDynamicTest.php`
- `SQLiteRealUpstreamJson501502DynamicBulkTest.php`
- `SQLiteRealUpstreamJson501Json502DynamicCorpusTest.php`
- `SQLiteRealUpstreamJsonDynamicCorpusTest.php`
- `SQLiteRealUpstreamJsonDynamicPathCorpusTest.php`
- `SQLiteRealUpstreamJsonInvariantDynamicTest.php`
- `SQLiteRealUpstreamJsonPatchDynamicCorpusTest.php`
- `SQLiteRealUpstreamJsonb01RemoveDynamicCorpusTest.php`

Those files already cite and exercise upstream `json101`, `json102`,
`json103`, `json104`, `json105`, `json106`, `json107`, `json108`, `json109`,
`json501`, `json502`, and `jsonb01` behavior. A fresh small hand-port from the
same scripts would overlap accepted constructor, path, mutation, JSONB remove,
JSON5, aggregate/window, JSON table cursor/source/constraint, and invariant
coverage and would not satisfy the active hard floor for a real-corpus ready
handoff.

## Next larger batch

The next useful batch is not another manual JSON subset. It should add a
lane-local Tcl JSON corpus extractor/admission tool that reads real
`do_execsql_test`, `do_catchsql_test`, and `foreach` rows from `json101.test`
through `json109.test`, `json501.test`, `json502.test`, and `jsonb01.test`,
then maps runnable SQL function calls to existing native helpers and reports
unmapped SQL forms explicitly. The acceptance target should be at least `5,000`
new distinct behavior assertions or a runner-map denominator move backed by
the hydrated upstream filenames above. Until that tool exists, this slice is
blocked from producing a non-overlapping, floor-compliant ready patch.

## Verification

- `git diff --check -- lanes/libsqlite`: pass

Root harness: not run - isolated micro-slice.

Dependency closure: no new support component is needed for the blocked note.
The next attempt should reuse the hydrated upstream SQLite checkout and existing
native JSON helper classes; the missing piece is lane-local corpus extraction
and admission tooling, not a new external dependency.
