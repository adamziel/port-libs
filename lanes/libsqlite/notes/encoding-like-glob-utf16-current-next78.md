# encoding-like-glob-utf16-current-next78

Status: focused PHP behavior growth for UTF-16 database text keys feeding
SQLite-style GLOB current/next scans.

This slice adds `SQLiteUtf16GlobCurrentNextCursor`, an additive wrapper around
the existing native GLOB cursor. It decodes UTF-16LE/UTF-16BE option-name bytes
before current/next range comparison, preserves endian-specific key evidence,
and reuses the existing residual GLOB matcher for BINARY/NOCASE/RTRIM scans.

Application smoke:

```sh
php lanes/libsqlite/examples/application-option-name-utf16-glob-current-next78.php --self-test
```

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16GlobCurrentNext78Test.php
# 1 test files, 55 assertions, 0 failures
```

Non-overlap: avoids accepted Unicode GLOB range matching, malformed UTF-8 GLOB
byte planning, UTF-16 record encoding guards, LIKE current/next ranges, JSON
table cursor/source/constraint work, and current VFS/WAL/B-tree apply clusters.
The new behavior is specifically GLOB current/next scanning when copied
Application `wp_options.option_name` keys originate from UTF-16 database text
records.

Dependency closure: no new support component is needed; this reuses existing
native PHP GLOB cursor and matcher behavior with a bounded UTF-16 decoder.
