# real-upstream-corpus-json1-jsonb-dynamic-20260531T054451Z-0

Base accepted HEAD: `db171f640e25dd929585c8e1b7a1c804219fdfee`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`

Ported sections:

- `json101-20.1`: `json_object('a',2e370,'b',-3e380)` canonicalizes infinities to `9.0e+999` and `-9.0e+999`.
- `json101-20.2`: `->>` returns positive SQL infinity from the generated object member.
- `json101-20.3`: `->>` returns negative SQL infinity from the generated object member.

Focused coverage:

- Added `SQLiteRealUpstreamJson101InfinityDynamicTest.php` with 1000 dynamic cases plus source-citation coverage.
- Each case checks text `json_object`, JSONB `jsonb_object`, `SQLiteSelectExpression` function dispatch, `->` JSON-text extraction, `->>` SQL-value extraction, JSON type preservation, and JSONB decode parity.
- Focused assertion count: 12004 assertions in 1001 TestRunner PASS cases.

Non-overlap:

- This does not repeat JSON101 escape, NULL, depth, scalar-root, quoted-empty-path, constructor, table-valued invariant, JSON102 mutation/path, JSON103 aggregate/window, JSON104 patch, JSON105 reverse-index, JSON106 invariant, JSON107 BLOB compatibility, JSON108 pretty, JSON109 array-insert, JSON501/502 JSON5/path, or jsonb01 malformed/remove coverage.
- The slice is limited to the upstream JSON infinity behavior in `json101-20.1..20.3`.

Dependency closure:

- No new support component is needed. The slice reuses existing native JSON constructor, JSONB, JSON inspection, and SELECT expression dispatch primitives.
