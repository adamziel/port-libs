# PRAGMA index_xinfo foreign-key current-source next275-278

This slice extends the current-source PRAGMA/FK page family after next259-262
with FK action diagnostics derived from `PRAGMA foreign_key_list`, child
`PRAGMA table_info`, `PRAGMA index_list`, and `PRAGMA index_xinfo`.

- next275 reports CASCADE actions whose child key no longer has an ok leftmost
  lookup index.
- next276 reports SET NULL actions on NOT NULL child key columns.
- next277 reports SET DEFAULT actions where a child key column has no default.
- next278 publishes RESTRICT/NO ACTION action rows as non-blocking evidence.

The page wrappers layer over the accepted page262 implementation and keep
current/next source hashes, summaries, counts, deltas, resume cursors, and
dependencies consistent with the earlier PRAGMA slices.
