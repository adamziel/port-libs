# Packed-Refs Peeling Parity

Micro-slice: `gitoxide-packed-refs-peeling-parity-20260531T090852Z`

Upstream source truth:

- `gix/src/reference/iter.rs` defines `references().prefixed(...).peeled()`: iteration keeps a stable packed-ref buffer and peels each yielded reference before returning it.
- `gix/tests/gix/repository/reference.rs::prefixed_and_peeled` records the visible behavior: prefixed iteration may follow symbolic refs to a final target outside the original prefix, and packed peeled IDs are returned as object targets.
- `gix-ref/src/store/file/raw_ext.rs` defines `peel_to_id_packed()`, which uses stored packed peeled IDs without requiring object lookup.

Native PHP mapping:

- `ReferenceStore::prefixedPeeled()` now maps prefixed references through `followReferenceToObject()` and returns object-target `ResolvedReference` values.
- Packed refs with stored peeled IDs are converted directly to the peeled object target, preserving the final reference name and source after symbolic resolution.
- Non-packed or unpeeled direct refs can still use the existing `ObjectDatabase` tag-chain peeling path when a database is supplied.
- The WordPress packed-refs smoke now models a loose `refs/heads/release-candidate` symbolic ref pointing at a packed release tag and verifies prefixed peeled iteration reports the release commit without invoking `git for-each-ref`.

Verification:

- `php -l lanes/gitoxide/src/ReferenceStore.php`
- `php -l lanes/gitoxide/fixtures/wordpress-packed-refs.php`
- `php -l lanes/gitoxide/examples/wordpress-packed-refs.php`
- `php -l lanes/gitoxide/tests/PackedReferencesTest.php`
- `php -l lanes/gitoxide/tests/ReferenceStoreTest.php`
- `php tools/run-tests.php lanes/gitoxide/tests/PackedReferencesTest.php lanes/gitoxide/tests/ReferenceStoreTest.php`: `2 test files, 411 assertions, 0 failures`
- `php lanes/gitoxide/examples/wordpress-packed-refs.php`: exits `0`
- `php tools/run-tests.php lanes/gitoxide/tests`: `38 test files, 3747 assertions, 0 failures`

Dependency closure:

- No new support component is needed. The slice reuses existing packed-ref parsing, symbolic reference lookup, and object database tag peeling.

Non-overlap:

- This does not repeat the accepted single-reference packed/loose `peelToObjectId()` work from `d47dc133`; it adds the missing iterator-shaped `prefixed(...).peeled()` parity surface used by upstream Gitoxide when a packed-ref buffer is already held during iteration.
