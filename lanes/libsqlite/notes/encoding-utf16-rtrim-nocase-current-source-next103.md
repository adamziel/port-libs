# Encoding UTF-16 RTRIM NOCASE current-source next103

Status: focused PHP behavior growth for Application `wp_options.option_name`
comparisons using `rtrim(option_name) COLLATE NOCASE` across UTF-8,
UTF-16LE, and UTF-16BE current/next sources.

Behavior:

- Added `SQLiteUtf16RtrimNocaseCurrentSourceNextPlan` to decode mixed text
  encodings, apply SQLite's space-only `rtrim()` expression, then apply
  ASCII-only `NOCASE` comparison keys for current/next matched rowsets.
- The focused test covers UTF-16LE/BE byte preservation, source-cookie
  invalidation, repaired/new malformed rows, ASCII-only case folding,
  non-trimmed tab and NBSP suffixes, emoji and non-ASCII case boundaries, and
  stable-source no-reprepare behavior.
- Added a Application smoke at
  `lanes/libsqlite/examples/application-utf16-rtrim-nocase-current-source-next103.php`.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16RtrimNocaseCurrentSourceNext103Test.php`
- `php -l lanes/libsqlite/src/SQLiteUtf16RtrimNocaseCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteUtf16RtrimNocaseCurrentSourceNext103Test.php`
- `php -l lanes/libsqlite/examples/application-utf16-rtrim-nocase-current-source-next103.php`
- `php lanes/libsqlite/examples/application-utf16-rtrim-nocase-current-source-next103.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dashboard delta:

- `phpPass`: `39474 -> 39527` from 53 newly passing focused PASS lines in
  `SQLiteUtf16RtrimNocaseCurrentSourceNext103Test.php`.
- `benchmarkDenominator.mapped`: unchanged at `587 / 1589`; this is focused
  current-source PHP behavior over existing encoding/collation inventory.

Dependency closure:

No new support component is needed. This reuses native PHP UTF-16 encoding
helpers and adds a lane-local planner for SQLite `rtrim()` plus `NOCASE`
current/next comparison semantics.

Non-overlap:

Avoids accepted Unicode GLOB ranges, malformed UTF-16 LIKE/GLOB range scans,
dynamic affinity LIKE behavior, expression-index collation cursor behavior,
UTF-16 insert guards, JSON/VFS/WAL/B-tree clusters, and SQL expression ORDER
BY. The new surface is mixed UTF-16 current-source invalidation for
`rtrim(option_name) COLLATE NOCASE` equality comparisons.
