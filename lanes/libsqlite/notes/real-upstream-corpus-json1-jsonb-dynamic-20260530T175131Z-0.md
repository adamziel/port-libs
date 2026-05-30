## real-upstream-corpus-json1-jsonb-dynamic-20260530T175131Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json105.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`

Ported behavior cluster:

- `json105-1.*`: dynamic JSON path extract with `[#]`, `[#-N]`, padded reverse indexes, huge reverse indexes, nested reverse indexes, multipath text and JSONB extract.
- `json105-2.*`: `json_remove()` and `jsonb_remove()` with append tokens, reverse indexes, nested removal, and ordered multi-path removal.
- `json105-3.*`, `json105-4.*`, `json105-5.*`: `json_insert()`, `json_set()`, `json_replace()` plus JSONB variants for append and reverse-index mutation.
- `json105-6.*`: malformed dynamic array path rejection.
- `json102-250..310`, `json102-360..500`, and `json102-510..580`: text/JSONB extract, set, remove, and type parity for object/array paths, missing paths, structural JSON arguments, ordered multi-path removal, root removal, and scalar JSON types.

Focused assertion count: 685 existing TestRunner assertions in `SQLiteRealUpstreamJsonDynamicPathCorpusTest.php` plus 93 new assertions / 32 PASS cases in `SQLiteRealUpstreamJson102JsonbDynamicCorpusTest.php`.

Non-overlap: this batch is not metadata admission, not generated fake suite rows, and not a WordPress-shaped scenario. It ports real upstream JSON dynamic path behavior from `json105.test`; accepted JSON table cursor/source/constraint work and prior JSON null-path/scalar-input tests are not repeated.

Dependency closure: no new support component is needed. The existing native PHP `SQLiteJsonB`, `SQLiteJsonExtract`, `SQLiteJsonMutation`, `SQLiteJsonRemove`, and `SQLiteJsonPath` components are reused.
