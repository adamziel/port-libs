# real-upstream-corpus-json1-jsonb-dynamic-20260531T073002Z-0

Implemented a focused upstream JSON1/JSONB dynamic corpus expansion from the
hydrated SQLite source file:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`
- Sections: `json101-1047`, `json101-1050`, `json101-1053..1062`, and
  `json101-1077..1098`.

The new PHP test file adds 1,002 distinct TestRunner cases:

- 1,000 dynamic rows for SQL NULL propagation across `json_extract`,
  `json_insert`, `json_remove`, `json_replace`, `json_set`, `json_type`, and
  `->` / `->>` operator dispatch.
- 1 source-citation case proving the hydrated upstream source rows are present.
- 1 dependency-closure note case.

Non-overlap:

- Avoids accepted JSON visible/hidden constraint, JSON table source/cursor,
  JSONB null-path mutation, JSON104 merge-patch, JSON105 reverse-path,
  JSON108 pretty, and JSON501 JSON5 dynamic corpus batches.
- This slice covers upstream JSON101 SQL NULL propagation and no-op mutation
  semantics through both direct helper calls and SELECT-expression dispatch.

Dependency closure: no new support component is needed; this reuses existing
JSON scalar dispatch, JSONB encoder, JSON path null handling, and SELECT
expression JSON operators.
