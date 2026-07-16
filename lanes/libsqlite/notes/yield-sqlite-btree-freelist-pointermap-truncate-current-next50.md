# B-tree freelist pointer-map truncate current-next50

This slice extends incremental freelist tail truncation for auto-vacuum
databases with pointer-map evidence. `SQLiteFreelistTruncatePlan` now reports
the current/next free-page pointer-map entries that become unreachable after
tail truncation, plus the surviving boundary page's pointer-map entry after
the freelist trunk is rewritten.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteFreelistPointerMapTruncateCurrentNext50Test.php`
  - `1 test files, 50 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-freelist-pointermap-truncate-current-next50.php`
  - reports copied `wp_options` transient cleanup truncating tail pages
    `[12, 11, 10, 9]`, preserving freelist trunk page `5`, and keeping page
    `8` as the next live B-tree pointer-map boundary.
- `php -l` passed for changed PHP files.
- `git diff --check -- lanes/libsqlite` passed.

Non-overlap:

- Avoids accepted overflow freelist release, bulk overflow freeblock
  materialization, root-collapse/page-move pointer-map rewrites, PRAGMA
  integrity pointer-map diagnostics, VFS/WAL transaction application, JSON
  table source/cursor/constraint work, SELECT SQL text/subquery/group/order
  clusters, and Unicode GLOB behavior.
- The new surface is specifically the current/next pointer-map evidence around
  freelist tail truncation after obsolete tail pages have already reached the
  freelist.

Dependency closure:

- No new support component is needed. The patch reuses native PHP SQLite
  header parsing, freelist trunk parsing/assembly, auto-vacuum pointer-map
  decoding, and page-image truncation planning.
