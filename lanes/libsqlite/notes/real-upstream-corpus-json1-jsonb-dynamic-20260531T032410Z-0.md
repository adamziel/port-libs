# real-upstream-corpus-json1-jsonb-dynamic-20260531T032410Z-0

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`
- Sections ported: `json101-11.0..11.3` and `json101-21.1..21.27`.

Focused coverage:

- 300 dynamic boundary cases for `json_valid()` and `json_error_position()` around the upstream 1000-level valid / 1001-level invalid array and object nesting limit.
- 700 dynamic NULL-input cases covering JSON validity, canonicalization, construction, extraction, mutation, patch, remove, arrow operators, type inspection, table-valued empty rowsets, and aggregate-equivalent constructor behavior.
- The new test adds 1001 distinct TestRunner PASS cases and more than 20,000 behavior assertions from real upstream JSON101 sections.

Non-overlap:

- This does not repeat accepted JSON table cursor/source/hidden/visible constraint work, JSON102/105 path mutation and JSONB extraction, JSON103 aggregate/window behavior, JSON104 merge patch matrix rows, JSON107 legacy text-BLOB behavior, JSON108 pretty invariants, JSON109 array insert behavior, or JSON501/502 JSON5 escaped path coverage.
- No production API, metadata-only admission row, generated fake upstream script id, or WordPress-shaped scenario is added.

Dependency closure:

- No new support component is needed; this reuses existing native PHP JSON validity, canonicalization, mutation, patch, table-valued, and SELECT-expression support.
