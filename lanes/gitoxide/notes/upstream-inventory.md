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

Focused pack-data/delta inventory inspected on 2026-05-22:

- Selected `gix-pack` data header, entry header, delta application, file decode, input lookup, and test paths inspected with targeted `git show` and `git grep`.
- 23 Rust `#[test]` attributes counted across `gix-pack/tests/pack/data/header.rs`, `gix-pack/tests/pack/data/file.rs`, `gix-pack/tests/pack/data/input.rs`, and `gix-pack/src/data/entry/header.rs`.
- `gix-pack/src/data/header.rs` defines the mapped pack file header semantics: `PACK` signature, version 2/3 support, and object count.
- `gix-pack/src/data/entry/header.rs` and `gix-pack/src/data/file/decode/entry.rs` define the mapped entry semantics: Git's variable-size entry header, type IDs for commit/tree/blob/tag/OFS_DELTA/REF_DELTA, zlib-compressed entry data, object size checks, and explicit delta handling paths.
- `gix-pack/src/data/delta.rs` defines the mapped delta semantics: 7-bit little-endian source/result size headers, copy instructions with offset/size bytes, zero-size copy expansion to `0x10000`, insert commands, and explicit rejection of reserved command 0/truncated data/out-of-range copies.
- `gix-pack/tests/pack/data/file.rs` and `gix-pack/tests/pack/data/input.rs` provide the mapped non-delta commit/blob/tree decompression cases plus OFS_DELTA and REF_DELTA resolution paths.

Focused object-database/alternates/replacements inventory inspected on 2026-05-22:

- Selected `gix-odb` dynamic object database find, header, prefix, iteration, linked-store, loose-store, alternate parser/resolver, replacement handling, and test paths inspected with targeted `git show` and `git grep`.
- 52 Rust `#[test]` attributes counted across `gix-odb/tests/odb/store/dynamic.rs`, `gix-odb/tests/odb/store/linked.rs`, `gix-odb/tests/odb/store/loose.rs`, `gix-odb/tests/odb/alternate.rs`, and `gix-odb/src/store_impls/dynamic/find.rs`, including the dynamic object replacement test.
- `gix-odb/src/store_impls/dynamic/find.rs` defines the mapped object lookup behavior: search loaded pack indices before loose object stores, read packed data by pack offset, then fall back to loose objects when no pack contains the id.
- `gix-odb/src/store_impls/dynamic/prefix.rs` defines the mapped prefix semantics: lookup across all pack indices and loose stores, return missing/found/ambiguous, and treat duplicate sightings of the same object id as one candidate.
- `gix-odb/src/store_impls/dynamic/iter.rs` defines the mapped traversal semantics: iterate packed objects before loose objects, with lexicographic index ordering by default and an optional pack-offset ordering for efficient packed reads.
- `gix-odb/src/alternate/mod.rs` and `parse.rs` define the mapped alternates semantics: read `objects/info/alternates`, skip blank/comment lines, unquote ANSI-C-style quoted paths, resolve relative paths from the objects directory, recurse into linked object databases, and reject cycles.
- `gix-odb/src/store_impls/dynamic/find.rs`, `header.rs`, and `init.rs` define the mapped replacement semantics: replacement pairs are sorted by source object id, object reads and headers apply one replacement by default, replacement application can be disabled, and replacement mappings remain inspectable.

Focused multi-pack-index inventory inspected on 2026-05-22:

- 11 selected `gix-pack` multi-index source/test paths inspected with targeted `git ls-tree`, `git show`, and `git grep`.
- 12 Rust `#[test]` attributes counted across `gix-pack/tests/pack/multi_index/access.rs`, `fuzzed.rs`, `verify.rs`, and `write.rs`.
- `gix-pack/src/multi_index/init.rs` defines the mapped v1 parser semantics: `MIDX` signature, hash-kind validation, chunk-table decoding with a sentinel, required `PNAM`/`OIDF`/`OIDL`/`OOFF` chunks, optional `LOFF`, sorted null-terminated pack index names, monotonic 256-entry fanout, object counts from fanout[255], exact chunk sizes, and trailing object-hash checksum bytes.
- `gix-pack/src/multi_index/access.rs` and `verify.rs` define the mapped access semantics: object IDs are fanout-bounded, full lookups binary-search the object-id table, prefix lookups return missing/ambiguous/found, entries map object IDs to pack-index IDs and pack offsets, high-bit 32-bit offsets use `LOFF` when present, and fast verification covers checksum, non-empty object sets, object order, and pack-offset consistency.

Focused protocol v2 inventory inspected on 2026-05-22:

- Selected `gix-protocol` command, `ls_refs`, handshake ref parsing, and `gix-transport` capability files inspected with targeted `git ls-tree`, `git show`, and `git grep`.
- 15 Rust `#[test]` attributes counted across `gix-protocol/tests/protocol/command.rs`, `gix-protocol/tests/protocol/handshake.rs`, `gix-protocol/src/ls_refs.rs`, and `gix-transport/tests/client/capabilities.rs`.
- `gix-transport/src/client/capabilities.rs` defines the mapped capability semantics: v1 capabilities are parsed after the NUL delimiter, v2 capabilities require a `version 2` first line, capability names come before `=`, values are space-separated, and callers can test whether a capability value is supported.
- `gix-protocol/src/command.rs` and `ls_refs.rs` define the mapped `ls-refs` command semantics: default arguments are `symrefs` and `peel`, `unborn` is requested only when the `ls-refs` capability supports it, ref-prefix arguments preserve first-seen order while de-duplicating duplicates, and validation rejects unknown arguments or unsupported non-agent features.
- `gix-protocol/src/handshake/refs/shared.rs` and `tests/protocol/handshake.rs` define the mapped v2 ref-line semantics: direct refs, symbolic refs, unborn symbolic refs, `(null)` symref targets, peeled refs, and symbolic peeled refs are all normalized into typed remote refs with explicit tag/object/target fields.

Focused fetch negotiation inventory inspected on 2026-05-22:

- Selected `gix-protocol/src/fetch/arguments/mod.rs`, `gix-protocol/tests/protocol/fetch/arguments.rs`, and the fetch sections of `gix-protocol/tests/protocol/command.rs` with targeted `git show` and `git grep`.
- 13 fetch-focused Rust test attributes counted: 9 async/blocking fetch argument tests plus 4 fetch command default-feature/initial-argument tests.
- `gix-protocol/src/command.rs` defines the mapped fetch feature selection semantics: protocol v1 chooses the best `multi_ack` and sideband variant, leaves `no-progress` disabled by default, and protocol v2 derives features from the advertised `fetch=` values.
- `gix-protocol/src/fetch/arguments/mod.rs` defines the mapped argument-builder semantics: protocol v2 begins with `thin-pack` and `ofs-delta`, adds `sideband-all` only when advertised, keeps `packfile-uris` out of default arguments, bakes protocol v1 features into the first `want`, treats protocol v2 as stateless, and exposes support flags for shallow, filter, ref-in-want, deepen, and include-tag behavior.

Focused fetch response and sideband inventory inspected on 2026-05-22:

- Selected `gix-protocol/src/fetch/response/mod.rs`, `gix-protocol/src/fetch/response/blocking_io.rs`, `gix-protocol/tests/protocol/fetch/response.rs`, `gix-packetline/src/lib.rs`, `gix-packetline/src/blocking_io/sidebands.rs`, and `gix-packetline/tests/read/sideband.rs` with targeted `git show` and `git grep`.
- 14 fetch response reader tests counted across the V1/V2 `from_line_reader` sections of `gix-protocol/tests/protocol/fetch/response.rs`; 6 packetline sideband tests counted in `gix-packetline/tests/read/sideband.rs`.
- `gix-protocol/src/fetch/response/blocking_io.rs` defines the mapped protocol v2 section semantics: parse `acknowledgments`, `shallow-info`, and `wanted-refs` sections until a delimiter or flush, stop with `has_pack=false` on message end, and stop with `has_pack=true` when the `packfile` section starts.
- `gix-protocol/src/fetch/response/mod.rs` defines the mapped line semantics: `ACK <id>`, `ACK <id> common`, `ACK <id> ready`, `ready`, `NAK`, `shallow <id>`, `unshallow <id>`, and wanted-ref `<id> <path>` lines are parsed into typed response values.
- `gix-packetline/src/lib.rs` and `blocking_io/sidebands.rs` define the mapped sideband semantics: sideband channel 1 carries pack bytes, channel 2 carries progress text with one trailing newline trimmed, and channel 3 carries error text with one trailing newline trimmed.

Focused partial clone/promisor inventory inspected on 2026-05-22:

- Selected `gix-protocol/src/fetch/arguments/mod.rs` and `gix-pack/tests/pack/iter.rs` with targeted `git show`/`git grep`; broad partial-clone scans were stopped because they hydrate too much changelog and fixture history in the filtered checkout.
- 5 `gix-pack` pack iterator tests counted, including `restore_partial_pack`; the 9 fetch argument tests counted in the fetch-negotiation slice also cover the upstream protocol boundary where `filter <spec>` is attached to a fetch request.
- `gix-protocol/src/fetch/arguments/mod.rs` defines the mapped fetch-filter boundary: a filter is sent as `filter <spec>` when the server advertised `filter`.
- Local `git rev-list(1)` and `git clone(1)` documentation were used as the filter-spec reference for `blob:none`, `blob:limit=<n>[kmg]`, `tree:<depth>`, and `sparse:oid=<oid>` semantics. This is Git behavior documentation, not a Gitoxide runner result.
- `gix-pack/tests/pack/iter.rs` includes partial-pack restoration coverage; the PHP slice maps the local object database side by discovering `.promisor` sidecars, reporting promisor-present objects, and distinguishing promised-but-missing object IDs from ordinary missing IDs.

Focused sparse checkout/pathspec inventory inspected on 2026-05-22:

- Selected `gix-index/src/access/sparse.rs`, `gix-index/src/entry/mod.rs`, `gix-index/src/entry/mode.rs`, `gix-pathspec/src/parse.rs`, `gix-pathspec/src/pattern.rs`, `gix-pathspec/tests/parse/valid.rs`, and `gix-pathspec/tests/search/mod.rs` with targeted `git show`; broad `git grep` was stopped once it began hydrating filtered blobs.
- 47 selected gix-index sparse/index and gix-pathspec source/test/fixture paths were listed for this slice.
- 23 `gix-pathspec` search tests and 14 valid parse tests were counted in the selected upstream files.
- `gix-index/src/access/sparse.rs` defines the mapped sparse modes: disabled, cone directory patterns with all entries plus skip-worktree, cone directory patterns with sparse directory entries, and non-cone ignore-pattern matching.
- `gix-pathspec` search and parse tests informed the bounded non-cone fallback, but the PHP slice intentionally prioritizes Git sparse-checkout cone behavior because Git documents non-cone sparse-checkout as deprecated and performance-hostile.
- Local `git sparse-checkout check-rules` and `git sparse-checkout(1)` documentation were used to cross-check the cone-mode WordPress examples: root files are included, files immediately under selected-directory ancestors are included, and all paths under selected directories are included.

Focused lazy promisor inventory inspected on 2026-05-22:

- Selected `gix-protocol/src/fetch/function.rs`, `gix-protocol/tests/protocol/fetch/v2.rs`, `gix/src/remote/connection/fetch/mod.rs`, and `gix/src/remote/connection/fetch/receive_pack.rs` with targeted `git show`.
- 4 protocol v2 fetch tests were counted in `gix-protocol/tests/protocol/fetch/v2.rs`.
- `gix-protocol/src/fetch/function.rs` defines the mapped fetch pack-consumption seam: negotiation eventually hands the pack stream to a caller-provided consumer.
- `gix/src/remote/connection/fetch/receive_pack.rs` defines the mapped repository-level side effect: received pack bytes are written into the object database pack directory when not in dry-run mode.
- The PHP slice maps the local lazy-hydration side for partial clone reads: when the object database has promisor packs and a read misses locally, a `PromisorObjectResolver` can resolve the object, the object ID is verified, and the object is written into loose storage before the read returns.

Runner status:

- `cargo` is available locally.
- Full `cargo test` was not executed because the workspace is large, feature-heavy, and would hydrate/build far beyond the current VM cap.
- Crate-level Cargo tests were not executed in this run because the cache is sparse/no-checkout; running them requires materializing at least the selected crate source paths and building Rust dependencies.
- The next inventory slice should either materialize only the needed protocol/transport crate paths and try a controlled `cargo test -p gix-protocol --no-run --locked --offline` probe before any live runner attempt, or start mapping push protocol request basics.

Current PHP mapping:

- `GitObjectTest.php` maps canonical object header storage, SHA-1 object IDs, loose object zlib storage, and invalid object headers.
- `CommitTest.php` maps basic commit header parsing, parent lists, required header errors, and reading a commit body from native Git object bytes.
- `TreeTest.php` maps `gix-object` tree semantics for empty trees, `everything.tree` entry kinds, entry-mode classification, leading-space filenames, truncated object IDs, malformed modes, tree-object roundtrips, and a WordPress deploy tree fixture.
- `LooseReferenceTest.php` maps `gix-ref` loose direct and symbolic ref parsing, uppercase object ID normalization, SHA-256 object IDs when requested, `FETCH_HEAD` first-OID parsing, trailing hex rejection in SHA-1 mode, symbolic target validation, loose on-disk writes, and a WordPress deploy-branch reference fixture.
- `PackedReferencesTest.php` maps `gix-ref` packed-ref header traits, uppercase and SHA-256 object IDs, peeled object lines, invalid headers/lonely peels, upstream `without-header` and `unsorted` fixtures, packed partial lookup disambiguation, and a WordPress packed branch/tag fixture.
- `ReferenceStoreTest.php` maps loose-over-packed precedence, opening `packed-refs` from a Git directory, loose-only remote `HEAD` shortcuts, capitalized packed branches, and WordPress combined loose+packed ref resolution.
- `PackIndexTest.php` maps `gix-pack` v2 pack-index fanout parsing, monotonic fanout/size/version validation, pack and index checksum access, checksum verification, full object ID lookup, prefix missing/ambiguous/found outcomes, CRC32 entries, 32-bit and large 64-bit offsets, sorted offsets, and a WordPress compacted object-offset fixture.
- `PackDataTest.php` maps `gix-pack` pack header parsing, checksum verification, Git variable-size entry headers, non-delta commit/blob decompression by pack-index offset, OFS_DELTA/REF_DELTA resolution, Git delta copy/insert application, object ID verification, unsupported/corrupt pack errors, direct delta-entry rejection, and a WordPress packed commit/blob/delta fixture.
- `ObjectDatabaseTest.php` maps `gix-odb` pack-before-loose object lookup, packed object counts, prefix lookup across packed and loose objects, duplicate id de-duplication, ambiguous prefix reporting, pack-missing error behavior, pack lexicographic iteration, pack-offset iteration, loose and packed alternates, quoted relative alternate paths, cycle rejection, loose and packed replacement refs, replacement ignore mode, sorted replacement mappings, MIDX-backed pack selection/de-duplication with missing-pack rejection, and WordPress pack+loose+alternate+replacement plus multi-pack object database fixtures.
- `MultiPackIndexTest.php` maps `gix-pack` multi-index v1 header and chunk-table parsing, SHA-1/SHA-256 hash-kind recognition, sorted pack index names, fanout and chunk size validation, full object ID lookup, prefix missing/ambiguous/found outcomes, high-bit raw offsets without `LOFF`, large 64-bit offsets through `LOFF`, checksum verification, fast object-order and pack-id integrity checks, and a WordPress content/template/media multi-pack-index fixture.
- `ProtocolV2Test.php` maps `gix-transport` v1/v2 capability parsing and capability value support, `gix-protocol` `ls-refs` default arguments, `unborn` negotiation, first-seen ref-prefix de-duplication, unknown argument/capability validation errors, v2 remote ref parsing for direct/symbolic/unborn/peeled/symbolic-peeled lines, malformed ref-line errors, and a WordPress protocol v2 `ls-refs` fixture for active branch/release tag/unborn staging ref discovery.
- `FetchNegotiationTest.php` maps `gix-protocol` fetch feature defaults, protocol v2 initial fetch arguments, protocol v1 first-want feature baking, stateless protocol v2 request argument construction, guarded shallow/filter/ref-in-want/deepen/include-tag support, unknown argument/capability validation, and a WordPress shallow blobless ref-in-want fetch fixture.
- `FetchResponseTest.php` maps `gix-protocol` fetch acknowledgements, shallow updates, wanted-ref response lines, required V1 response features, protocol v2 response section parsing, no-pack responses, unknown section errors, sideband channel decoding for pack/progress/error bytes, and a WordPress protocol v2 fetch response fixture.
- `PartialCloneTest.php` maps common fetch filter specs (`blob:none`, `blob:limit`, `tree:<depth>`, `sparse:oid`), `FetchCommand` value-object filter emission, `.promisor` pack sidecar discovery, promisor-present object reporting, promised-missing object state, and a WordPress blobless partial-clone tree where an omitted media blob stays promised rather than ordinary-missing.
- `SparseCheckoutTest.php` maps sparse checkout cone directory matching, cone pattern-file reconstruction, bounded non-cone include/exclude matching, case-insensitive matching, skip-worktree decisions, and WordPress tree-entry filtering for a plugin-focused sparse checkout.
- `PartialCloneTest.php` also maps lazy promisor hydration: a promised-missing object can be resolved through a native resolver, verified against the requested object ID, persisted into loose storage, and then observed as present by a fresh object database.
