# quadrable WordPress Scenario

Authenticated local-first state sync for Playground snapshots and content databases.

## Current Native Slice

Pure-PHP BLAKE2s-256 hash/key primitives plus an in-memory sparse tree for get/put/delete, update batching, path-independent roots, `getMulti`, empty-head restoration, delete bubbling equivalence, ordered raw integer keys for WordPress option/post records, composite integer/hash keys for post-meta style rows, an exact upstream-digest snapshot root, iterator windows for incremental sync chunks, compact authenticated range proofs for partial snapshot verification, proof-backed partial-tree updates for narrow content changes, merged partial proofs for independently requested records, bounded sync proof fragments with diff reconstruction, upstream-compatible sync fragment path-order/same-path guards, scan-time diff callbacks that match the final authenticated diff, imported proof-fragment shadow leaf node ids that match final sync diffs, exposed memStore-range sync shadow root node ids, upstream-MT19937-shaped sync-fuzz dimensions and byte budgets, partial-shadow diffs that skip unchanged witness branches while reconstructing changed paths, tracked leaf node-id reuse for compact rebuilds of unchanged snapshot records, saved branch-head checkout for old/new snapshot forks, tracked scan/final diffs that report identical leaf node ids for changed/deleted/added records, tracked diff application that reconstructs changed snapshots from final diffs, memStore-range detached overlays for volatile preview edits, and named published heads that reject volatile memStore writes until a preview fork detaches the head.

## Fixture And Example

- `fixtures/wordpress-ordered-snapshot.json` stores ordered `wp_options`, `wp_posts`, and `wp_postmeta` records under raw integer keys.
- `examples/wordpress-iterator-window.php` loads the fixture and emits a three-record window beginning at key 2, modeling a bounded Playground snapshot sync page.
- `examples/wordpress-composite-meta-key.php` uses `Key::fromIntegerAndHash` to group multiple hashed `wp_postmeta` names under a stable post-id prefix.
- `examples/wordpress-proof-range.php` exports and imports a compact range proof for keys 2 through 4, then reads the authenticated partial tree using only the trusted root and encoded proof bytes.
- `examples/wordpress-proof-update.php` exports a narrow proof for a single `wp_posts` record, applies an authenticated update to the imported partial tree, and verifies that the partial update root matches the full snapshot update root.
- `examples/wordpress-proof-merge.php` imports one proof for `siteurl`, merges a second proof for a post record with the same trusted root, and reads both records from the expanded authenticated partial tree.
- `examples/wordpress-sync-diff.php` uses upstream-shaped sync request/response transport round trips to fetch bounded proof fragments from a changed snapshot, diff the authenticated shadow tree, and reconstruct updated/deleted/added WordPress records locally.
- `examples/wordpress-sync-scan-diff.php` streams scan-time diff callbacks while sync requests converge, then shows that those callbacks match the final authenticated WordPress option/post diff.
- `examples/wordpress-sync-node-id-parity.php` shows scan-time and final sync diffs carrying identical node ids, with changed/added records coming from imported memStore-range shadow leaves and deletes carrying local snapshot node ids.
- `examples/wordpress-sync-shadow-node.php` reports the memStore-range shadow root node id as proof fragments expand toward the authenticated remote snapshot root.
- `examples/wordpress-sync-request-guard.php` demonstrates rejecting overlapping same-path proof fragment requests from a malformed snapshot sync peer before any response proof is exported.
- `examples/wordpress-node-id-reuse.php` rebuilds the ordered snapshot from reused leaf node ids, preserving unchanged record node ids while producing the same trusted root and a new branch head id.
- `examples/wordpress-snapshot-fork.php` saves the branch head id for an ordered snapshot, applies a post update on a fork, then checks out both old and new branch heads to read the authenticated record versions.
- `examples/wordpress-node-id-diff.php` compares a saved snapshot with a changed fork and reports matching scan-time and final diff leaf node ids for changed, deleted, and added records.
- `examples/wordpress-tracked-diff-reconstruct.php` applies final tracked diffs to reconstruct a changed WordPress snapshot and shows scan-time node ids matching the final diff node ids.
- `examples/wordpress-memstore-overlay.php` starts from an authenticated ordered snapshot, writes preview-only post changes into upstream's memStore node-id range, and leaves the base snapshot root unchanged.
- `examples/wordpress-named-memstore-fork.php` starts from a named published snapshot head, demonstrates the upstream memStore guard for direct volatile writes, then forks into a detached preview overlay that leaves the published head untouched.

## Next Task

Broaden the bounded two-trial upstream-MT19937 sync-fuzz slice into the full upstream 500-trial proof-fragment sync fuzz, using the exposed sync shadow root node ids as the next checkpoint toward persisted LMDB-style branch node-id parity.
