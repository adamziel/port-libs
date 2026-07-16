# PRAGMA index_xinfo / foreign_key current-source next287-294

This slice keeps the PRAGMA/FK current-source lane moving after next279-286 by adding child-side foreign-key diagnostics that combine:

- `PRAGMA foreign_key_list` child-column declarations.
- `PRAGMA table_info` / `table_xinfo` child-column shape.
- `PRAGMA index_list` and `PRAGMA index_xinfo` child lookup coverage.

## Added pages

- next287: missing child column referenced by the FK definition.
- next288: generated child column used as an FK key.
- next289: nullable child column used with a SET NULL action.
- next290: child column without a default used with a SET DEFAULT action.
- next291: missing child lookup index.
- next292: only a partial child lookup index is available.
- next293: only an expression child lookup index is available.
- next294: child lookup index key order mismatches the FK child-column order.

Each page wraps the existing next286 chain, preserves resumable source hashes, exposes current/next count deltas, and keeps the new rows under the `foreign_key_child_key_diagnostic` kind.
