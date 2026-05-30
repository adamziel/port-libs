# real-upstream-corpus-json1-jsonb-dynamic-20260530T234200Z-0

Added `SQLiteRealUpstreamJson102OperatorPathStressTest.php`, a real upstream
JSON operator stress batch based on hydrated SQLite upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`
- Ported sections: `json102-1600`, `json102-1610`, `json102-1620`, and
  `json102-1800` through `json102-1831`.
- Focused behavior: `->` and `->>` parity against `json_extract()` and
  `json_type()` for object keys, array indexes, full path RHS values, quoted
  object labels, integer RHS array addressing, numeric-looking string RHS
  object addressing, text JSON input, JSON subtype input, and JSONB input.

Focused coverage:

- `1001` TestRunner PASS cases.
- `15004` focused assertions.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson102OperatorPathStressTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102OperatorPathStressTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap:

- This is not metadata admission and does not add generated fake upstream
  script ids.
- It avoids accepted JSON table cursor/source/hidden/visible constraint work,
  JSON105 reverse-index mutation, JSON109 array insert, JSON103 aggregate/window,
  JSON104 patch, JSON106/108 invariants, JSON501/502 escaped-path work, and
  JSONB remove coverage.
- Existing operator matrix coverage is narrower and uses fixed upstream rows;
  this batch expands the same real upstream operator sections into dynamic
  full-path, quoted-label, subtype, and JSONB source combinations with one
  distinct TestRunner PASS case per row.

Dependency closure:

- No new support component is needed. The batch reuses existing native PHP
  JSONB, JSON path, JSON inspection, JSON extract, and SELECT expression
  helpers.
