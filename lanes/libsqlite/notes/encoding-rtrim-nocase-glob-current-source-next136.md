# encoding-rtrim-nocase-glob-current-source-next136

Slice: `encoding-rtrim-nocase-glob-current-source-next136`.

## Behavior

- Added `SQLiteRtrimNocaseGlobCurrentSourceNext136Plan` for encoded
  `wp_options.option_name` rows scanned through an expression index shaped like
  `rtrim(option_name) COLLATE NOCASE`.
- The seek range trims ASCII spaces and folds ASCII case, while the GLOB
  residual remains SQLite-style byte/case-sensitive.
- Current-to-next diagnostics report entered/exited matched rows, uppercase
  NOCASE false positives rejected by GLOB, UTF-8/UTF-16LE/UTF-16BE byte and
  encoding changes, malformed text repair/regression, schema/collation-source
  invalidation, and cursor reuse for stable sources.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRtrimNocaseGlobCurrentSourceNext136Test.php`
  - `1 test files, 89 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteRtrimNocaseGlobCurrentSourceNext136Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteRtrimNocaseGlobCurrentSourceNext136Test.php`
- `php -l lanes/libsqlite/examples/application-rtrim-nocase-glob-current-source-next136.php`
- `php lanes/libsqlite/examples/application-rtrim-nocase-glob-current-source-next136.php --self-test`
- `git diff --check -- lanes/libsqlite`

## Non-Overlap

This avoids accepted Unicode GLOB ranges, UTF-16 malformed insert guards,
plain RTRIM/NOCASE GLOB current-source next119, UTF-16 RTRIM/NOCASE equality
next132, cast LIKE/GLOB affinity slices, SQL SELECT text/order/group/subquery
clusters, JSON table cursor/source/constraint work, B-tree freeblock/freelist
clusters, and WAL/VFS transaction application. The new behavior is the
combined expression-index seek key `rtrim(...) COLLATE NOCASE` with a
byte-sensitive GLOB residual over encoded current/next sources.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP
UTF-8/UTF-16 text encode/decode, SQLite GLOB matching, and current-source
diagnostic helpers.
