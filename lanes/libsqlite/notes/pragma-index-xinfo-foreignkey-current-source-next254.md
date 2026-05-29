# PRAGMA index_xinfo/foreign_key nullable parent key current-source next254

- Slice: `pragma-index-xinfo-foreignkey-current-source-next254`
- Base accepted HEAD: `2d826f3672d51185a8fc82f12ed43afe26d2c9d6`
- Behavior: adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, which pages the existing current-source PRAGMA index_xinfo/foreign_key_list evidence together with nullable parent-key diagnostics. It compares explicit parent columns from `PRAGMA foreign_key_list` to exact non-partial UNIQUE parent indexes from `PRAGMA index_xinfo`, then decorates each parent-key column with `PRAGMA table_info` NOT NULL / primary-key visibility.
- WordPress smoke: `examples/wordpress-pragma-index-xinfo-foreignkey-nullable-parent-key-current-source-next254.php` models copied taxonomy import staging where nullable parent slug/taxonomy columns behind an exact UNIQUE parent index are blocked in the current catalog and repaired in the next catalog by adding `NOT NULL`.
- Non-overlap: avoids accepted/queued PRAGMA index_xinfo/FK surfaces for generated child action columns next253, expression child action indexes next251, generated parent/child columns next246/249/250, parent/child collation checks, MATCH clauses, SET NULL/SET DEFAULT action-column checks, rowid alias rejection, missing parent table diagnostics, and parent expression/partial/permuted/external unique-index diagnostics.
- Dependency closure: reuses existing lane-local `SQLitePragmaSchemaCatalog`, `SQLiteCreateTable`, and PRAGMA index/table/foreign-key metadata helpers. No new support component is needed.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php | tee /tmp/pragma254.out; printf 'PASS_LINES='; rg -c '^PASS ' /tmp/pragma254.out
1 test files, 64 assertions, 0 failures
PASS_LINES=54
```

Expected dashboard movement:

- `phpPass`: `+54` focused PASS lines when accepted.
- Mapped coverage: unchanged; this is focused PHP behavior coverage, not a new manifest-backed upstream row.
