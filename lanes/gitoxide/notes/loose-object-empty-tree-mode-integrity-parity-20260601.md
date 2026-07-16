# Loose Object Empty Tree Mode Integrity Parity

Micro-slice: `gitoxide-loose-object-integrity-parity-20260601T224608Z`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-object/src/tree/mod.rs` at upstream commit `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- `EntryMode::extract_from_bytes()` accepts an immediate space delimiter as mode value `0`, and `EntryMode::kind()` maps unmatched mode bits to the catch-all commit/submodule kind.
- `gix-odb` loose-object integrity decodes tree objects through `object.decode()` after canonical hash verification, so this tree decode behavior is part of loose integrity parity.

Implementation:

- `TreeEntry::assertValidMode()` now permits the empty mode string while preserving rejection of malformed non-empty modes and the existing malformed-mode diagnostic.
- `Tree::parse()` therefore accepts a loose tree payload shaped as `" block.html\0<20-byte oid>"`, preserves the empty mode on round trip, and classifies it as `commit`.
- The WordPress object-database smoke now writes and verifies a loose tree object whose block-template entry uses this upstream empty-mode shape.

Non-overlap:

- This does not repeat accepted loose-object header canonicalization, NUL delimiter, size mismatch, trailing stream, allocation-limit, SHA-256, CRLF structured object, iterator traversal, broken symlink, case duplicate, or write-finalization slices.
- The change is limited to the upstream tree entry mode `0` parser boundary reached by loose-object integrity decode.

Dependency closure:

- No new support component is required. The slice reuses the existing native PHP loose object store, object database, tree parser, and WordPress fixture smoke path.

Verification:

- Red check before the patch: `Tree::parse(" block.html\0<20-byte oid>")` rejected with `Tree entry mode must be one to seven octal digits`.
- `php -l` passed for `src/TreeEntry.php`, `tests/TreeTest.php`, `tests/GitObjectTest.php`, `tests/ObjectDatabaseTest.php`, and `examples/wordpress-object-database.php`.
- `php tools/run-tests.php lanes/gitoxide/tests/TreeTest.php lanes/gitoxide/tests/GitObjectTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`: `3 test files, 751 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-object-database.php`: exited `0`.
- `git diff --check -- lanes/gitoxide`: passed.
