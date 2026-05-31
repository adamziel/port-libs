# Packed-Refs Header And Out-Of-Bounds Peeling Parity

Micro-slice: `gitoxide-packed-refs-peeling-parity-20260531T104831Z`

Upstream source truth:

- `gix-ref/src/store/packed/decode.rs` parses `# pack-refs with: ` header traits into an order-sensitive peeled state: unspecified, `peeled`, or `fully-peeled`, while ignoring unknown traits for behavior.
- `gix-ref/src/store/packed/decode/tests.rs::header::valid_fully_peeled_stored` is the exact upstream unit probe for the common `peeled fully-peeled sorted` header.
- `gix-ref/tests/refs/packed/find.rs::binary_search_a_name_past_the_end_of_the_packed_refs_file` uses `gix-ref/tests/fixtures/packed-refs/triggers-out-of-bounds`, whose final tag record has a peeled sidecar at EOF, and verifies a lookup beyond the last tag returns no reference without reading past the packed buffer.

Native PHP mapping:

- `PackedReferences` now exposes `headerPeeledState()` with `unspecified`, `partial`, and `fully` states while preserving existing raw header trait reporting.
- The upstream `triggers-out-of-bounds` packed-refs fixture is copied into the lane with its trailing header space normalized for `git diff --check`, and is covered by `PackedReferencesTest.php`; the test verifies the final peeled tag sidecar, exact packed names, and a missing `v0.0.1` lookup.
- The WordPress packed-refs smoke now reports the packed file's peeled-state header and keeps a compact-release missing lookup as a native PHP null result without invoking `git show-ref` or `git for-each-ref`.

Verification:

- Upstream: `timeout 180 cargo test -p gix-ref --test refs packed::find::binary_search_a_name_past_the_end_of_the_packed_refs_file --features sha1,sha256 -- --exact --nocapture`: `1 passed; 0 failed; 143 filtered out`
- Upstream: `timeout 180 cargo test -p gix-ref --lib store_impl::packed::decode::tests::header::valid_fully_peeled_stored --features sha1,sha256 -- --exact --nocapture`: `1 passed; 0 failed; 16 filtered out`
- `php -l lanes/gitoxide/src/PackedReferences.php`
- `php -l lanes/gitoxide/tests/PackedReferencesTest.php`
- `php -l lanes/gitoxide/fixtures/wordpress-packed-refs.php`
- `php -l lanes/gitoxide/examples/wordpress-packed-refs.php`
- `php tools/run-tests.php lanes/gitoxide/tests/PackedReferencesTest.php`: `1 test files, 51 assertions, 0 failures`
- `php tools/run-tests.php lanes/gitoxide/tests`: `39 test files, 4298 assertions, 0 failures`
- `php lanes/gitoxide/examples/wordpress-packed-refs.php`: exits `0`
- `git diff --check -- lanes/gitoxide`: exits `0`

Dependency closure:

- No new support component is needed. The slice reuses the existing packed-ref parser and reference lookup surface.

Non-overlap:

- This does not repeat the accepted `peelToObjectId()`, `prefixedPeeled()`, or stale packed sidecar transaction-rewrite slices. It maps the remaining packed buffer header peeled-state and upstream EOF peeled-sidecar lookup fixture that was previously noted as a sparse-cache gap.
