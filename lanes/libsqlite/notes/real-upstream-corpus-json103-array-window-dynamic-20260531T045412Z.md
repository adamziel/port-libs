Real upstream JSON103 array-window dynamic corpus, 2026-05-31T04:54Z

Slice: real-upstream-corpus-json1-jsonb-dynamic-20260531T045412Z-0
Base accepted HEAD: d470482ec8f04bd52049cae518f9a06a2103fe0c

Upstream source:
- /home/claude/port-libs/.upstream-cache/libsqlite/test/json103.test
- Ported section: json103-400 json_group_array(x) as a window function over
  ROWS 2 PRECEDING.

Patch:
- Added SQLiteRealUpstreamJson103ArrayWindowDynamicMegaTest.php.
- The batch expands json103-400 into 1,000 deterministic array-window cases
  covering varying frame widths, mixed SQL scalar values, NULLs, boundary
  positions, JSONB parity, array length/type checks, and first/last path
  extraction.
- One citation/dependency test records source ownership and confirms no new
  support component is needed.

Non-overlap:
- Avoids accepted JSON table cursor/source/hidden/visible-constraint work.
- Avoids accepted JSON103 object-window mega coverage by targeting
  json_group_array json103-400, not json_group_object json103-410.
- Avoids JSON104 patch, JSON105 reverse-index, JSON106/108 invariant,
  JSON107 BLOB-text, JSON109 array-insert, JSON501/502, and jsonb01 remove
  surfaces already present in this worktree.

Focused evidence:
- php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson103ArrayWindowDynamicMegaTest.php
  => No syntax errors detected.
- php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson103ArrayWindowDynamicMegaTest.php
  => 1 test files, 7003 assertions, 0 failures; 1001 PASS lines.

Expected dashboard movement:
- Count as PASS-line growth from real upstream behavior: +1001.
- Count as behavior assertion growth: +7003 assertions.
- Mapped denominator movement: none; mapped coverage is already complete.

Dependency closure:
- No new support component needed; existing SQLiteJsonAggregate,
  SQLiteJsonCanonical, SQLiteJsonInspection, and SQLiteJsonExtract behavior is
  reused.
