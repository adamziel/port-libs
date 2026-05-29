# encoding-utf16-rtrim-like-current-source-next121

## Behavior

Adds `SQLiteUtf16RtrimLikeCurrentSourceNextPlan`, a current/next residual
scan for copied WordPress `wp_options.option_name` rows when a `LIKE` predicate
is associated with an `RTRIM` index collation. The existing source cursor keeps
reporting that the `RTRIM` index range is not usable for `LIKE`; this slice
adds the fallback behavior that decodes UTF-8/UTF-16LE/UTF-16BE record text,
applies SQLite `LIKE` matching without trimming trailing spaces, reports
malformed text rows, and explains current/next invalidation reasons.

## Evidence

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16RtrimLikeCurrentSourceNext121Test.php
```

WordPress smoke:

```text
php lanes/libsqlite/examples/wordpress-utf16-rtrim-like-current-source-next121.php --self-test
```

Additional required checks:

```text
php -l lanes/libsqlite/src/SQLiteUtf16RtrimLikeCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteUtf16RtrimLikeCurrentSourceNext121Test.php
php -l lanes/libsqlite/examples/wordpress-utf16-rtrim-like-current-source-next121.php
git diff --check -- lanes/libsqlite
```

## Non-overlap

This does not repeat accepted Unicode GLOB range behavior, UTF-16 malformed
record guards, UTF-16 pattern decoding, or RTRIM equality/NOCASE comparison
coverage. It keeps the accepted “RTRIM LIKE has no usable range” decision and
adds only the residual full-scan current/next behavior for UTF-16 source rows.

## Dependency Closure

No new support component is needed. The slice reuses existing lane-local UTF
encoding/decoding, LIKE matching, and collation planning primitives.
