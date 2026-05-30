# vfs-lock-byte-uri-shm-current-source-next93

This slice adds `SQLiteVfsLockByteUriShmCurrentSourceNext`, a bounded native
PHP planner for current-source VFS transitions that normalize SQLite `file:`
URI database identity before coordinating main database lock bytes with the
matching WAL `-shm` sidecar lock source.

Focused behavior:

- Percent-decoded `file:` URI spellings for the same Application database reuse
  one current-source entry, while the `-shm` sidecar key remains derived from
  the decoded database filename.
- Main database lock-byte transitions and SHM read/write/checkpoint locks are
  coordinated in the same source snapshot, so shared readers block main
  `exclusive` until release while SHM conflicts remain independent.
- `mode=ro`, `immutable=1`, `nolock=1`, and `mode=memory` URI inputs produce
  bounded SQLite-style blockers for writer byte locks, SHM exclusive locks, or
  private in-memory lock state.
- The Application smoke covers a copied `wp-content/database/wp copy.sqlite`
  import where a reader holds shared byte and SHM locks until an importer
  reaches exclusive and checkpoint ownership after release.

Verification:

- `php -l lanes/libsqlite/src/SQLiteVfsLockByteUriShmCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLiteVfsLockByteUriShmCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/application-vfs-lock-byte-uri-shm-current-source-next93.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsLockByteUriShmCurrentSourceNextTest.php`
  - `1 test files, 64 assertions, 0 failures`
  - `64` PASS lines
- `php lanes/libsqlite/examples/application-vfs-lock-byte-uri-shm-current-source-next93.php --self-test`
- `git diff --check -- lanes/libsqlite`

Non-overlap:

This does not repeat accepted URI/SHM file-control current-source `next88`,
WAL/SHM lock-byte current-source `next89`, lock byte-range constants, VFS
lock-state/process-lock/locked-writer behavior, VFS sync/apply, WAL byte
truncation, WAL checkpoint transactions, or rollback-journal apply. The new
surface is decoded file URI source identity across main lock bytes and the
derived WAL SHM sidecar source.

Dependency closure:

No new support component is required. The slice reuses existing lane-local
`SQLiteFileUri` parsing and `SQLiteLockByteRangePlan` transition semantics,
adding only a bounded current-source coordinator needed by later pager/VFS WAL
transaction application.
