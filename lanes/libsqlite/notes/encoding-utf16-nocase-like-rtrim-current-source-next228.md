# UTF-16 NOCASE LIKE RTRIM current-source next228

## Behavior

Adds `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan`, a bounded current-source diagnostic for prepared Application `wp_options` scans shaped like:

```sql
rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ?
```

The plan builds on accepted next211 byte-order-only refresh handling. It proves the narrower SQLite edge where decoded rows, RTRIM keys, NOCASE keys, and LIKE residual rowsets are stable across a UTF-16LE to UTF-16BE source refresh, but the database text-encoding header and prepared statement encoding no longer match. The logical rowset can be retained for diagnostics, while the prepared cursor must be invalidated and reprepared.

## Evidence

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext228Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 69 assertions, 0 failures
56 PASS lines
```

Application smoke:

```text
php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next228.php
application-utf16-nocase-like-rtrim-current-source-next228 self-test passed
```

## Non-Overlap

This next228 slice adds a database text-encoding header/prepared-statement fence. It avoids next224 keyset resume, next223 DESC LIMIT windows, next221 prepared byte signatures, next208 ESCAPE decoding, next211 rowset source-refresh diagnostics, Unicode GLOB ranges, UTF-16 malformed insert guards, and non-encoding SQL/JSON/WAL/B-tree/VFS clusters.

## Dependency Closure

No new support component is needed. The slice reuses native UTF-16 decode, accepted NOCASE/RTRIM LIKE residual behavior, and adds only a bounded database text-encoding header fence for prepared cursor reuse.
