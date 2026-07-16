# Loose Object Unknown-Kind Header Integrity Parity

## Source Truth

- Upstream cache: `/home/claude/port-libs/.upstream-cache/gitoxide`
- Upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- Relevant upstream files:
  - `gix-object/src/lib.rs`
  - `gix-object/src/kind.rs`
  - `gix-odb/src/store_impls/loose/find.rs`
  - `gix-odb/src/store_impls/loose/verify.rs`
  - `gix-odb/src/store_impls/loose/iter.rs`

`gix_object::decode::loose_header()` parses the object kind after the first
space and before searching for the NUL terminator or parsing the size. That
means `wordpress 123`, `wordpress nope\0body`, and `wordpress 4\0body` should
all reject as unknown object kinds before missing-NUL or invalid-size fallback
diagnostics.

## Native Delta

- `GitObject::decodeLooseHeader()` now validates the type token before NUL and
  size parsing.
- `LooseObjectStore` mirrors that ordering for small complete inflated loose
  object streams across `readHeader()`, `tryReadHeader()`, `read()`, and
  `verifyIntegrity()`.
- `ObjectDatabase` inherits the same primary and alternate loose-store
  integrity behavior.
- `wordpress-object-header.php` now includes an unknown-kind loose-object
  corruption smoke path.

Red-first probe before the change:

```text
unknown-no-nul: InvalidArgumentException: Did not find 0 byte in header
unknown-with-nul: InvalidArgumentException: Invalid Git object header: wordpress 4
```

## Evidence

- `php -l` on changed PHP files passed.
- `php tools/run-tests.php lanes/gitoxide/tests/GitObjectTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  passed: `2 test files, 643 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-object-header.php` exited `0`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed:
  `40 test files, 10138 assertions, 0 failures`.
- `git diff --check -- lanes/gitoxide` passed.

## Non-overlap

This slice does not repeat the accepted loose-object missing type/size
delimiter, missing-NUL, size mismatch, allocation-limit, trailing-stream,
SHA-256, empty-file, case-duplicate, broken-symlink, or iterator interruption
clusters. It owns only the upstream ordering for unsupported object kinds
before later header-integrity fallbacks.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP
`GitObject`, `LooseObjectStore`, `ObjectDatabase`, zlib-backed loose-object
fixture writer, and the existing lane test harness. The full upstream Cargo
workspace was not run for this isolated micro-slice.
