# PRAGMA index_xinfo foreign-key current-source next244

Slice: `pragma-index-xinfo-foreignkey-current-source-next244`

Behavior:

- Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext244`, layered on the
  accepted next241 current-source page.
- Appends current/next diagnostic rows for foreign keys whose parent columns
  are backed only by a UNIQUE parent index that includes expression key rows in
  `PRAGMA index_xinfo`.
- Marks expression-bearing UNIQUE indexes as unusable FK parent-key candidates
  when their key arity matches the referenced parent columns but one or more
  key terms are expressions (`cid = -2` / `name = NULL`).
- Keeps unrelated expression UNIQUE indexes visible without treating them as
  blockers, and ignores partial expression indexes.

WordPress relevance:

Copied taxonomy/import schemas may contain helper indexes such as
`UNIQUE(site_id, lower(slug))`. SQLite must not admit that expression index as
the parent key for `FOREIGN KEY(site_id, slug) REFERENCES parent(site_id, slug)`;
the copied schema needs an exact non-expression UNIQUE parent index before FK
repair can proceed.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext244Test.php
1 test files, 66 assertions, 0 failures
```

Dependency closure:

No new support component is required. This reuses the existing
`SQLitePragmaSchemaCatalog`, `PRAGMA index_list`, `PRAGMA index_xinfo`, and
`PRAGMA foreign_key_list` catalog helpers.

Non-overlap:

Avoids accepted next240/next241 implicit parent-column resolution, next181
collation mismatch, next188 partial unique parent-index handling, next218
RESTRICT timing, and next239 auxiliary `key=0` parent-index diagnostics. This
slice is specifically about expression key rows in a UNIQUE parent index.
