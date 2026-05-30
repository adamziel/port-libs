# encoding-nocase-rtrim-like-current-source-next134

## Behavior

Adds `SQLiteNocaseRtrimLikeCurrentSourceNextPlan` for current-source `LIKE`
cursor invalidation when copied `wp_options.option_name` scans move between a
usable default `LIKE`/`NOCASE` prefix range and an unusable `RTRIM` collation
source that must be residual-scanned. The plan decodes UTF-8/UTF-16 text,
separates NOCASE range candidates from RTRIM full-scan candidates, preserves
SQLite's untrimmed LIKE residual semantics, reports malformed source rows, and
records retained source byte/encoding/collation-key changes.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNocaseRtrimLikeCurrentSourceNext134Test.php`
- Result: `1 test files, 80 assertions, 0 failures`
- New TestRunner PASS lines: `64`
- Application smoke: `php lanes/libsqlite/examples/application-nocase-rtrim-like-current-source-next134.php --self-test`

## Non-Overlap

This avoids accepted UTF-16 malformed record guards, Unicode GLOB ranges,
UTF-16 RTRIM LIKE next121 residual scans, RTRIM LIKE/GLOB operator switching,
RTRIM/NOCASE equality invalidation, CAST RTRIM/NOCASE slices, JSON/VFS/WAL/
B-tree/SQL executor clusters, and suite evidence work. The new surface is the
current-source handoff between default LIKE `NOCASE` range eligibility and
`RTRIM` full-scan fallback, including residual LIKE semantics that do not trim
trailing spaces.

## Dependency Closure

No new support component is needed. The slice composes existing native PHP
UTF-8/UTF-16 text decoding, LIKE pattern planning, NOCASE range comparison,
RTRIM collation keys, and current-source invalidation metadata.
