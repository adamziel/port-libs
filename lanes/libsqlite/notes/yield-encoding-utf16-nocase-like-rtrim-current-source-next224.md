# UTF-16 NOCASE LIKE RTRIM current-source next224

## Behavior

Adds `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan`, a bounded current-source diagnostic for resumed Application `wp_options` scans shaped like:

```sql
rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ?
AND (rtrim(option_name) COLLATE NOCASE, rowid) > (?, ?)
ORDER BY rtrim(option_name) COLLATE NOCASE, rowid
LIMIT ?
```

The plan decodes UTF-16 prepared ESCAPE bytes, applies the accepted LIKE prefix range and RTRIM/NOCASE residual match, orders by the SQLite keyset `(RTRIM NOCASE key, rowid)`, and compares current vs next source rows before and after the saved resume key. It reports resume-prefix, resume-page, and resume-tail invalidation reasons so copied option-name cursors do not skip newly inserted rows after a source refresh.

## Evidence

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext224Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 87 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next224.php
application-utf16-nocase-like-rtrim-current-source-next224 self-test passed
```

## Non-Overlap

This next224 slice adds keyset resume fencing after a saved `(rtrim-nocase-key,rowid)` tail. It avoids accepted next218 LIMIT/OFFSET yield-window fencing, next208 prepared ESCAPE decoding, next203 no-prefix full scans, next200 escape rebinding, next191 prepared pattern rebinds, next206 BOM pattern normalization, Unicode GLOB range handling, and UTF-16 malformed insert guards.

## Dependency Closure

No new support component is needed. The slice reuses native UTF-16 decode, prepared LIKE ESCAPE handling, RTRIM/NOCASE keys, residual matching, and current-source cursor diagnostics.
