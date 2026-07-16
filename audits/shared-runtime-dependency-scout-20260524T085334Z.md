# Shared Runtime Dependency Scout - 2026-05-24T085334Z

Scope: audit-only scout pass for non-document shared support-library coverage across esbuild, LightningCSS, rclone, Syncthing, Gitoxide, Difftastic, libsqlite, Dolt, and Quadrable. I did not edit lane implementation files, lane status/manifest files, `dependency-backlog.json`, `progress.md`, `porting.html`, or `porting-summary.json`. I did not stage, commit, push, reset, revert, inspect credentials, inspect provider configs, or run live-service/provider tests.

## Current Tracker Coverage Summary

`dependency-backlog.json` currently has 29 gated support-library rows: 19 `candidate`, 10 `deferred`; 4 `critical`, 17 `high`, and 8 `medium`. Eighteen rows touch at least one of the nine non-document lanes in this audit.

Lane coverage from the current backlog:

- esbuild: `unicode-text-repair-width`, `charset-encoding-core`, `json-json5-document-core`, `source-map-v3-core`, `browser-compat-target-data-core`, `js-package-resolution-core`, `tree-sitter-grammar-subset`, `glob-filter-pathspec-core`.
- LightningCSS: `unicode-text-repair-width`, `charset-encoding-core`, `source-map-v3-core`, `browser-compat-target-data-core`, `js-package-resolution-core`, `tree-sitter-grammar-subset`.
- rclone: `shared-zip-package-core`, `xml-html5-dom-core`, `webdav-protocol-core`, `epub3-package-core`, `charset-encoding-core`, `json-json5-document-core`, `checksum-hash-suite`, `archive-compression-streams`, `glob-filter-pathspec-core`, `provider-metadata-normalization-core`.
- Syncthing: `json-json5-document-core`, `protobuf-wire-core`, `checksum-hash-suite`, `archive-compression-streams`, `glob-filter-pathspec-core`, `provider-metadata-normalization-core`.
- Gitoxide: `unicode-text-repair-width`, `charset-encoding-core`, `checksum-hash-suite`, `archive-compression-streams`, `glob-filter-pathspec-core`.
- Difftastic: `xml-html5-dom-core`, `unicode-text-repair-width`, `charset-encoding-core`, `source-map-v3-core`, `tree-sitter-grammar-subset`, `glob-filter-pathspec-core`.
- libsqlite: `json-json5-document-core`, `sql-storage-codec-core`, `sql-expression-semantics-core`.
- Dolt: `unicode-text-repair-width`, `charset-encoding-core`, `json-json5-document-core`, `checksum-hash-suite`, `sql-storage-codec-core`, `sql-expression-semantics-core`.
- Quadrable: `unicode-text-repair-width`, `charset-encoding-core`, `checksum-hash-suite`, `sql-storage-codec-core`.

Rows that are sufficient and should not be duplicated:

- `webdav-protocol-core` is the right bounded rclone row for local-only WebDAV PROPFIND/PROPPATCH/LOCK/If/COPY/MOVE/GET/PUT/gzip behavior. Do not add separate per-method WebDAV rows.
- `json-json5-document-core` already covers libsqlite JSON/JSON5, esbuild JSON loader/preflight, rclone/Syncthing payloads, Dolt JSON scalars, and shared diagnostics. Do not add separate JSON rows per lane.
- `source-map-v3-core`, `browser-compat-target-data-core`, and `js-package-resolution-core` are adequate for esbuild/LightningCSS bundler-support reuse. Keep target data and package resolution deferred until those concrete slices open.
- `tree-sitter-grammar-subset` is sufficient as a grammar/query compatibility row as long as activation stays pinned to one concrete language family and never becomes a parser-generator/runtime port.
- `protobuf-wire-core` is sufficient for Syncthing BEP serialization if it stays hand-written wire/message support and excludes `protoc`, reflection/runtime generation, and gRPC.
- `sql-expression-semantics-core` is the right shared row for Dolt query-diff scalar semantics and future libsqlite SQL execution slices. Do not duplicate it with per-function rows.

## Recommended Additions

Add only the rows below. They close real cross-lane gaps found in local manifests/status files and are smaller than whole tools or protocol applications.

### 1. `url-percent-encoding-core`

- id: `url-percent-encoding-core`
- neededBy lanes: `rclone`, `gitoxide`, `esbuild`, `lightningcss`
- essential capability: Shared URL/path escaping and resolution for WebDAV `href`/`Destination`/tagged `If` handling, Git remote URL parsing/rendering, esbuild `new URL(..., import.meta.url)` asset references, and LightningCSS `url()`/`@import` token handling without calling browser, Node, Git, rclone, or provider runtimes.
- scope boundary: In scope: RFC 3986 path/query/fragment percent encoding and decoding, path-segment escaping, dot-segment normalization where a caller explicitly asks for it, relative URL resolution for asset references, file URL/path boundaries, Go `net/url` `EscapedPath` compatibility cases needed by x/net WebDAV, and deterministic diagnostics. Out of scope: HTTP clients, DNS, TLS, cookies, redirects, OAuth, live network access, browser navigation, package fetching, provider APIs, and full WHATWG browser URL engine parity beyond fixture-backed cases.
- activation gate: `rclone-webdav-filename-destination-next-or-gitoxide-url-parse-next-or-esbuild-new-url-asset-next-or-lightningcss-css-url-token-next`
- upstream/spec denominator: RFC 3986 plus targeted WHATWG URL Standard cases; Go `net/url` and `golang.org/x/net/webdav` `TestFilenameEscape`/`TestEscapeXML` evidence already referenced by the rclone manifest; `gix-url` generated/fuzzed baseline evidence in Gitoxide manifests; esbuild `new URL(import.meta.url)` asset cases; LightningCSS URL/import token fixtures.
- expected PHP evidence: A dependency-specific vector manifest with PHP pass/fail counts for percent-escape round trips, path-segment preservation, relative asset resolution, local WebDAV href serialization, Git remote URL parse/render cases, JS/CSS asset URL token cases, and bounded upstream runner/static evidence where available.
- malformed/corrupt cases: Bad percent triplets, stray `%`, encoded slash policy boundaries, CR/LF header injection attempts, NUL bytes, invalid UTF-8, overlong UTF-8, path traversal/dot-segment ambiguity, Windows drive/path ambiguity, and unsupported schemes.
- reuse notes: Keep this below `webdav-protocol-core`, `js-package-resolution-core`, `source-map-v3-core`, rclone provider paths, and Gitoxide URL/refspec adapters. It should supply bytes/URL helpers only; each lane keeps its own protocol/application semantics.
- explicit exclusions: No shell-out to Git/rclone/Node/browsers; no live HTTP requests; no live provider remotes; no credential-bearing URLs; no OAuth/browser auth state; no whole browser URL/runtime implementation.

### 2. `sequence-diff-merge-core`

- id: `sequence-diff-merge-core`
- neededBy lanes: `difftastic`, `gitoxide`, `dolt`, `quadrable`
- essential capability: Shared native edit-script and hunk-building primitives for structural diffs, Git text/content merge previews, Dolt row/table diff rendering, and Quadrable command diff/patch output without shelling out to upstream CLIs.
- scope boundary: In scope: bounded Myers/patience/histogram-style sequence algorithms where fixture-backed, stable edit opcodes, hunk grouping, intraline token handoff hooks, deterministic tie-breaking, binary/text boundary adapters, and small merge-base helper outputs needed by review UIs. Out of scope: full Difftastic language parsing, full Git merge machinery, Dolt SQL engines, Quadrable sparse-tree storage, rename detection beyond fixture-backed hooks, HTML rendering frameworks, and whole application command ports.
- activation gate: `difftastic-diff-algorithm-next-or-gitoxide-content-merge-diff-next-or-dolt-row-diff-render-next-or-quadrable-diff-patch-next`
- upstream/spec denominator: Difftastic display/diff fixtures and exact parser config evidence; Gitoxide `gix-diff`/merge text-conflict fixtures and any upstream algorithm runner evidence the lane can run; Dolt diff/schema/table/query-diff fixture rows; Quadrable `check.cpp` `diff`/`patch` command-output scenarios. Record each upstream family separately instead of inventing one fake denominator.
- expected PHP evidence: PHP pass/fail counts for edit-script vectors, equal/insert/delete/replace hunks, repeated-line tie-breakers, binary fallback decisions, structural-token handoff from Difftastic, Git conflict label/hunk cases, Dolt row-diff output rows, and Quadrable diff/patch transcript parity.
- malformed/corrupt cases: Invalid UTF-8 delegated through `charset-encoding-core`, NUL-containing binary payloads, huge repeated lines/tokens, empty inputs, unbalanced or inconsistent opcode streams, malformed hunk headers, unsupported token-tree adapters, and resource-limit fail-closed behavior.
- reuse notes: Use `charset-encoding-core` and `unicode-text-repair-width` for text boundaries; use `tree-sitter-grammar-subset` only to provide token trees. Keep rendering and lane-specific diagnostics outside the core.
- explicit exclusions: No `git diff`, `dolt diff`, `difft`, or `quadb diff` shell-outs as progress; no parser-generator runtime; no whole Difftastic/Git/Dolt/Quadrable application port; no live repositories or provider remotes.

## Recommended Priority And Gate Changes

Do not activate all dependency projects at once. Recommended order:

1. Open `url-percent-encoding-core` first if the next accepted rclone WebDAV edge touches href/Destination/If URL escaping, or if Gitoxide/esbuild/LightningCSS next selects URL parsing. Otherwise leave it candidate-only.
2. Keep using existing `webdav-protocol-core` for rclone local-only WebDAV; it has the strongest current base-lane evidence and should not be split.
3. Promote and sharpen `glob-filter-pathspec-core` when any one of rclone filters, Syncthing ignores, Gitoxide pathspecs, esbuild entry globs, or Difftastic file overrides becomes the next accepted slice.
4. Open `archive-compression-streams` only for a concrete rclone gzip/archive, Syncthing LZ4, Gitoxide pack deflate, or package-compression blocker.
5. Open `sequence-diff-merge-core` only when the next Difftastic/Gitoxide/Dolt/Quadrable diff/merge slice would otherwise grow another lane-local algorithm.
6. Keep `browser-compat-target-data-core`, `js-package-resolution-core`, `provider-metadata-normalization-core`, and `sql-storage-codec-core` deferred until their exact base-lane gates open.

Changed row recommendations:

### `glob-filter-pathspec-core`

- id: `glob-filter-pathspec-core`
- neededBy lanes: `rclone`, `syncthing`, `gitoxide`, `esbuild`, `difftastic`
- essential capability: Shared include/exclude/pathspec semantics for rclone filters, Syncthing ignore rules, Git sparse/pathspec matching, esbuild entry/filter matching, and Difftastic file-selection overrides.
- scope boundary: In scope: glob syntax, gitignore/pathspec-style rules, rooted/recursive matching, include/exclude precedence, case-sensitivity options, slash/path normalization, and lane adapters for upstream-specific diagnostics. Out of scope: filesystem watchers, shell glob expansion, arbitrary regex engines, live provider traversal, and secret-bearing path roots.
- activation gate: replace vague `shared-infra-after-base-green` with `rclone-filter-next-or-syncthing-ignore-next-or-gitoxide-pathspec-next-or-esbuild-entry-glob-next-or-difftastic-override-path-next`
- upstream/spec denominator: rclone `fs/filter` tests, Syncthing ignore matcher tests, Gitoxide `gix-pathspec`/`gix-ignore` generated baselines, esbuild entry/filter fixtures, and Difftastic config/file override fixtures.
- expected PHP evidence: Cross-lane fixture matrix with PHP pass/fail counts, per-dialect parser evidence, malformed pattern/path diagnostics, traversal guards, case-folding/case-sensitive pairs, and bounded upstream runner/static evidence for the selected dialect.
- malformed/corrupt cases: Unterminated character classes, invalid escapes, recursive wildcard ambiguity, absolute/rooted path confusion, `..` traversal, NUL bytes, invalid byte sequences, and platform separator mismatches.
- reuse notes: Put this below rclone provider walks, Syncthing scans, Gitoxide sparse checkout/pathspecs, esbuild package/entry resolution, and Difftastic file filters. Keep dialect adapters explicit.
- explicit exclusions: No shelling out to Git/rclone/shell glob engines; no live provider listing; no filesystem watcher runtime; no package manager or parser generator.
- priority/status recommendation: raise from `deferred`/`medium` to `candidate`/`high` once one concrete dialect slice is selected; until then keep inactive.

### `checksum-hash-suite`

- id: `checksum-hash-suite`
- neededBy lanes: `rclone`, `gitoxide`, `dolt`, `quadrable`, `syncthing`, `markerpdf`
- essential capability: Shared streaming integrity checks for object stores, sync manifests, Git objects/packs, Dolt content hashes, Quadrable BLAKE2s keys, Syncthing blocks, and package/benchmark artifacts.
- scope boundary: In scope: official-vector-backed CRC32/CRC32C, MD5/SHA compatibility modes, BLAKE2s, QuickXorHash, streaming/chunked updates, manifest formatting helpers, and constant-time comparisons where relevant. Out of scope: TLS, password hashing, authentication protocols, secret handling, new crypto design, and unbounded cryptographic suites.
- activation gate: replace vague `shared-infra-after-base-green` with `rclone-checksum-next-or-gitoxide-object-hash-next-or-dolt-content-hash-next-or-quadrable-blake2s-next-or-syncthing-block-hash-next`
- upstream/spec denominator: Official algorithm vectors plus rclone hash/QuickXorHash evidence, Gitoxide object hash evidence, Dolt hash/scalar fixtures, Quadrable BLAKE2s runner evidence, and Syncthing block-hash fixtures.
- expected PHP evidence: Per-algorithm PHP pass/fail counts, streaming vs one-shot parity, large/chunked payload vectors, manifest parse/render cases, selected lane fixtures, and honest notes for algorithms backed only by static evidence.
- malformed/corrupt cases: Truncated streams, wrong digest lengths, invalid hex/base64 encodings, mismatched declared algorithms, empty payload boundaries, binary payloads with NUL bytes, and unsupported algorithm names.
- reuse notes: Centralize low-level hash code, but keep lane-specific object IDs, security policy, and digest display formats in adapters.
- explicit exclusions: No shell-out to hash utilities; no TLS/password/auth protocol work; no secret material; no live provider reads.
- priority/status recommendation: keep `candidate`/`high`, but require the concrete gate above before any progress credit.

### `archive-compression-streams`

- id: `archive-compression-streams`
- neededBy lanes: `rclone`, `syncthing`, `pandoc`, `markerpdf`, `gitoxide`
- essential capability: Shared deterministic gzip/deflate/tar/LZ4 stream helpers for rclone local WebDAV/archive responses, Syncthing compressed BEP metadata, Git pack-adjacent deflate, and package/archive fixture handling.
- scope boundary: In scope: streaming gzip/deflate wrappers, tar/PAX metadata where fixture-backed, LZ4 block/frame handling where Syncthing fixtures require it, HTTP content-encoding gzip contracts, safe path extraction checks, deterministic writer output, and local generated-fixture readback. Out of scope: every archive format, encrypted archives, external decompressor wrappers, FUSE/mount behavior, OS package tools, and model/runtime compression stacks.
- activation gate: sharpen from `rclone-webdav-gzip-or-archive-next-or-pandoc-package-compression-next` to `rclone-webdav-gzip-or-archive-next-or-syncthing-lz4-bep-next-or-gitoxide-pack-deflate-next-or-pandoc-package-compression-next`
- upstream/spec denominator: gzip/deflate/tar/LZ4 specs plus rclone gzip/archive evidence, Syncthing LZ4/BEP evidence, Gitoxide pack/deflate evidence, and package fixture evidence where package rows depend on it.
- expected PHP evidence: PHP pass/fail counts for streaming read/write, bounded writer output, corrupt/truncated frame handling, HTTP gzip response parity, LZ4 block/frame vectors, tar path safety, and local interoperability readback without external tools.
- malformed/corrupt cases: Bad checksums, truncated streams, invalid block sizes, zip-slip/tar traversal names, malformed PAX headers, decompression bombs bounded by limits, extra bytes, and empty streams.
- reuse notes: Keep ZIP package specifics in `shared-zip-package-core`; share only lower-level compression/path-safety primitives here.
- explicit exclusions: No shelling out to `tar`, `gzip`, `lz4`, `git`, rclone, or archive tools; no live remotes; no FUSE; no encrypted archive suite.
- priority/status recommendation: raise priority from `medium` to `high`, but keep inactive until one concrete gate opens.

### `provider-metadata-normalization-core`

- id: `provider-metadata-normalization-core`
- neededBy lanes: `rclone`, `syncthing`
- essential capability: Local-only normalization of object, directory, timestamp, hash, case, tier, device, folder, and config metadata for sync decisions and review payloads without live provider credentials.
- scope boundary: In scope: local provider object schemas, path/case normalization, timestamp precision, content-type/storage-tier fields, synthetic directory metadata, provider IDs/parent IDs, Syncthing device/folder/config projections, and sanitized deterministic output. Out of scope: live cloud providers, OAuth, tenant APIs, network remotes, secret-bearing configs, remote mutation, and cloud application wrappers.
- activation gate: sharpen from `rclone-provider-normalization-next` to `rclone-provider-normalization-next-or-syncthing-device-folder-metadata-next`
- upstream/spec denominator: rclone provider contract/local memory-provider evidence, rclone metadata mapper and OneDrive normalization fixtures, plus Syncthing device/folder/config metadata evidence in manifests.
- expected PHP evidence: PHP pass/fail counts for object/directory metadata, path/case/time/tier/hash parity, sanitized device/folder projections, malformed metadata, traversal rejection, and no-live-service fixture-only provider matrices.
- malformed/corrupt cases: Invalid timestamps, impossible hash metadata, duplicate provider IDs, parent-cycle metadata, case collisions, traversal names, unsupported tier values, malformed device/folder IDs, and redaction failures.
- reuse notes: Reuse `charset-encoding-core`, `unicode-text-repair-width`, `checksum-hash-suite`, `glob-filter-pathspec-core`, and `url-percent-encoding-core` once present.
- explicit exclusions: No rclone/Syncthing shell-outs; no live provider/service remotes; no credential/config inspection; no OAuth; no FUSE; no Docker-backed provider suites.
- priority/status recommendation: keep `deferred`/`medium` until a local-only provider/config normalization blocker appears.

### `sql-storage-codec-core`

- id: `sql-storage-codec-core`
- neededBy lanes: `dolt`, `libsqlite`, `quadrable`
- essential capability: Bounded row/value/key/page/chunk codecs for Dolt storage slices, libsqlite record/page writing, and Quadrable raw store/proof dump compatibility without external database engines.
- scope boundary: In scope: SQL scalar value encodings, SQLite record/page payload handoff, Dolt/Prolly/Noms-style mapped chunk metadata, Quadrable key/varint/raw-store codecs, binary-safe ordering, and portable dump/restore codec helpers. Out of scope: full SQL optimizers, query planners, transaction engines, LMDB engine porting, MySQL/SQLite/Dolt servers, network protocols, and shelling out to database CLIs.
- activation gate: sharpen from `dolt-storage-next-or-libsqlite-write-next-or-quadrable-store-codec-next` to `dolt-prolly-row-codec-next-or-libsqlite-record-page-write-next-or-quadrable-raw-store-codec-next`
- upstream/spec denominator: SQLite file-format specs and libsqlite manifest evidence, Dolt/prolly storage fixture evidence, Quadrable raw LMDB/dump/restore oracle evidence. Track sub-denominators separately.
- expected PHP evidence: PHP pass/fail counts for each selected subfamily, row/chunk/page/key/value parity, binary fixture readback, malformed storage handling, and explicit notes for fixture-only slices.
- malformed/corrupt cases: Truncated cells/pages/chunks, invalid varints, inconsistent payload lengths, bad checksums, impossible key ordering, overflow references, page/chunk cycles, and non-UTF-8/binary value preservation.
- reuse notes: Share only byte/codec primitives and ordering helpers; keep SQL expression semantics in `sql-expression-semantics-core` and each storage engine's policy in lane adapters.
- explicit exclusions: No `sqlite3`, `dolt`, `mysql`, LMDB tools, database servers, or network services as progress; no full storage engine port hidden inside the support row.
- priority/status recommendation: keep `deferred`/`medium` until one exact storage-codec gate opens.

## Explicit Rejects

These must not be added or counted as support-library progress:

- Live services and provider integrations: cloud remotes, OAuth flows, tenant APIs, browser auth state, credential-bearing configs, provider TestIntegration suites, FUSE mounts, Docker-backed provider suites, and network remotes.
- External CLIs and shell-outs: `git`, `dolt`, `sqlite3`, `mysql`, `rclone`, `syncthing`, `difft`, `quadb`, Node/npm/yarn/pnpm, esbuild/LightningCSS CLIs, Browserslist CLI, archive tools, decompressor tools, hash utilities, and office/converter executables.
- Parser-generator runtimes: tree-sitter/Cargo parsers, parser generators, generated grammar runtimes, protoc/gRPC runtimes, and broad language parser engines. Fixture-backed grammar/query compatibility is acceptable only under `tree-sitter-grammar-subset`.
- Whole applications: rclone/Syncthing daemons, Git/Dolt servers, browser engines, package managers, cloud/provider applications, full DB engines, full Difftastic, full Quadrable command suite as a dependency row, and any dashboard/GUI/service wrapper.
- Over-broad abstractions: generic Merkle-tree, generic database, generic HTTP-client, generic crypto, or generic filesystem watcher rows unless a later audit names a small upstream/spec denominator and concrete base-lane gate.

## Exact Local Files Inspected

- `goal.md`
- `progress.md`
- `dependency-backlog.json`
- `audits/support-library-progress-tracker-20260524T083724Z.md`
- `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json`
- `lanes/esbuild/lane-status.json`
- `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json`
- `lanes/lightningcss/lane-status.json`
- `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`
- `lanes/rclone/lane-status.json`
- `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`
- `lanes/syncthing/lane-status.json`
- `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`
- `lanes/gitoxide/lane-status.json`
- `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`
- `lanes/difftastic/lane-status.json`
- `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json`
- `lanes/libsqlite/lane-status.json`
- `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`
- `lanes/dolt/lane-status.json`
- `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json`
- `lanes/quadrable/lane-status.json`

## Checks Run

- `jq empty dependency-backlog.json lanes/esbuild/UPSTREAM_TEST_MANIFEST.json lanes/esbuild/lane-status.json lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json lanes/lightningcss/lane-status.json lanes/rclone/UPSTREAM_TEST_MANIFEST.json lanes/rclone/lane-status.json lanes/syncthing/UPSTREAM_TEST_MANIFEST.json lanes/syncthing/lane-status.json lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json lanes/gitoxide/lane-status.json lanes/difftastic/UPSTREAM_TEST_MANIFEST.json lanes/difftastic/lane-status.json lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json lanes/libsqlite/lane-status.json lanes/dolt/UPSTREAM_TEST_MANIFEST.json lanes/dolt/lane-status.json lanes/quadrable/UPSTREAM_TEST_MANIFEST.json lanes/quadrable/lane-status.json`: passed with exit 0 and no output.
- `git diff --check -- audits/shared-runtime-dependency-scout-20260524T085334Z.md`: passed with exit 0 and no output.

## Unresolved Blockers

- No blocker to writing this audit.
- I did not update `dependency-backlog.json`; all additions and gate/priority changes above are recommendations only.
- I did not run root tests, dashboard generation, live-service tests, provider tests, or broad upstream runners.
