# real-upstream-corpus-json1-jsonb-dynamic-20260531T031019Z-0

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`
- Ported sections: `json101-7.1..7.7`, `json101-8.1`, `json101-8.1b`,
  `json101-8.2`, `json101-8.3`, `json101-8.4`, and `json101-9.1..9.7`.

Behavior covered:

- JSON whitespace is limited to space, tab, LF, and CR; form-feed remains
  invalid in leading, inter-token, and trailing positions.
- `json_array()` and `jsonb_array()` escape control characters `1..31`,
  preserve space/quote/hash bytes, and round-trip through `json_extract()`.
- A high-byte text JSON string validates and extracts the byte value used by
  upstream `unicode(json_extract(...))`.
- `json_quote()` preserves scalar SQLite behavior for text, numbers, NULL,
  BLOB rejection, and argument-count errors.

Focused evidence:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamJson101ControlQuoteDynamicMegaTest.php`.
- Verified focused TestRunner PASS lines: 1007.
- Verified focused assertions: 8045.
- Dependency closure: no new support component needed; this reuses existing
  native JSON constructor, JSONB, quote, validity, canonicalization, extraction,
  and error-position helpers.

Non-overlap:

- This slice avoids JSON table cursor/source/hidden/visible constraint work and
  does not repeat accepted JSON102 path/operator/lexical, JSON103 aggregate,
  JSON104 patch, JSON105 reverse/index mutation, JSON106 invariant, JSON107
  BLOB text/operator, JSON108 pretty, JSON109 array-insert, JSON501/502 JSON5
  escaped-path, or jsonb01 remove coverage. It specifically ports the
  `json101.test` control-character, whitespace, and `json_quote()` sections.
