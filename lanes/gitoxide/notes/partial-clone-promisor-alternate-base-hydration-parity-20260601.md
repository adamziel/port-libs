# Partial Clone Promisor Alternate-Base Hydration Parity

Micro-slice: `gitoxide-partial-clone-promisor-hydration-parity-20260601T081130Z`

Accepted base: `5529bd9ecf625e32345da54315436fc326df673b`

## Source Truth

- `gix/src/remote/connection/fetch/receive_pack.rs` passes the object store's alternate database paths into pack bundle writing when receiving fetch packs.
- `gix-pack/src/bundle/write/*` verifies thin packs against the primary object database plus alternates before keeping the accepted pack.
- `gix-odb/src/store_impls/dynamic/find.rs` and `header.rs` resolve external REF_DELTA bases through the dynamic object store, including loose stores from alternates, while preserving the 32-step recursion guard already ported in this lane.

## Native Delta

- `ObjectDatabase::writePromisorPackBundle()` now validates external `ref-delta` bases before writing `.keep`, `.pack`, `.idx`, or `.promisor` files.
- Thin promisor pack bundles are accepted when their missing REF_DELTA bases are present in an alternate object directory.
- Thin promisor pack bundles with unresolved external bases are rejected before any bundle sidecar is left behind.
- The WordPress lazy-promisor example now covers alternate-backed thin pack hydration through the native bundle writer.

## Red-First Evidence

After adding the focused missing-base test but before the source fix:

`php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php`

Result: `1 test files, 268 assertions, 1 failures`

Failing case: `object database rejects promisor thin pack bundles with unresolved external bases`

## Verification

- `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php`
  - Result: `1 test files, 286 assertions, 0 failures`
- `php tools/run-tests.php lanes/gitoxide/tests`
  - Result: `40 test files, 8213 assertions, 0 failures`
- `php lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php`
  - Result: exit `0`
- `php -l lanes/gitoxide/src/ObjectDatabase.php`
  - Result: `No syntax errors detected in lanes/gitoxide/src/ObjectDatabase.php`
- `php -l lanes/gitoxide/tests/PartialCloneTest.php`
  - Result: `No syntax errors detected in lanes/gitoxide/tests/PartialCloneTest.php`
- `php -l lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php`
  - Result: `No syntax errors detected in lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php`
- `git diff --check -- lanes/gitoxide`
  - Result: exit `0`

## Non-Overlap

This does not repeat the accepted promisor bundle writer sidecar behavior, resolver-returned object hydration, refresh-never inventory behavior, cross-pack promisor REF_DELTA reads, resolver-hydrated thin-delta bases, or the external delta recursion bound. The new behavior is write-time acceptance parity for thin promisor bundles whose external bases must be resolvable through local object storage or alternates.

## Dependency Closure

No new support component is needed. The slice reuses the existing native `ObjectDatabase` alternate resolution, `LooseObjectStore`, `PackBuilder`, pack index/data readers, and promisor bundle writer.

Next useful follow-up: stale multi-pack-index refresh behavior for promisor hydration when pack indexes disappear or are replaced during a partial clone fetch.
