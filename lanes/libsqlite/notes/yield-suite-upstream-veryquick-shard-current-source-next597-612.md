# SQLite upstream veryquick shard current-source next597-612

This slice extends `SQLiteUpstreamSuiteEvidence` with direct next597 through
next612 veryquick-shard admission methods that all reuse the existing shared
current-source evidence mapper.

- Scope: bounded upstream veryquick shard evidence only.
- Non-overlap: follows accepted next581-596 suite evidence and keeps exact-shard
  next148, runner106/jsonvt104 rebase work, release/all parity, and live
  B-tree/JSON/VFS/WAL/planner/PRAGMA/ATTACH/window/VDBE behavior lanes out of
  this count.
- Countability: each next597-612 record admits exactly one newly mapped shard
  row while preserving the next581-596 anchor.

Validation targets:

```sh
php -l lanes/libsqlite/src/SQLiteUpstreamSuiteEvidence.php
php -l lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext597612Test.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext597612Test.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamVeryquickShardCurrentSourceNext581596Test.php
git diff --check
```
