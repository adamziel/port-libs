# UTF-16 NOCASE LIKE RTRIM current-source next208

This slice adds `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan`, covering
prepared UTF-16 `ESCAPE` parameter bytes for
`rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ?` current-source cursors.

Focused behavior:

- decodes UTF-16LE/UTF-16BE/UTF-8 prepared ESCAPE bytes before LIKE prefix
  planning;
- strips a leading UTF BOM before SQLite's single-character ESCAPE validation;
- rewrites the prepared pattern template to the decoded escape character so
  prefix and residual matching stay aligned across current/next sources;
- invalidates stale cursors on source/cookie, escape bytes, BOM, malformed row
  text, and matched-rowset changes;
- preserves the stable-source reuse path when decoded escape bytes and sources
  are unchanged.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext208Test.php
php -l lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next208.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext208Test.php
php lanes/libsqlite/examples/wordpress-utf16-nocase-like-rtrim-current-source-next208.php
git diff --check -- lanes/libsqlite
```

Focused result: `1 test files, 87 assertions, 0 failures`, with `77` PASS
lines.

Dependency closure: no new support component is needed. The slice reuses native
UTF-16 decode, prepared LIKE ESCAPE byte normalization, LIKE ESCAPE prefix
planning, RTRIM keys, and current-source cursor diagnostics.

Non-overlap: this covers prepared UTF-16 ESCAPE parameter decoding and BOM
stripping before NOCASE/RTRIM LIKE range planning. It avoids accepted
prepared-pattern BOM next206, escape rebind next200, no-prefix next203, escaped
literal next194/195, Unicode GLOB ranges, and malformed UTF-16 insert guards.
