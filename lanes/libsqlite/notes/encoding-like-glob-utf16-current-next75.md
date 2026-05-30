# encoding-like-glob-utf16-current-next75

- Behavior: adds `SQLiteUtf16LikeGlobCurrentNextCursor` for bounded UTF-16LE/UTF-16BE option-name index scans. It decodes UTF-16 text keys, rejects odd byte lengths and unpaired surrogate code units, applies SQLite-style LIKE/GLOB prefix range planning over decoded text, and keeps residual LIKE/GLOB matching separate from BINARY/NOCASE/RTRIM cursor ordering.
- Application smoke: `lanes/libsqlite/examples/application-option-name-utf16-like-glob-current-next75.php` scans copied `wp_options.option_name` UTF-16 bytes for escaped literal-percent LIKE predicates and supplementary-plane GLOB class predicates without requiring `ext/sqlite`.
- Non-overlap: avoids accepted LIKE current/next cursor ranges, accepted Unicode GLOB UTF-8 range behavior, accepted malformed UTF-16 record serialization guard, batch70 malformed text cursor work, and recent WAL/VFS/B-tree/JSON/SELECT clusters. This slice is only the UTF-16 decoded current/next LIKE/GLOB scan boundary.
- Dependency closure: no new support component is needed; the slice adds a small native PHP UTF-16 decoder/encoder local to libsqlite and reuses existing native LIKE/GLOB and LIKE collation planners.

Verification:

```text
$ php -l lanes/libsqlite/src/SQLiteUtf16LikeGlobCurrentNextCursor.php
No syntax errors detected in lanes/libsqlite/src/SQLiteUtf16LikeGlobCurrentNextCursor.php

$ php -l lanes/libsqlite/tests/SQLiteUtf16LikeGlobCurrentNext75Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteUtf16LikeGlobCurrentNext75Test.php

$ php -l lanes/libsqlite/examples/application-option-name-utf16-like-glob-current-next75.php
No syntax errors detected in lanes/libsqlite/examples/application-option-name-utf16-like-glob-current-next75.php

$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16LikeGlobCurrentNext75Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 52 assertions, 0 failures

$ php lanes/libsqlite/examples/application-option-name-utf16-like-glob-current-next75.php
```

Dashboard delta: expected `phpPass` +52 from focused PASS lines. Mapped coverage is unchanged; this is a focused PHP behavior slice over already mapped encoding/collation LIKE/GLOB surfaces, not a new upstream inventory row.
