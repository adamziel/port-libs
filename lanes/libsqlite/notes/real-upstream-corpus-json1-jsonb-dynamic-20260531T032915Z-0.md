# real-upstream-corpus-json1-jsonb-dynamic-20260531T032915Z-0

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/jsonb01.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`

Ported behavior cluster:

- `jsonb01-1.2`: JSONB `jsonb_remove()` parity with text `json_remove()` for
  object member paths, array indexes, append-token no-ops, reverse indexes,
  nested object paths, and left-to-right multi-path removal order.
- `jsonb01-2.0`: the exact upstream malformed JSONB blob fails strict
  validation; related malformed-family blobs keep bounded validation,
  bounded error-position, and canonicalization behavior.
- `json102-190..240`: `json_array_length()` parity for text JSON and JSONB
  inputs over object roots, nested arrays, scalar paths, and missing paths.
- `json102-510..600`: `json_type()` parity for text JSON and JSONB over
  object, array, integer, real, true, false, null, text, and missing paths.
- `json102-1110`: `json_tree()` scalar row fullkey/atom parity over
  documentation-style nested objects for text JSON and JSONB.

Focused test file:

- `lanes/libsqlite/tests/SQLiteRealUpstreamJsonbDynamicRemovalInspection20260531Test.php`

Focused assertion count:

- `44466` assertions from `6` TestRunner PASS cases.

Non-overlap:

- This is not metadata admission and does not add generated fake upstream
  script ids.
- It avoids accepted JSON table cursor/source/hidden/visible constraint work,
  JSON aggregate/window work, JSON101 constructor-only coverage, JSON105
  reverse path mutation-only coverage, JSON106 invariants, JSON107 BLOB text,
  JSON108 pretty, JSON109 array insert, JSON501/502 JSON5/path work, and the
  previously noted JSONB remove-only slices by combining JSONB removal parity
  with JSON102 array-length/type/tree inspection behavior over dynamic nested
  documents.

Dependency closure:

- No new support component is needed. The batch reuses existing native PHP JSON
  helpers: `SQLiteJsonB`, `SQLiteJsonRemove`, `SQLiteJsonInspection`,
  `SQLiteJsonExtract`, `SQLiteJsonTree`, `SQLiteJsonCanonical`, and
  `SQLiteJsonValidity`.
