# encoding-collation-rtrim-glob-current-source-next125

Status: focused PHP behavior growth for mixed UTF-8/UTF-16 `GLOB` scans over
`wp_options.option_name` with an `RTRIM` index key at the current-source/next
boundary.

Behavior:

- Adds `SQLiteUtf16RtrimGlobCurrentSourceNextPlan::wordpressOptionNamePlan()`.
- Decodes per-row UTF-8, UTF-16LE, and UTF-16BE option-name bytes.
- Uses SQLite GLOB prefix bounds with `RTRIM` comparison for range candidates,
  then applies exact GLOB residual matching so trailing ASCII spaces, tabs,
  newlines, and case remain significant to the operator.
- Reports current/next candidate rowids, residual-rejected rowids, retained /
  entered / exited matched rowids, malformed text rowids, byte/encoding/text
  changes, and cursor invalidation reasons.

WordPress smoke:

- `lanes/libsqlite/examples/wordpress-utf16-rtrim-glob-current-source-next125.php --self-test`

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16RtrimGlobCurrentSourceNext125Test.php`
- Result: `1 test files, 74 assertions, 0 failures`
- Dashboard delta: expected `phpPass` +74, from `49426` to `49500`.
- Mapped upstream coverage unchanged; this is focused behavior over already
  mapped encoding/collation/GLOB current-source surfaces.

Non-overlap:

- Avoids accepted Unicode GLOB range handling, UTF-16 malformed record guards,
  UTF-16 RTRIM/LIKE current-source next121, UTF-16 GLOB range next102,
  encoding affinity/collation predicate next109, JSON/VFS/WAL/B-tree current
  source clusters, and SELECT SQL executor clusters.
- The new surface is specifically mixed-encoding `RTRIM` range admission plus
  exact `GLOB` residual rejection at the current-source/next125 boundary.

Dependency closure:

- No new support component is needed. The slice reuses existing native PHP text
  decoding, GLOB prefix/range helpers, and GLOB residual matching.
