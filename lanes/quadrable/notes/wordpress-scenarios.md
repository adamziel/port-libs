# quadrable WordPress Scenario

Authenticated local-first state sync for Playground snapshots and content databases.

## Current Native Slice

Pure-PHP BLAKE2s-256 hash/key primitives plus an in-memory sparse tree for get/put/delete, update batching, path-independent roots, `getMulti`, empty-head restoration, delete bubbling equivalence, ordered raw integer keys for WordPress option/post records, an exact upstream-digest snapshot root, iterator windows for incremental sync chunks, compact authenticated range proofs for partial snapshot verification, proof-backed partial-tree updates for narrow content changes, merged partial proofs for independently requested records, and bounded sync proof fragments with diff reconstruction.

## Fixture And Example

- `fixtures/wordpress-ordered-snapshot.json` stores ordered `wp_options`, `wp_posts`, and `wp_postmeta` records under raw integer keys.
- `examples/wordpress-iterator-window.php` loads the fixture and emits a three-record window beginning at key 2, modeling a bounded Playground snapshot sync page.
- `examples/wordpress-proof-range.php` exports and imports a compact range proof for keys 2 through 4, then reads the authenticated partial tree using only the trusted root and encoded proof bytes.
- `examples/wordpress-proof-update.php` exports a narrow proof for a single `wp_posts` record, applies an authenticated update to the imported partial tree, and verifies that the partial update root matches the full snapshot update root.
- `examples/wordpress-proof-merge.php` imports one proof for `siteurl`, merges a second proof for a post record with the same trusted root, and reads both records from the expanded authenticated partial tree.
- `examples/wordpress-sync-diff.php` uses upstream-shaped sync request/response transport round trips to fetch bounded proof fragments from a changed snapshot, diff the authenticated shadow tree, and reconstruct updated/deleted/added WordPress records locally.

## Next Task

Broaden sync fuzz parity with deterministic randomized multi-round sync cases, scan/diff equivalence, and persisted LMDB node-id behavior.
