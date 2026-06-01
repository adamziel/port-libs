# Loose Object Overlong Header Window Parity - 2026-06-01

Micro-slice: `gitoxide-loose-object-integrity-parity-20260601T184632Z`

Base accepted HEAD: `251e6c15aa22f4f06aae4aa9f10b34fd233b85dd`

## Source Truth

- Upstream cache: `/home/claude/port-libs/.upstream-cache/gitoxide` at
  `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `gix-odb/src/store_impls/loose/find.rs` inflates loose-object headers into
  the fixed `HEADER_MAX_SIZE` 64-byte buffer for `try_header()` and
  `try_find()`.
- The first inflated window is decoded by
  `gix_object::decode::loose_header()` from `gix-object/src/lib.rs`, so an
  overlong header whose NUL appears after byte 64 keeps upstream diagnostic
  ordering: missing delimiter first, unknown kind before size/NUL, then missing
  NUL for a known kind with a space delimiter.

## Native PHP Delta

- `LooseObjectStore::inflateHeaderBytes()` and
  `inflateStorageBytesExactly()` now route overlong first-window header bytes
  through the same loose-header diagnostic ordering instead of returning a
  local maximum-size error.
- `GitObjectTest.php` covers known-kind, unknown-kind, and missing-delimiter
  overlong first-window headers through header reads, body reads, and integrity
  verification.
- `ObjectDatabaseTest.php` covers the same behavior across primary and
  alternate loose object stores.
- `wordpress-object-header.php` records the WordPress import/deploy preflight
  smoke for overlong loose-object headers before trusting advertised sizes.

## Verification

- Red-first probe before implementation:
  - `php -r 'require "tools/bootstrap.php"; ... write gzcompress("blob " . str_repeat("1", 60) . "\0body") ... readHeader(...) ...'`
  - output: `InvalidArgumentException: Loose object header exceeds maximum size of 64 bytes`
- PHP lint:
  - `php -l lanes/gitoxide/src/LooseObjectStore.php`
  - `php -l lanes/gitoxide/tests/GitObjectTest.php`
  - `php -l lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `php -l lanes/gitoxide/examples/wordpress-object-header.php`
  - `php -l lanes/gitoxide/fixtures/wordpress-object-header.php`
  - all reported no syntax errors.
- Focused tests:
  - `php tools/run-tests.php lanes/gitoxide/tests/GitObjectTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `2 test files, 708 assertions, 0 failures`
- Full Gitoxide lane:
  - `php tools/run-tests.php lanes/gitoxide/tests`
  - `40 test files, 10570 assertions, 0 failures`
- Example smoke:
  - `php lanes/gitoxide/examples/wordpress-object-header.php`
  - exited 0.
- Whitespace:
  - `git diff --check -- lanes/gitoxide`
  - exited 0.

Root aggregate and full upstream Cargo workspace runners were not run for this
isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses PHP zlib streaming APIs,
the existing native loose-object store, object database alternates, and the
existing WordPress object-header example.

## Non-Overlap

This does not repeat accepted loose-object signed/zero-padded size
canonicalization, LF-tailed size rejection, missing-NUL complete short headers,
missing type-size delimiter short headers, unknown-kind short headers,
NUL-before-space ordering, allocation limits, empty-file rejection, inflated
body-size mismatches, trailing compressed streams, late same-stream overruns,
SHA-256 structured decoding, directory/nested/symlink/case iterator candidates,
traversal errors, interruption callbacks, or first-window zlib
truncation/corruption. The new mapped behavior is limited to decode ordering for
overlong loose-object headers whose first 64 inflated bytes do not contain the
NUL terminator.
