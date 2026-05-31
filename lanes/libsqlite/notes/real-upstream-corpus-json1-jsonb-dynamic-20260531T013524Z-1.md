# real-upstream-corpus-json1-jsonb-dynamic-20260531T013524Z-1

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`
- Upstream sections ported: `json101-22.1`, `json101-22.2`, `json101-23.1`, `json101-23.2`, and `json101-24.1` through `json101-24.8`.

Patch scope:

- Adds `SQLiteRealUpstreamJson101EditCacheSubstructureDynamicTest.php`.
- Ports repeated JSON edit-cache replacement behavior, parsed-and-edited array append extraction, and missing object/array substructure creation for `json_insert`, `json_set`, and `json_replace`.
- Extends each text behavior to the equivalent JSONB function where the port has native JSONB helpers.

Non-overlap:

- Avoids accepted JSON table cursor/source/hidden/visible constraint work.
- Avoids prior `json101-21` NULL propagation and existing `json105`/`json109` reverse-index/array-insert batches.
- Does not add metadata-only rows or fabricated upstream names.

Dependency closure:

- No new support component is needed. This reuses existing native JSONB encoder/decoder, JSON path parser, JSON mutation editor, JSON extraction, and canonical JSON helpers.
