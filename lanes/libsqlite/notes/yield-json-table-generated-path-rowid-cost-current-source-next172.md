# JSON Table Generated Path Rowid Cost Current-Source Next172

Status: focused PHP behavior growth for current-source `json_tree()` generated-path rowid cursor fencing.

Behavior:

- Uses the consolidated `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidSourceFence()` entry point.
- Builds on the accepted next166 generated-path rowid yield profile, but adds a current-source fence token and stable yield key derived from the current JSON source fingerprint, generated path, rowid/path rowset, and yield decision.
- A pinned current-source cursor can keep yielding rowid `[6]` while a changed next source gets `sourceResetRequired=true`, `staleYieldBlocked=true`, and a distinct fence token.
- Residual/non-pinned rowid scans preserve rowset evidence but require a reset instead of advertising stale current-source reuse.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext172Test.php
```

Application smoke:

```sh
php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next172.php --self-test
```

Non-overlap: this does not repeat accepted next166 generated-path rowid yield admission, next165 rowid seek costing, next163 xBestIndex admission, visible/hidden JSON constraints, JSON table SELECT source/cursor wiring, host joins, or JSON generated ordering. The new behavior is specifically current-source fence-token/reset accounting for generated-path rowid yield reuse across current/next source changes.

Dependency closure: no new support component is needed; this reuses native JSON table current-source yield, generated path, rowid, and xBestIndex profiles.
