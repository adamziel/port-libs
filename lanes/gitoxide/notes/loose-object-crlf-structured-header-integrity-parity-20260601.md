# Loose Object CRLF Structured Header Integrity Parity - 2026-06-01

Micro-slice: `gitoxide-loose-object-integrity-parity-20260601T095205Z`
Base accepted HEAD: `c4086662a04e6ef1ef746773f2a19994bf04a926`

## Upstream Source Truth

- Re-read upstream `gix-odb/src/store_impls/loose/verify.rs` at manifest
  commit `87433ed33eee9ba974111d20b854f6acb07cd4a6`: loose object integrity
  hashes decoded object bytes and then calls structured `object.decode()`.
- Re-read upstream `gix-object/src/parse.rs`,
  `gix-object/src/commit/decode.rs`, and `gix-object/src/tag/decode.rs`:
  structured object line parsing splits on LF and does not normalize a
  preceding CR byte out of required header values.
- Mapped parity: a raw loose commit or tag object can be stored under its
  correct object id while still failing integrity if required structured
  headers use CRLF bytes. The CR remains part of the object-id header value,
  so decode rejects it.

## Native PHP Delta

- `Commit::trimLineEnding()` and `GitTag::trimLineEnding()` now remove only the
  LF terminator, preserving a preceding CR byte for structured validation.
- `GitObjectTest.php` adds raw loose commit and tag fixtures with CRLF required
  headers and an LF object-message separator. Direct loose reads still return
  the raw body, while direct parse and loose integrity reject the structured
  object.
- `wordpress-object-database.php` reports the same CRLF commit/tag loose
  integrity rejection flags, and `ObjectDatabaseTest.php` asserts them.

## Evidence

- Red-first probe before the implementation accepted a commit body whose
  `tree`, `author`, and `committer` headers used CRLF bytes.
- `php -l lanes/gitoxide/src/Commit.php` passed.
- `php -l lanes/gitoxide/src/GitTag.php` passed.
- `php -l lanes/gitoxide/tests/GitObjectTest.php` passed.
- `php -l lanes/gitoxide/tests/ObjectDatabaseTest.php` passed.
- `php -l lanes/gitoxide/examples/wordpress-object-database.php` passed.
- `php tools/run-tests.php lanes/gitoxide/tests/GitObjectTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  passed with `2 test files, 469 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/GitObjectTest.php lanes/gitoxide/tests/ObjectDatabaseTest.php lanes/gitoxide/tests/CommitTest.php lanes/gitoxide/tests/CommitSignatureObjectTest.php lanes/gitoxide/tests/GitTagTest.php`
  passed with `5 test files, 947 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-object-database.php >/tmp/gitoxide-wordpress-object-database.out`
  exited 0.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
loose object store, zlib compression path, commit/tag parsers, object database
integrity walker, and focused PHP test/example harnesses. No Cargo workspace,
network, live-service, or provider-credential dependency was introduced.

## Non-overlap

This only extends loose object structured decode parity for CRLF bytes in
required commit/tag headers. It does not repeat accepted loose-object hash,
signed-size, allocation, empty-file, inflated-size, trailing-stream, late
overrun, SHA-256, nested-directory, case-duplicate, broken-symlink, or write
finalization integrity clusters, and it does not touch transport, protocol,
reference, pack/index, credential, URL/refspec, or tree/pathspec behavior.
