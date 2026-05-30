# encoding-collation-source-current-next82

- Behavior: adds `SQLiteEncodingCollationSourceCursor`, a current-source LIKE/GLOB cursor boundary that decodes source text bytes according to SQLite database text encoding (`UTF-8`, `UTF-16LE`, `UTF-16BE`) before applying BINARY/NOCASE/RTRIM range and residual LIKE/GLOB semantics.
- Application smoke: `lanes/libsqlite/examples/application-option-name-encoding-source-current-next82.php` scans copied `wp_options.option_name` source bytes across UTF-8 and UTF-16 encodings for escaped literal-percent LIKE predicates, emoji prefixes, and GLOB prefixes without requiring `ext/sqlite`.
- Non-overlap: avoids accepted UTF-16 LIKE/GLOB cursor tests, Unicode GLOB range handling, malformed UTF-16 record insertion guards, LIKE current/next cursor ranges, JSON table source/cursor/constraint work, VFS/WAL transaction application, B-tree page/freelist clusters, and SELECT SQL text/group/order/subquery clusters. This slice is only the current-source text-encoding decode boundary feeding existing LIKE/GLOB collation semantics.
- Dependency closure: no new support component is needed; the slice adds a small native PHP source text decoder/encoder and reuses existing native LIKE/GLOB and collation planners.

Verification:

```text
$ php -l lanes/libsqlite/src/SQLiteEncodingCollationSourceCursor.php
No syntax errors detected in lanes/libsqlite/src/SQLiteEncodingCollationSourceCursor.php

$ php -l lanes/libsqlite/tests/SQLiteEncodingCollationSourceCursorNext82Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteEncodingCollationSourceCursorNext82Test.php

$ php -l lanes/libsqlite/examples/application-option-name-encoding-source-current-next82.php
No syntax errors detected in lanes/libsqlite/examples/application-option-name-encoding-source-current-next82.php

$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingCollationSourceCursorNext82Test.php
Focused test run: 1 selected test files (root lock skipped)
58 PASS lines
1 test files, 61 assertions, 0 failures

$ php lanes/libsqlite/examples/application-option-name-encoding-source-current-next82.php --self-test
application-option-name-encoding-source-current-next82 self-test passed
```

Dashboard delta: expected `phpPass` +58 from focused PASS lines (31014 -> 31072). Mapped coverage is unchanged; this is a focused PHP behavior slice over already mapped encoding/collation LIKE/GLOB inventory.
