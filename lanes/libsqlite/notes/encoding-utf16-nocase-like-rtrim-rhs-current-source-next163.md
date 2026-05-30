# encoding-utf16-nocase-like-rtrim-rhs-current-source-next163

Status: focused PHP behavior growth for UTF-16 `NOCASE` `LIKE` scans where the RHS is a `rtrim(pattern)` expression and the indexed key is `rtrim(option_name)`.

Application path: `application-utf16-nocase-like-rtrim-rhs-current-source-next163.php` models copied `wp_options.option_name` rows scanned by a plugin-prefix pattern whose bound UTF-16 RHS value contains trailing spaces. The plan trims only ASCII spaces from the RHS expression, preserves tabs as literal LIKE prefix text, keeps ASCII-only NOCASE behavior, reports current/next pattern byte changes, and isolates malformed UTF-16 row payloads.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimRhsCurrentSourceNext163Test.php`
  - `1 test files, 84 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-rhs-current-source-next163.php --self-test`
  - `application-utf16-nocase-like-rtrim-rhs-current-source-next163 self-test passed`

Expected dashboard movement: `phpPass +73`, from `72664` to `72737` (`84` raw assertions). Mapped upstream coverage remains `609 / 1589`; this is current-source PHP behavior over already mapped UTF-16, NOCASE LIKE, and RTRIM expression inventory rather than a fresh upstream manifest row.

Non-overlap: avoids accepted next156/158 UTF-16 row-side `NOCASE LIKE`/`RTRIM` scans, next159/160 UTF-16 pattern-byte current-source handoffs, next141 malformed-row isolation, next103/132 RTRIM/NOCASE equality scans, Unicode GLOB ranges, VFS/WAL/B-tree/JSON/SQL executor clusters, and suite-runner evidence work. The new surface is specifically RHS `rtrim(pattern)` expression semantics feeding a UTF-16 NOCASE LIKE/RTRIM current-source cursor.

Dependency closure: no new support component is needed. The slice reuses lane-local UTF-16 decode/encode helpers, RHS RTRIM expression trimming, NOCASE LIKE range planning, and current-source invalidation diagnostics.

Next task: continue encoding work only on a non-overlapping collation, affinity, LIKE/GLOB, or malformed-text comparison edge with focused tests.
