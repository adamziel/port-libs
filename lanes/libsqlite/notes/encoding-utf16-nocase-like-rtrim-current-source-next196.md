# encoding-utf16-nocase-like-rtrim-current-source-next196

Status: focused PHP behavior growth for UTF-16 NOCASE LIKE/RTRIM current-source scans with duplicate comparison-key peer resume safety.

Application path: `application-utf16-nocase-like-rtrim-current-source-next196.php` models a copied `wp_options.option_name` scan where UTF-16LE, UTF-16BE, and UTF-8 rows collapse to the same `RTRIM(option_name) COLLATE NOCASE` key. A yielded scan token can resume only when duplicate-key peers before the token are unchanged; inserted or changed peers require replay from the range start so duplicated option-name rows are not skipped.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext196Test.php`
  - `1 test files, 82 assertions, 0 failures`

Expected dashboard movement: `phpPass +82`, from `94386` to `94468`. Mapped upstream coverage remains `618 / 1589`; this is focused current-source PHP behavior over already mapped encoding, collation, LIKE, and RTRIM inventory rather than a fresh upstream manifest row.

Non-overlap: avoids accepted batch179 next192 UTF-16 NOCASE/LIKE/RTRIM candidate-token false-positive replay, next191 prepared pattern rebind, next183 prefix reuse, malformed UTF-16 guards, Unicode GLOB ranges, and VFS/WAL/B-tree/JSON/SQL executor clusters. The new surface is duplicate comparison-key peer safety for yielded current/next scans.

Dependency closure: no new support component is needed. The slice reuses native PHP UTF-16 decode/encode helpers, LIKE NOCASE prefix range planning, RTRIM expression keys, and residual LIKE rechecks.

Next task: continue encoding work only on a non-overlapping malformed-text, collation, affinity, or LIKE/GLOB edge with focused tests.
