# encoding-collation-rtrim-nocase-glob-current-source-next119

Status: focused PHP behavior growth for current/next GLOB range scans over copied Application option-name rows with BINARY, NOCASE, and RTRIM index collations.

Behavior:

- Added `SQLiteRtrimNocaseGlobCurrentSourceNext119Plan`.
- Models SQLite-style GLOB prefix range candidates under BINARY/NOCASE/RTRIM index ordering while keeping the GLOB residual byte/case-sensitive.
- Records current/next candidate rowsets, residual matches, false-positive rowids introduced by NOCASE/RTRIM traversal, malformed UTF-8 row visibility, and cursor invalidation reasons.
- Adds a Application smoke for copied `wp_options.option_name` scans where `Plugin_*` and `PLUGIN_*` are NOCASE index candidates but not GLOB residual matches, and where RTRIM range traversal admits space-padded false positives for an exact GLOB probe.

Non-overlap:

- Avoids accepted Unicode GLOB range matching, UTF-16 malformed record guards, UTF-16/RTRIM current-source cursor handoffs, SELECT predicate LIKE/GLOB affinity, VFS/WAL/B-tree/JSON/source clusters, and accepted SQL expression ORDER/GROUP/subquery behavior.
- The new surface is current/next source invalidation for NOCASE/RTRIM index candidates with byte-sensitive GLOB residual filtering.

Dependency closure:

- No new support component is needed. This slice reuses native PHP `SQLiteDatabase::globPrefixRangeBounds()` and `SQLiteDatabase::globMatches()` and adds only lane-local current/next planning logic.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRtrimNocaseGlobCurrentSourceNext119Test.php`
  - `1 test files, 59 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-rtrim-nocase-glob-current-source-next119.php --self-test`
  - `application-rtrim-nocase-glob-current-source-next119 self-test passed`

Dashboard delta:

- Expected `phpPass` +59 from focused PASS lines, from `45302` to `45361`.
- Mapped upstream coverage remains `604 / 1589`; this is focused PHP behavior over already mapped encoding/collation/GLOB inventory.
