# quadrable WordPress Scenario

Authenticated local-first state sync for Playground snapshots and content databases.

## Current Native Slice

Native hash/key primitives plus an in-memory sparse tree for get/put/delete, update batching, path-independent roots, `getMulti`, empty-head restoration, delete bubbling equivalence, ordered raw integer keys for WordPress option/post records, iterator windows for incremental sync chunks, and compact authenticated range proofs for partial snapshot verification.

## Fixture And Example

- `fixtures/wordpress-ordered-snapshot.json` stores ordered `wp_options`, `wp_posts`, and `wp_postmeta` records under raw integer keys.
- `examples/wordpress-iterator-window.php` loads the fixture and emits a three-record window beginning at key 2, modeling a bounded Playground snapshot sync page.
- `examples/wordpress-proof-range.php` exports and imports a compact range proof for keys 2 through 4, then reads the authenticated partial tree using only the trusted root and encoded proof bytes.

## Next Task

Port proof-backed update/merge semantics from upstream `update proof` scenarios, including witness-leaf mutation guards and partial-tree `mergeProof` expansion.
