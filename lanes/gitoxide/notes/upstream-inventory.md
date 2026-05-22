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

Focused pack generation inventory inspected on 2026-05-22:

- Selected `gix-pack/src/bundle/write/mod.rs`, `gix-pack/src/bundle/write/types.rs`, `gix-pack/src/index/write/mod.rs`, `gix-pack/tests/pack/data/output/count_and_entries.rs`, `gix-pack/tests/pack/data/output/mod.rs`, and `gix-pack/tests/pack/multi_index/write.rs` with targeted `git show`.
- 5 output/write `#[test]` attributes were counted across the selected pack output and multi-index write tests.
- `gix-pack` output tests define the wider generation model: count reachable objects, emit base and delta entries, and produce pack data hashes. The PHP slice starts with deterministic non-delta `commit`/`tree`/`blob`/`tag` object packing because that is enough to hand generated pack bytes to a receive-pack request.
- `gix-pack/src/index/write/mod.rs` defines the mapped generated-index semantics: sort objects by id for the index, write a monotonic fanout table, object IDs, CRC32 values, 32-bit or large offsets, the trailing pack checksum, and the trailing index checksum.

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

Focused push/refspec inventory inspected on 2026-05-22:

- Selected `gix-refspec/tests/refspec/parse/push.rs`, `gix-refspec/src/spec.rs`, `gix-transport/tests/fixtures/v1/push.request`, `gix-transport/tests/fixtures/v1/push.response`, and `gix/src/push.rs` with targeted `git show` and `git ls-tree`.
- 11 push parse `#[test]` attributes were counted in `gix-refspec/tests/refspec/parse/push.rs`.
- `gix-refspec` push tests define the mapped update shapes: `src:dst` updates, `+src:dst` forced updates, `:` matching-branch updates, `:dst` deletions, and excluded refs.
- `gix-transport/tests/fixtures/v1/push.request` defines the mapped receive-pack request envelope: first ref update line carries `old new ref\0` followed by requested capabilities such as `report-status-v2`, `side-band-64k`, `object-format=sha1`, and `agent=...`; subsequent update lines omit capabilities; a flush separates commands from optional push-options and pack bytes.
- `gix-transport/tests/fixtures/v1/push.response` and `gix-transport/tests/client/git.rs::push_v1_simulated` define the mapped receive-pack response envelope: sideband channel 2 carries progress/advisory text, while sideband channel 1 carries nested report-status packet lines such as `unpack ok`, `ok <ref>`, and an inner flush packet.
- 1 simulated push response test was counted in `gix-transport/tests/client/git.rs`.
- Local `gitprotocol-pack(5)` documentation was used to cross-check report-status and report-status-v2 grammar: `unpack <result>`, `ok <ref>`, `ng <ref> <error>`, and v2 `option refname`, `option old-oid`, `option new-oid`, and `option forced-update` lines.

Focused send-pack orchestration inventory inspected on 2026-05-22:

- Selected `gix-transport/tests/client/git.rs`, `gix-transport/src/lib.rs`, `gix-transport/src/client/git/mod.rs`, `gix-protocol/CHANGELOG.md`, and local `gitprotocol-pack(5)` documentation with targeted reads.
- 5 async/blocking client-git tests were counted in `gix-transport/tests/client/git.rs`, including `push_v1_simulated`.
- `gix-transport/src/lib.rs` defines the mapped receive-pack service selector as `Service::ReceivePack`, while the client-git request path establishes the boundary where command packet lines and pack bytes are written to transport I/O.
- `gix-protocol` changelog notes were used as a bounded signal that receive-pack handshakes stay constrained by protocol-v1 behavior in this area. The PHP slice maps the local orchestration layer without claiming full remote adapter parity.
- Local `gitprotocol-pack(5)` documentation remains the grammar cross-check for advertised refs, capability lines, ref update commands, push-options, pack payload separation, and report-status responses.

Focused thin/ref-delta pack generation inventory inspected on 2026-05-22:

- Selected `gix-pack/src/data/output/bytes.rs`, `gix-pack/src/data/output/entry/mod.rs`, `gix-pack/src/data/output/entry/iter_from_counts.rs`, `gix-pack/src/bundle/write/mod.rs`, `gix-pack/src/data/delta.rs`, `gix-pack/tests/pack/data/output/count_and_entries.rs`, `gix-pack/tests/pack/data/input.rs`, `gix-transport/tests/client/git.rs`, `gix-transport/tests/fixtures/v1/push.request`, and local `gitprotocol-pack(5)` receive-pack documentation with targeted reads.
- 13 selected test attributes were counted across the gix-pack data output/input and gix-transport client-git paths inspected for this slice.
- `gix-pack/src/data/output/entry/mod.rs` defines the mapped output-entry distinction: object entries can be base objects, offset deltas, or `RefDelta` entries that identify their base by object ID for thin packs in transit.
- `gix-pack/src/data/output/entry/iter_from_counts.rs` defines the mapped thin-pack guard: `allow_thin_pack` permits ref-delta objects whose base is not included in the pack; otherwise such deltas are repacked as base objects.
- `gix-pack/src/bundle/write/mod.rs` defines the related at-rest repair boundary: a thin-pack base object lookup is used when writing bundles to an object database, reinforcing that thin packs are transit payloads rather than complete packs at rest.
- `gitprotocol-pack(5)` receive-pack documentation was used to cross-check that push data sends update commands followed by a packfile containing the objects the server needs, while receive-pack capabilities include `ofs-delta` rather than a separate `thin-pack` capability.

Focused receive-pack transport I/O inventory inspected on 2026-05-22:

- Selected `gix-transport/src/client/blocking_io/request.rs`, `gix-transport/src/client/git/blocking_io.rs`, `gix-transport/src/client/non_io_types.rs`, `gix-transport/tests/client/git.rs::push_v1_simulated`, local `gitprotocol-pack(5)` git-transport service request documentation, and local `gitprotocol-http(5)` smart receive-pack documentation with targeted reads.
- 5 gix-transport client-git async/blocking tests remain the focused transport count for this slice, including `push_v1_simulated`.
- `gix-transport/src/client/blocking_io/request.rs` defines the mapped request-writer boundary: binary writes are passed through, `into_read()` writes a terminating message and flushes, and `into_parts()` allows direct pack-byte writing before reading the response.
- `gix-transport/src/client/git/blocking_io.rs` defines the mapped connection behavior: a handshake reads capabilities/refs, while `request(WriteMode::Binary, MessageKind::Flush, ...)` yields a request writer and response reader over the same transport.
- `gix-transport/tests/client/git.rs::push_v1_simulated` defines the mapped receive-pack transport flow: write the first command, flush command packet-lines, write the remaining request bytes including pack payload, then parse sideband progress plus nested report-status lines.
- Local `gitprotocol-pack(5)` git-transport documentation defines the mapped git-daemon receive-pack opener: send one pkt-line containing `git-receive-pack`, the repository path, a NUL-delimited `host=` parameter, and optional extra parameters before reading the receive-pack advertisement. The PHP slice now maps that service-request envelope and delegates the rest of the flow through stream-backed receive-pack packet I/O.
- Local `gitprotocol-http(5)` smart HTTP documentation defines the mapped HTTP receive-pack opener: discover refs with `$GIT_URL/info/refs?service=git-receive-pack`, verify the `application/x-git-receive-pack-advertisement` response and `# service=git-receive-pack` pkt-line, then POST the send-pack request to `$GIT_URL/git-receive-pack` with `application/x-git-receive-pack-request` and parse an `application/x-git-receive-pack-result` body. The PHP slice maps that request/response flow, Basic URL userinfo headers, URL expansion for gateway-style query URLs, and Git-Protocol extra parameters.

Focused merge primitive inventory inspected on 2026-05-22:

- Selected `gix-revision/src/merge_base/function.rs`, `gix-revision/tests/revision/merge_base.rs`, `gix-merge/src/tree/function.rs`, `gix-merge/src/tree/mod.rs`, `gix-merge/tests/merge/tree/mod.rs`, and `gix-merge/tests/merge/tree/baseline.rs` with targeted reads.
- 5 merge-focused Rust test attributes were counted across the selected merge-base and tree baseline tests.
- `gix-revision/src/merge_base/function.rs` defines the mapped merge-base shape: paint ancestry from both sides, collect common ancestors, and remove redundant ancestors so the topologically most recent independent bases remain.
- `gix-merge/src/tree/function.rs` and `gix-merge/src/tree/mod.rs` define the much broader tree-merge model: diff both sides against the ancestor tree, apply clean changes to an editor, keep structured conflict entries, and distinguish unresolved tree/content conflicts.
- `gix-merge/tests/merge/tree/mod.rs` drives Git baseline cases through commit-level merge and index conflict comparison. The PHP slice now maps flat tree decisions plus a bounded recursive content-merge path that reads nested tree/blob objects, writes merged blob/tree objects, records full-path content conflicts, exposes ancestor/ours/theirs index stage views, writes blob conflicts into Git index v2 `DIRC` files, expands tree stages into file-level index entries for directory-file conflicts, writes checkout-clean merged worktree files including marker blobs, removes stale files/directories while preserving `.git`, classifies directory-file collisions before generic add-add conflicts, detects exact same-object rename/delete plus rename/rename conflicts with ambiguity guards, detects bounded similar-blob rename/delete plus rename/modify conflicts for text blobs with matching modes and conservative ambiguity filters, merges same-target renamed blobs through the content-merge path at the renamed filename, detects bounded directory renames by descendant similarity so directory rename/modify cases merge at the new directory path, and now matches unique renamed internal leaves so a plugin directory can be detected after its main plugin file is renamed. Broader directory rename cases, further rename heuristics, and full commit merge orchestration remain unported.

Focused blob merge inventory inspected on 2026-05-22:

- Selected `gix-merge/src/blob/builtin_driver/text/function.rs`, `gix-merge/src/blob/builtin_driver/text/mod.rs`, `gix-merge/src/blob/builtin_driver/binary.rs`, and `gix-merge/tests/merge/blob/builtin_driver.rs` with targeted reads.
- 22 blob-focused Rust test attributes were counted across selected `gix-merge` blob source and tests.
- `gix-merge/src/blob/builtin_driver/text/function.rs` defines the mapped text merge shape: compare ours/base/theirs as tokenized lines, render conflict markers, support merge and diff3-style sections, and return a resolution enum.
- `gix-merge/src/blob/builtin_driver/text/mod.rs` defines conflict style labels and default marker size. The PHP slice maps merge and diff3 markers with labels, but does not yet port zealous diff3 hunk contraction or Gitoxide's diff-backend internals.
- `gix-merge/tests/merge/blob/builtin_driver.rs` defines same-change clean merges, partially overlapping conflicts, binary default/side-pick behavior, fuzz regressions, and text-baseline corpus checks. The PHP slice maps the first useful subset with line-based independent edit merging, conflict markers, and minimal binary side picks.

Runner status:

- `cargo` is available locally.
- Full `cargo test` was not executed because the workspace is large, feature-heavy, and would hydrate/build far beyond the current VM cap.
- Crate-level Cargo tests were not executed in this run because the cache is sparse/no-checkout; running them requires materializing at least the selected crate source paths and building Rust dependencies.
- The next inventory slice should either add an SSH receive-pack adapter, add broader directory rename cases or further rename heuristics, or materialize only the needed protocol/transport crate paths and try a controlled `cargo test -p gix-protocol --no-run --locked --offline` probe before any live runner attempt.

Current PHP mapping:

- `GitObjectTest.php` maps canonical object header storage, SHA-1 object IDs, loose object zlib storage, and invalid object headers.
- `CommitTest.php` maps basic commit header parsing, parent lists, required header errors, and reading a commit body from native Git object bytes.
- `TreeTest.php` maps `gix-object` tree semantics for empty trees, `everything.tree` entry kinds, entry-mode classification, leading-space filenames, truncated object IDs, malformed modes, tree-object roundtrips, and a WordPress deploy tree fixture.
- `LooseReferenceTest.php` maps `gix-ref` loose direct and symbolic ref parsing, uppercase object ID normalization, SHA-256 object IDs when requested, `FETCH_HEAD` first-OID parsing, trailing hex rejection in SHA-1 mode, symbolic target validation, loose on-disk writes, and a WordPress deploy-branch reference fixture.
- `PackedReferencesTest.php` maps `gix-ref` packed-ref header traits, uppercase and SHA-256 object IDs, peeled object lines, invalid headers/lonely peels, upstream `without-header` and `unsorted` fixtures, packed partial lookup disambiguation, and a WordPress packed branch/tag fixture.
- `ReferenceStoreTest.php` maps loose-over-packed precedence, opening `packed-refs` from a Git directory, loose-only remote `HEAD` shortcuts, capitalized packed branches, and WordPress combined loose+packed ref resolution.
- `PackIndexTest.php` maps `gix-pack` v2 pack-index fanout parsing, monotonic fanout/size/version validation, pack and index checksum access, checksum verification, full object ID lookup, prefix missing/ambiguous/found outcomes, CRC32 entries, 32-bit and large 64-bit offsets, sorted offsets, and a WordPress compacted object-offset fixture.
- `PackDataTest.php` maps `gix-pack` pack header parsing, checksum verification, Git variable-size entry headers, non-delta commit/blob decompression by pack-index offset, OFS_DELTA/REF_DELTA resolution, Git delta copy/insert application, object ID verification, unsupported/corrupt pack errors, direct delta-entry rejection, and a WordPress packed commit/blob/delta fixture.
- `PackBuilderTest.php` maps deterministic non-delta pack generation for native Git objects, generated v2 pack-index bytes, empty pack generation for already-present push objects, multi-byte entry headers, REF_DELTA generation for similar same-type objects, thin REF_DELTA generation against remote bases, generated pack handoff to `PushCommand`, and a WordPress receive-pack branch/tag request with native pack bytes.
- `ObjectDatabaseTest.php` maps `gix-odb` pack-before-loose object lookup, packed object counts, prefix lookup across packed and loose objects, duplicate id de-duplication, ambiguous prefix reporting, pack-missing error behavior, pack lexicographic iteration, pack-offset iteration, loose and packed alternates, quoted relative alternate paths, cycle rejection, loose and packed replacement refs, replacement ignore mode, sorted replacement mappings, MIDX-backed pack selection/de-duplication with missing-pack rejection, and WordPress pack+loose+alternate+replacement plus multi-pack object database fixtures.
- `MultiPackIndexTest.php` maps `gix-pack` multi-index v1 header and chunk-table parsing, SHA-1/SHA-256 hash-kind recognition, sorted pack index names, fanout and chunk size validation, full object ID lookup, prefix missing/ambiguous/found outcomes, high-bit raw offsets without `LOFF`, large 64-bit offsets through `LOFF`, checksum verification, fast object-order and pack-id integrity checks, and a WordPress content/template/media multi-pack-index fixture.
- `ProtocolV2Test.php` maps `gix-transport` v1/v2 capability parsing and capability value support, `gix-protocol` `ls-refs` default arguments, `unborn` negotiation, first-seen ref-prefix de-duplication, unknown argument/capability validation errors, v2 remote ref parsing for direct/symbolic/unborn/peeled/symbolic-peeled lines, malformed ref-line errors, and a WordPress protocol v2 `ls-refs` fixture for active branch/release tag/unborn staging ref discovery.
- `FetchNegotiationTest.php` maps `gix-protocol` fetch feature defaults, protocol v2 initial fetch arguments, protocol v1 first-want feature baking, stateless protocol v2 request argument construction, guarded shallow/filter/ref-in-want/deepen/include-tag support, unknown argument/capability validation, and a WordPress shallow blobless ref-in-want fetch fixture.
- `FetchResponseTest.php` maps `gix-protocol` fetch acknowledgements, shallow updates, wanted-ref response lines, required V1 response features, protocol v2 response section parsing, no-pack responses, unknown section errors, sideband channel decoding for pack/progress/error bytes, and a WordPress protocol v2 fetch response fixture.
- `PartialCloneTest.php` maps common fetch filter specs (`blob:none`, `blob:limit`, `tree:<depth>`, `sparse:oid`), `FetchCommand` value-object filter emission, `.promisor` pack sidecar discovery, promisor-present object reporting, promised-missing object state, and a WordPress blobless partial-clone tree where an omitted media blob stays promised rather than ordinary-missing.
- `SparseCheckoutTest.php` maps sparse checkout cone directory matching, cone pattern-file reconstruction, bounded non-cone include/exclude matching, case-insensitive matching, skip-worktree decisions, and WordPress tree-entry filtering for a plugin-focused sparse checkout.
- `PartialCloneTest.php` also maps lazy promisor hydration: a promised-missing object can be resolved through a native resolver, verified against the requested object ID, persisted into loose storage, and then observed as present by a fresh object database.
- `PushCommandTest.php` maps protocol v1 receive-pack update commands, create/update/delete ref lines, first-line capability negotiation, `atomic` and `push-options` guards, command packet-line framing before pack bytes, and a WordPress branch/tag deployment push request fixture.
- `PushResponseTest.php` maps receive-pack report-status parsing, sideband progress/error extraction, nested sideband channel 1 report-status packet lines, accepted and rejected ref statuses, unpack failures, report-status-v2 rewritten-ref options, malformed response guards, and a WordPress branch/tag deployment push status fixture.
- `SendPackSessionTest.php` maps receive-pack advertisement parsing, advertised-old-object create/update/delete planning, no-op update elision, generated pack request construction, delete-only request behavior, thin REF_DELTA request building from remote bases, session response parsing, a WordPress branch/tag send-pack fixture from advertised refs through status response, and a WordPress thin REF_DELTA send-pack fixture.
- `ReceivePackTransportTest.php` maps stream-backed receive-pack advertisement/request/response I/O, git-daemon receive-pack service request bytes and URL/input validation, smart HTTP receive-pack discovery/result requests, service advertisement stripping, URL expansion, content-type/status guards, Basic auth header derivation, Git-Protocol extra parameters, sideband and direct report-status response selection from negotiated features, request write ordering guards, truncated packet stream errors, no-report-status refusal, and a WordPress receive-pack transport fixture over native PHP streams.
- `MergeBaseTest.php` maps simple commit ancestry merge-base discovery, independent criss-cross merge bases, unrelated histories, and ObjectDatabase commit-object validation.
- `TreeMergeTest.php` maps flat tree three-way decisions for independent WordPress tree changes, modify/modify conflicts, delete/delete removals, delete/modify conflicts, exact same-object rename/delete and rename/rename conflicts, bounded similar-blob rename/delete and rename/modify conflicts, same-target similar-rename content merges at the renamed path, bounded directory rename/modify merges at the renamed directory path including renamed internal plugin entry file heuristics, ambiguous exact/similar-rename guards, add/add resolution/conflicts, directory-file conflict classification, deterministic path ordering, duplicate-entry guards, recursive tree traversal over nested tree objects, clean nested blob content merges, full-path recursive content conflicts with marker blobs, nested exact rename/delete conflicts, merge-index stage entries, and worktree conflict file views.
- `MergeWorktreeWriterTest.php` maps the bounded checkout side of recursive content conflicts: unmerged blob stages are written to a real Git index v2 `DIRC` file with stage bits and checksum, directory/file tree stages are expanded into file-level index entries, merged WordPress worktree files are written from tree objects, stale paths are removed while `.git` metadata is preserved, file/directory blockers are replaced, marker blobs are materialized for conflicted `theme.json`, unsafe checkout paths are rejected, and raw tree stages are explicitly refused unless callers use the result-aware expansion path.
- `BlobMergeTest.php` maps text same-change and one-sided clean merges, independent line edits, merge-style and diff3 conflict markers, binary unresolved default picks, binary auto-resolved side picks, and a WordPress metadata/theme merge fixture.
