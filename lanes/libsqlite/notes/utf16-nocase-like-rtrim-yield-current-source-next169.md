# UTF-16 NOCASE LIKE RTRIM Yield Current Source Next169

## Behavior

This slice adds `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan`, a bounded yield-page plan for copied `wp_options` scans over:

```sql
rtrim(option_name) COLLATE NOCASE LIKE ?
```

The plan reuses the accepted UTF-16 pattern normalization and next165 resume-token behavior, then adds page-size yield decisions, high-water token generation, duplicate-yield detection, and restart diagnostics when the current source changes underneath a yielded cursor.

## Evidence

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext169Test.php
1 test files, 96 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-yield-current-source-next169.php --self-test
wordpress-utf16-nocase-like-rtrim-yield-current-source-next169 self-test passed
```

## Non-Overlap

This does not repeat accepted UTF-16 malformed text guards, Unicode GLOB ranges, next156/next163/next165 NOCASE/RTRIM rowset and resume-token diagnostics, or batch157 UTF-16 NOCASE/LIKE/RTRIM current-source coverage. The new behavior is the bounded yield page/high-water-token layer used after a resumable cursor has already been planned.

## Dependency Closure

No new support component is needed. The slice reuses native PHP UTF-16 decoding, pattern normalization, ASCII NOCASE LIKE matching, RTRIM expression keys, and current-source resume diagnostics already present in the lane.
