# SQLite UTF-16 NOCASE GLOB Affinity Current Source Next148

## Behavior

- Adds `SQLiteUtf16NocaseGlobAffinityCurrentSourceNextPlan` for WordPress-style `wp_options.option_name` scans where a NOCASE index/source cursor can use folded UTF-16 decoded prefix keys for candidate discovery.
- Preserves SQLite GLOB residual semantics as case-sensitive, so mixed-case rows can be candidates but still be rejected after TEXT affinity and UTF-16LE/UTF-16BE decoding.
- Tracks current/next source invalidation for malformed text, range byte encoding changes, candidate/matched rowsets, retained text/storage/encoding/byte changes, and stable cursor reuse.

## Evidence

- Focused test:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseGlobAffinityCurrentSourceNext148Test.php`
  Result: `1 test files, 98 assertions, 0 failures` with 78 PASS lines.
- WordPress smoke:
  `php lanes/libsqlite/examples/wordpress-utf16-nocase-glob-affinity-current-source-next148.php`
  Expected: JSON reports NOCASE candidates, case-sensitive GLOB residual rejects, entered rowids, and dependency closure.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

- Avoids accepted Unicode GLOB range handling, UTF-16 malformed insert guards, UTF-16 RTRIM LIKE/GLOB affinity, accepted LIKE/GLOB range-only slices, and batch143 RTRIM LIKE/GLOB coverage.
- This patch specifically covers NOCASE source cursor behavior for GLOB with case-sensitive residual matching and TEXT affinity over current/next UTF-16 sources.

## Dependency Closure

No new support component is needed. The slice reuses native UTF-16 decoding, existing GLOB prefix range logic, NOCASE folded cursor keys, and `SQLiteDatabase::globMatches()` residual matching.
