# quadrable WordPress Scenario

Authenticated local-first state sync for Playground snapshots and content databases.

## Current Native Slice

Pure-PHP BLAKE2s-256 hash/key primitives plus an in-memory sparse tree for get/put/delete, update batching, path-independent roots, `getMulti`, empty-head restoration, delete bubbling equivalence, ordered raw integer keys for WordPress option/post records, an exact upstream-digest snapshot root, iterator windows for incremental sync chunks, compact authenticated range proofs for partial snapshot verification, proof-backed partial-tree updates for narrow content changes, merged partial proofs for independently requested records, bounded sync proof fragments with diff reconstruction, scan-time diff callbacks that match the final authenticated diff, tracked leaf node-id reuse for compact rebuilds of unchanged snapshot records, saved branch-head checkout for old/new snapshot forks, tracked scan/final diffs that report identical leaf node ids for changed/deleted/added records, memStore-range detached overlays for volatile preview edits, and named published heads that reject volatile memStore writes until a preview fork detaches the head.

## Fixture And Example

- `fixtures/wordpress-ordered-snapshot.json` stores ordered `wp_options`, `wp_posts`, and `wp_postmeta` records under raw integer keys.
- `examples/wordpress-iterator-window.php` loads the fixture and emits a three-record window beginning at key 2, modeling a bounded Playground snapshot sync page.
- `examples/wordpress-proof-range.php` exports and imports a compact range proof for keys 2 through 4, then reads the authenticated partial tree using only the trusted root and encoded proof bytes.
- `examples/wordpress-proof-update.php` exports a narrow proof for a single `wp_posts` record, applies an authenticated update to the imported partial tree, and verifies that the partial update root matches the full snapshot update root.
- `examples/wordpress-proof-merge.php` imports one proof for `siteurl`, merges a second proof for a post record with the same trusted root, and reads both records from the expanded authenticated partial tree.
- `examples/wordpress-sync-diff.php` uses upstream-shaped sync request/response transport round trips to fetch bounded proof fragments from a changed snapshot, diff the authenticated shadow tree, and reconstruct updated/deleted/added WordPress records locally.
- `examples/wordpress-sync-scan-diff.php` streams scan-time diff callbacks while sync requests converge, then shows that those callbacks match the final authenticated WordPress option/post diff.
- `examples/wordpress-node-id-reuse.php` rebuilds the ordered snapshot from reused leaf node ids, preserving unchanged record node ids while producing the same trusted root and a new branch head id.
- `examples/wordpress-snapshot-fork.php` saves the branch head id for an ordered snapshot, applies a post update on a fork, then checks out both old and new branch heads to read the authenticated record versions.
- `examples/wordpress-node-id-diff.php` compares a saved snapshot with a changed fork and reports matching scan-time and final diff leaf node ids for changed, deleted, and added records.
- `examples/wordpress-memstore-overlay.php` starts from an authenticated ordered snapshot, writes preview-only post changes into upstream's memStore node-id range, and leaves the base snapshot root unchanged.
- `examples/wordpress-named-memstore-fork.php` starts from a named published snapshot head, demonstrates the upstream memStore guard for direct volatile writes, then forks into a detached preview overlay that leaves the published head untouched.

## Next Task

Broaden bounded tracked scan/diff node-id parity into full upstream 500-trial randomized sync fuzz with imported shadow node ids.
