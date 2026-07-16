# Loose Object Zero-Padded Size Integrity Parity

Micro-slice: `gitoxide-loose-object-integrity-parity-20260601T122118Z`
Base accepted HEAD: `104a9f5fce0ab0f0e77688b3f9277242f2f9e31c`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-utils/src/btoi.rs`
  keeps `to_signed()` parsing decimal digits through Rust integer parsing,
  which accepts zero-padded positive sizes.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-object/src/lib.rs`
  decodes loose headers into a kind and size, then canonical object hashing is
  based on `"<kind> <body-size>\0<body>"`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-odb/src/store_impls/loose/find.rs`
  reads loose bytes from the object-id path without requiring the raw storage
  header to match the path hash first.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-odb/src/store_impls/loose/verify.rs`
  verifies loose objects by reading the object and comparing the decoded
  object's canonical id to the path id.

## Native PHP Delta

- Extended the WordPress object-header fixture and smoke to include
  zero-padded loose-object size bytes.
- Added loose-store coverage proving `blob 0003\0abc` decodes and verifies
  under the canonical `blob 3\0abc` id, but fails integrity when stored under
  the raw zero-padded header hash.
- Added object-database coverage for the same canonicalization boundary across
  primary and alternate loose-object stores.
- No production PHP code change was needed: existing `GitObject`,
  `LooseObjectStore`, and `ObjectDatabase` behavior already matched this
  upstream cluster and is now locked by tests and smoke coverage.

## Verification

- `php tools/run-tests.php lanes/gitoxide/tests/GitObjectTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  passed: `2 test files, 538 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`
  passed: `40 test files, 9238 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-object-header.php` exited `0`.
- `php -l` passed for:
  `lanes/gitoxide/fixtures/wordpress-object-header.php`,
  `lanes/gitoxide/examples/wordpress-object-header.php`,
  `lanes/gitoxide/tests/GitObjectTest.php`,
  `lanes/gitoxide/tests/ObjectDatabaseTest.php`.
- `git diff --check -- lanes/gitoxide` passed.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP zlib
inflation, hashing, loose-object storage, object-database alternate traversal,
and TestRunner infrastructure.

## Non-Overlap

This does not repeat accepted signed-size, negative-zero, LF-size rejection,
CRLF structured-header rejection, empty-file, allocation-limit,
trailing-stream, late same-stream overrun, nested-candidate, broken-symlink,
SHA-256 loose integrity, protocol, transport, pack/index, or reference
transaction parity slices.

