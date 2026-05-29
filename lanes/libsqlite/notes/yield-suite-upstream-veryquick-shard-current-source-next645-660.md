# SQLite upstream veryquick shard current-source next645-660

This slice extends `SQLiteUpstreamSuiteEvidence` with direct next645 through
next660 veryquick-shard admission methods that all reuse the existing shared
current-source evidence mapper.

- Scope: bounded upstream veryquick shard evidence only.
- Non-overlap: follows accepted next629-644 suite evidence and keeps exact-shard
  next148, runner106/jsonvt104 rebase work, release/all parity, and live
  B-tree/JSON/VFS/WAL/planner/PRAGMA/ATTACH/window/VDBE behavior lanes out of
  this count.
- Countability: each next645-660 record admits exactly one newly mapped shard
  row while preserving the next629-644 anchor.

Validation targets:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext645660Test.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext629644Test.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext645660Test.php
git diff --check
```
