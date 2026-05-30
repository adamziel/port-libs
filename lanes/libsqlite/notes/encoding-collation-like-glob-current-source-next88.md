# encoding-collation-like-glob-current-source-next88

Status: focused PHP behavior growth for LIKE/GLOB prepared cursor reuse across current and next Application option-name sources.

Behavior:
- Adds `SQLiteLikeGlobCurrentSourceNextPlan::optionRowNameStatement()` for copied `wp_options.option_name` LIKE/GLOB scans.
- Compares current and next prepared statement metadata: source fingerprint, operator, pattern, collation, ESCAPE, and `case_sensitive_like`.
- Reuses existing UTF-8/UTF-16 source cursors to compute current/next rowsets, byte/encoding changes, retained/exited/entered rowids, and reprepare reasons.

Focused verification:
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteLikeGlobCurrentSourceNext88Test.php`
- Result: `1 test files, 54 assertions, 0 failures`.
- `php -l lanes/libsqlite/src/SQLiteLikeGlobCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteLikeGlobCurrentSourceNext88Test.php`
- `php -l lanes/libsqlite/examples/application-option-name-like-glob-current-source-next88.php`
- `php lanes/libsqlite/examples/application-option-name-like-glob-current-source-next88.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dashboard delta:
- `phpPass`: `34288 -> 34342` for 54 newly verified focused PASS lines.
- mapped upstream coverage unchanged; this is a focused behavior slice, not a new upstream inventory unit.

Non-overlap:
- Avoids accepted Unicode GLOB range handling, UTF-16 malformed record guards, LIKE current/next cursor ranges, batch82 encoding source cursors, batch86 source-switch coverage, SELECT predicate affinity/collation metadata, JSON table residual LIKE/GLOB, and VFS/WAL/B-tree current-source clusters.
- This slice specifically guards prepared LIKE/GLOB cursor reuse when statement metadata or current-source bytes change.

Dependency closure:
- No new support component is needed. The slice reuses existing native PHP UTF-8/UTF-16 text encoding, LIKE/GLOB matching, collation range planning, and current/next source cursor components.
