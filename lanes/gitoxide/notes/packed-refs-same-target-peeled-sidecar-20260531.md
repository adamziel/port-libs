# Packed-Refs Same-Target Peeled Sidecar

Micro-slice: `gitoxide-packed-refs-peeling-parity-20260531T101350Z`

Upstream source truth:

- `gix-ref/src/store/file/transaction/mod.rs` defines packed update modes that propagate non-symbolic object updates into `packed-refs`.
- `gix-ref/src/store/packed/transaction.rs::prepare()` recalculates `Edit::peeled` from the supplied object database for every object update, and `commit()` writes the edit over an equal-name packed record with a fresh optional `^<peeled>` sidecar.
- `gix-ref/tests/refs/file/transaction/prepare_and_commit/create_or_update/mod.rs::packed_refs_creation_with_packed_refs_mode_leave_keeps_original_loose_refs` records that packed update mode rewrites existing packed records while preserving loose refs.

Native PHP mapping:

- `ReferenceStore::updateWithReport()` now treats the packed peeled sidecar as part of same-target packed update equality when an `ObjectDatabase` is supplied.
- If a packed tag target already matches but its peeled sidecar is missing or stale, explicit packed update mode rewrites `packed-refs` with the recalculated peeled commit.
- The WordPress packed-reference transaction smoke now models stale release-tag sidecar repair for deploy tooling that wants compact release refs without invoking `git pack-refs`.

Verification:

- `php -l lanes/gitoxide/src/ReferenceStore.php`
- `php -l lanes/gitoxide/tests/ReferenceStoreTest.php`
- `php -l lanes/gitoxide/examples/wordpress-packed-reference-transaction.php`
- `php tools/run-tests.php lanes/gitoxide/tests/ReferenceStoreTest.php`: `1 test files, 393 assertions, 0 failures`
- `php lanes/gitoxide/examples/wordpress-packed-reference-transaction.php`: exits `0`
- `php tools/run-tests.php lanes/gitoxide/tests`: `38 test files, 4103 assertions, 0 failures`
- `git diff --check -- lanes/gitoxide`: exits `0`

Dependency closure:

- No new support component is needed. The slice reuses existing packed-ref parsing and native loose object database tag peeling.

Non-overlap:

- This does not repeat the accepted `peelToObjectId()` or `prefixedPeeled()` lookup slices. It covers the packed transaction rewrite edge where the target object ID is unchanged but the serialized peeled sidecar must still be regenerated.
