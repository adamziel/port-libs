# Real Upstream JSON102 JSONB Mutation/Type Dynamic Corpus

Slice: `real-upstream-corpus-json1-jsonb-dynamic-20260531T045014Z-0`

Accepted base for this worktree: `ea98db4ecded4356aee592549997cc44a35fab5b`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`
- Sections: `json102-320..400` insert/replace/set value semantics, `json102-440..500` ordered remove semantics, and `json102-510..600` `json_type` scalar classes.

Added PHP coverage:

- `lanes/libsqlite/tests/SQLiteRealUpstreamJson102JsonbMutationTypeDynamicTest.php`
- 100 dynamic fixtures with 10 distinct TestRunner cases each over text JSON and JSONB inputs, plus source-citation and dependency-closure cases.
- Focused movement: `+1002` TestRunner PASS lines, `2506` assertions.
- Mapped denominator movement: none; mapped upstream inventory already remains `1589 / 1589`.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102JsonbMutationTypeDynamicTest.php`
- Result: `1 test files, 2506 assertions, 0 failures`

Dependency closure:

- No new support component required. The slice reuses existing native PHP JSON constructor, mutation, removal, JSONB, type-inspection, subtype, and SELECT-expression dispatch components.

Non-overlap:

- Avoids accepted JSON path extraction, JSON table cursor/source/constraint, JSON grouped rows, JSON aggregate/window, JSON502 escaped-label, malformed JSONB planner, and JSON visible/hidden constraint surfaces.
