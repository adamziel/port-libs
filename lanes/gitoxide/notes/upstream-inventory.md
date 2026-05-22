# Gitoxide Upstream Inventory

Upstream checkout: `GitoxideLabs/gitoxide` at `87433ed33eee9ba974111d20b854f6acb07cd4a6`.

Inventory method: shallow filtered checkout plus `git ls-tree -r --name-only HEAD`. Broad blob scans were stopped because they hydrate too many blobs in this VM; future inventory should either use a non-filtered checkout in a controlled window or target specific crates.

Static tree denominator:

- 93 `Cargo.toml` manifests in the Rust workspace.
- 472 Rust test/bench source files matching `tests`, `benches`, or root `tests.rs`.
- 605 files under upstream `tests/fixtures/`.
- 180 shell fixture scripts under upstream `tests/fixtures/`.
- 214 files under upstream `tests/fixtures/generated-archives/`.
- 2,877 files total in the upstream tree listing.

Targeted object/ref inventory inspected on 2026-05-22:

- The `.upstream-cache/gitoxide` checkout is sparse/no-checkout (`core.sparseCheckout=true`), so crate files were inspected through `git ls-tree`, targeted `git show`, and targeted `git grep` rather than broad working-tree scans.
- 205 paths under `gix-object` and `gix-ref`.
- 114 paths under `gix-object/tests` and `gix-ref/tests`.
- 37 Rust integration test source files under `gix-object/tests` and `gix-ref/tests`.
- 77 fixture paths under `gix-object/tests/fixtures` and `gix-ref/tests/fixtures`.
- 296 Rust `#[test]` attributes counted under targeted `gix-object`/`gix-ref` source and test paths.
- 25 `gix-object` tree behavior `#[test]` attributes counted under `gix-object/tests/object/tree` and `gix-object/src/tree`.
- 8 committed `gix-object/tests/fixtures/tree` binary tree fixtures.

Focused loose-ref inventory inspected on 2026-05-22:

- 16 selected `gix-ref` loose-reference, loose-store, and fixture paths inspected with targeted `git ls-tree`, `git show`, and `git grep`.
- 47 Rust `#[test]` attributes counted across `gix-ref/tests/refs/file/reference.rs`, `gix-ref/tests/refs/file/store/access.rs`, `gix-ref/tests/refs/file/store/find.rs`, `gix-ref/tests/refs/file/store/iter.rs`, and `gix-ref/tests/refs/packed/find.rs`.
- `gix-ref/src/store/file/loose/reference/decode.rs` defines the mapped parser semantics: direct refs read the configured hash length from the start of the file, symbolic refs start with `ref: `, skip additional spaces before the target, and stop the symbolic target at CR/LF.
- `gix-ref/tests/fixtures/make_ref_repository.sh` and `make_pristine.sh` provide the mapped direct, symbolic, `FETCH_HEAD`, broken-ref, and detached-HEAD scenarios for this slice.

Focused packed-ref inventory inspected on 2026-05-22:

- 10 selected `gix-ref` packed decode, buffer, find, iterator, and fixture paths inspected with targeted `git show`, `git ls-tree`, and `git grep`.
- 16 Rust `#[test]` attributes counted across `gix-ref/src/store/packed/decode/tests.rs` and `gix-ref/tests/refs/packed/find.rs`.
- `gix-ref/src/store/packed/decode.rs` defines the mapped parser semantics: optional `# pack-refs with: ` headers, ignored unknown traits, sorted flag detection, direct object IDs, validated full ref names, optional `^` peeled object lines, uppercase hex acceptance, and SHA-256 hash mode support.
- `gix-ref/src/store/packed/buffer.rs` and `find.rs` define the mapped buffer behavior: no-header and unsorted files are accepted, unsorted references are sorted in memory, and partial lookup tries `refs/`, `refs/tags/`, `refs/heads/`, then `refs/remotes/`.
- `gix-ref/tests/fixtures/packed-refs/without-header` and `packed-refs/unsorted` are copied into this lane as fixture parity inputs.

Focused reference-store overlay inventory inspected on 2026-05-22:

- 3 selected `gix-ref` file-store find and overlay fixture paths inspected with targeted `git show` and `git grep`.
- 7 Rust `#[test]` attributes counted in `gix-ref/tests/refs/file/store/find.rs`.
- `gix-ref/src/store/file/find.rs` defines the mapped lookup behavior: try loose refs first, fall back to packed refs only when a loose candidate is absent, keep `HEAD` and symbolic refs loose, find capitalized packed branches, and resolve `refs/remotes/<name>/HEAD` as a loose-only remote shortcut.
- `gix-ref/tests/fixtures/make_packed_ref_repository_for_overlay.sh` provides the mapped loose-over-packed branch overlay scenario.

Focused pack-index inventory inspected on 2026-05-22:

- 5 selected `gix-pack` index source/test paths inspected with targeted `git show` and `git grep`.
- 8 Rust `#[test]` attributes counted in `gix-pack/tests/pack/index.rs`.
- `gix-pack/src/index/init.rs` defines the mapped v2 parser semantics: `\xfftOc` signature, version validation, 256-entry monotonic fanout table, object count from fanout[255], v2 table size validation, large-offset table validation, and trailing pack/index checksums.
- `gix-pack/src/index/access.rs` and `verify.rs` define the mapped access semantics: object IDs are fanout-bounded and sorted, lookups use binary search by full object ID, prefix lookups can be missing/ambiguous/found, CRC32 values are available for v2, pack offsets may be 32-bit or large 64-bit offsets, sorted offsets are useful for pack traversal, and index checksums cover all bytes before the trailing checksum.

Runner status:

- `cargo` is available locally.
- Full `cargo test` was not executed because the workspace is large, feature-heavy, and would hydrate/build far beyond the current VM cap.
- Crate-level Cargo tests were not executed in this run because the cache is sparse/no-checkout; running them requires materializing at least the selected crate source paths and building Rust dependencies.
- The next inventory slice should materialize only the needed object/ref crate paths and try `cargo test -p gix-object --no-run --locked --offline` before any live runner attempt.

Current PHP mapping:

- `GitObjectTest.php` maps canonical object header storage, SHA-1 object IDs, loose object zlib storage, and invalid object headers.
- `CommitTest.php` maps basic commit header parsing, parent lists, required header errors, and reading a commit body from native Git object bytes.
- `TreeTest.php` maps `gix-object` tree semantics for empty trees, `everything.tree` entry kinds, entry-mode classification, leading-space filenames, truncated object IDs, malformed modes, tree-object roundtrips, and a WordPress deploy tree fixture.
- `LooseReferenceTest.php` maps `gix-ref` loose direct and symbolic ref parsing, uppercase object ID normalization, SHA-256 object IDs when requested, `FETCH_HEAD` first-OID parsing, trailing hex rejection in SHA-1 mode, symbolic target validation, loose on-disk writes, and a WordPress deploy-branch reference fixture.
- `PackedReferencesTest.php` maps `gix-ref` packed-ref header traits, uppercase and SHA-256 object IDs, peeled object lines, invalid headers/lonely peels, upstream `without-header` and `unsorted` fixtures, packed partial lookup disambiguation, and a WordPress packed branch/tag fixture.
- `ReferenceStoreTest.php` maps loose-over-packed precedence, opening `packed-refs` from a Git directory, loose-only remote `HEAD` shortcuts, capitalized packed branches, and WordPress combined loose+packed ref resolution.
- `PackIndexTest.php` maps `gix-pack` v2 pack-index fanout parsing, monotonic fanout/size/version validation, pack and index checksum access, checksum verification, full object ID lookup, prefix missing/ambiguous/found outcomes, CRC32 entries, 32-bit and large 64-bit offsets, sorted offsets, and a WordPress compacted object-offset fixture.
