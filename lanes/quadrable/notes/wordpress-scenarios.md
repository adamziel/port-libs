# quadrable WordPress Scenario

Authenticated local-first state sync for Playground snapshots and content databases.

## Current Native Slice

Native hash/key primitives plus an in-memory sparse tree for get/put/delete, update batching, path-independent roots, `getMulti`, empty-head restoration, delete bubbling equivalence, ordered raw integer keys for WordPress option/post records, iterator windows for incremental sync chunks, compact authenticated range proofs for partial snapshot verification, proof-backed partial-tree updates for narrow content changes, and merged partial proofs for independently requested records.

## Fixture And Example

- `fixtures/wordpress-ordered-snapshot.json` stores ordered `wp_options`, `wp_posts`, and `wp_postmeta` records under raw integer keys.
- `examples/wordpress-iterator-window.php` loads the fixture and emits a three-record window beginning at key 2, modeling a bounded Playground snapshot sync page.
- `examples/wordpress-proof-range.php` exports and imports a compact range proof for keys 2 through 4, then reads the authenticated partial tree using only the trusted root and encoded proof bytes.
- `examples/wordpress-proof-update.php` exports a narrow proof for a single `wp_posts` record, applies an authenticated update to the imported partial tree, and verifies that the partial update root matches the full snapshot update root.
- `examples/wordpress-proof-merge.php` imports one proof for `siteurl`, merges a second proof for a post record with the same trusted root, and reads both records from the expanded authenticated partial tree.

## Next Task

Port sync request/response proof fragments and diff reconstruction from upstream `sync fuzz` scenarios for multi-client Playground state sync.
