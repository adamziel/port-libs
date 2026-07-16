Real upstream corpus JSON1/JSONB dynamic slice

- Session: port-dev-sqlite-yield-dyn-real-json-20260531T011243Z
- Micro-slice: real-upstream-corpus-json1-jsonb-dynamic-20260531T011243Z-0
- Base accepted HEAD: 87abcd98ff24a32f5554f16930fc2af1462cc57c
- Upstream source truth:
  - /home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test
  - /home/claude/port-libs/.upstream-cache/libsqlite/test/jsonb01.test

Implemented coverage:

- Added SQLiteRealUpstreamJson102JsonbMutationDynamicTest.php with dynamic cases for json102-320 through json102-400 object-member mutation behavior across json_insert/json_replace/json_set and JSONB variants.
- Added JSONB parity cases for text input, JSONB input, json_* text output, and jsonb_* output.
- Added jsonb01-1.2 object member, array index, #, and #-N removal semantics for json_remove and jsonb_remove with JSONB input.

Focused evidence:

- php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102JsonbMutationDynamicTest.php
- Result: 1 test files, 4105 assertions, 0 failures
- PASS-line movement: +2053 distinct focused TestRunner PASS cases

Non-overlap:

- Avoids accepted JSON constructor/extract/type corpus, JSON table cursor/source/hidden/visible constraint clusters, JSON host joins, JSON subtype handoff, JSON aggregate/window clusters, and JSON malformed planner diagnostics.
- This slice owns upstream json102 mutation parity and jsonb01 JSONB remove parity only.

Dependency closure:

- No new support component is needed. The slice reuses existing bounded JSONB, JSON mutation, JSON remove, JSON canonicalization, JSON subtype, and JSON validity helpers.
