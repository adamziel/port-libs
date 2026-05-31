# real-upstream-corpus-json1-jsonb-dynamic-20260531T074749Z-0

- Base accepted HEAD: `9c30c680e4b44fbeb2fc11612b28622bb7d8e322`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`.
- Ported behavior cluster:
  - `json102-1300..1399`: generated `json_array($str)` quote-run stress rows for the historical `jsonAppendString()` off-by-one boundary.
  - `json102-1401..1415`: strict JSON numeric validity and JSON5 `json_error_position()` boundaries for leading zeroes, negative zeroes, decimal zeroes, and explicit plus signs.
- New focused PHP test: `lanes/libsqlite/tests/SQLiteRealUpstreamJson102StringNumericDynamicTest.php`.
- Focused coverage: 117 distinct TestRunner cases with 679 assertions once verified locally.
- Non-overlap: this avoids accepted json102 constructor/path/mutation/operator rows, json105 reverse path mutation, json109 array insert, json104 merge patch, json106/json108 invariant bulk rows, JSON501 control-character rows, and JSON table cursor/source/hidden/visible constraint work. The new cases specifically own the json102 quote-run append-string stress loop and numeric validity/error-position table.
- Dependency closure: no new support component needed; the slice reuses existing native JSON constructor, extract, validity, error-position, JSONB, canonicalization, and SELECT-expression dispatch helpers.
