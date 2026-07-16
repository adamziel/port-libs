# real-upstream-corpus-json1-jsonb-dynamic-20260530T201237Z-0 blocked

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

Current accepted base `c1a0d2c80ea721e0595b20a5cbe43c5043856066` already
contains focused real-upstream JSON corpus tests for every viable hand-portable
section of the assigned JSON1/JSONB dynamic domain:

- `SQLiteRealUpstreamJson1JsonbDynamicTest.php`
- `SQLiteRealUpstreamJson1JsonbDynamicCorpusTest.php`
- `SQLiteRealUpstreamJson1JsonbDynamicExpansionTest.php`
- `SQLiteRealUpstreamJson1JsonbDynamicFollowupTest.php`
- `SQLiteRealUpstreamJson1JsonbDynamicHighYieldTest.php`
- `SQLiteRealUpstreamJson1JsonbDynamicNoEditCorpusTest.php`
- `SQLiteRealUpstreamJson102JsonbDynamicCorpusTest.php`
- `SQLiteRealUpstreamJson103AggregateDynamicCorpusTest.php`
- `SQLiteRealUpstreamJson103WindowMatrixDynamicTest.php`
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
`json501`, `json502`, and `jsonb01` behavior. In particular, `json104.test`
already has a 1,101-case RFC-7396 merge-patch corpus, `json106.test` already
has a 160-document invariant corpus, and `jsonb01.test` already has a dynamic
JSONB remove corpus. A fresh manual subset from the same scripts would overlap
accepted constructor, path, mutation, JSONB remove, JSON5, aggregate/window,
JSON table cursor/source/constraint, and invariant coverage and would not meet
the active hard handoff floor for a real-corpus ready patch.

## Next larger batch

The next useful JSON corpus batch should not be another manual hand-port. It
should add a lane-local Tcl JSON corpus extractor/admission helper that reads
real `do_execsql_test`, `do_catchsql_test`, and `foreach` rows from
`json101.test` through `json109.test`, `json501.test`, `json502.test`, and
`jsonb01.test`; maps runnable SQL function calls to existing native helpers;
and reports unmapped SQL forms explicitly. The acceptance target should be at
least `5,000` new distinct behavior assertions or a guarded runner-map
denominator move backed by the hydrated upstream filenames above. Until that
tool exists, this slice is blocked from producing a non-overlapping,
floor-compliant ready patch.

## Verification

- `git diff --check -- lanes/libsqlite`: pass

Root harness: not run - isolated micro-slice.

Dependency closure: no new external support component is needed. The missing
piece is bounded lane-local corpus extraction/admission tooling that reuses the
hydrated upstream SQLite checkout and existing native JSON helper classes.
