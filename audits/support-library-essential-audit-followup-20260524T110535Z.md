# Support Library Essential Audit Follow-up - 2026-05-24T11:14Z

Scope: apply the bounded follow-ups from
`audits/essential-dependency-audit-20260524T105746Z.md` after the previous
support tracker integration completed. This artifact records tracker changes
only; it does not activate support-library work.

## Coordination

- Waited for `port-support-tracker-integrator-20260524T110423Z` until its log
  reported commit outcome `1e4d30e2 Record support dependency routing`.
- Took two file-state polls at least 20 seconds apart for
  `dependency-backlog.json`, `progress.md`, and
  `audits/support-library-essential-dependency-routing-20260524T105940Z.md`.
  Sizes, mtimes, hashes, and scoped status stayed stable across both polls.
- Read the requested tracker, audit, Gitoxide, Quadrable, and Difftastic
  context before editing.

Safety boundary observed: no process environments, credential stores, provider
configs, OAuth/browser auth state, cloud remotes, live-service provider tests,
secret-bearing inputs, lane sources, lane manifests, lane statuses, dashboard
artifacts, prompt files, logs outside the required integrator handoff, staging,
commit, push, root harness, or dashboard regeneration were changed.

## Before / After

- Before: 34 dependency rows; statuses were `blocked: 1`, `candidate: 22`,
  `deferred: 11`; priorities were `critical: 4`, `high: 24`, `medium: 6`;
  active support rows: 0.
- After: 36 dependency rows; statuses are `blocked: 1`, `candidate: 24`,
  `deferred: 11`; priorities are `critical: 4`, `high: 26`, `medium: 6`;
  active support rows: 0.
- Rows added: 2.
- Rows refined: 1.
- Rows activated: 0.

## Rows Added

### `git-wire-protocol-core`

- Status: `candidate`.
- Priority: `high`.
- Needed by: `gitoxide`.
- Activation gate:
  `gitoxide-protocol-v2-pktline-next-or-gitoxide-receive-pack-wire-next-or-gitoxide-upload-pack-wire-next`.
- Scope added: pkt-line length framing, flush/delim/response-end packets,
  protocol v1/v2 request and response framing, capability negotiation and
  advertisement, sideband channel splitting, ref advertisement, fetch and
  receive-pack transcript boundaries, shallow/wanted-ref/packfile section
  envelopes, and deterministic fake-transport byte transcripts.
- Malformed/error coverage required: bad packet lengths, truncation,
  overlong lengths, missing flush, unexpected delimiters, illegal sideband
  channels, duplicate capabilities, and out-of-order sections.
- Explicit exclusions: live remotes, Git CLI shell-outs, SSH/HTTP
  credentials, network listeners/services, credential helpers/stores,
  secret-bearing configs, pack-object storage, and full receive-pack or
  upload-pack server implementations.

Rationale: Gitoxide already had URL/hash/archive-adjacent rows, but no bounded
protocol row for pkt-line, sideband, capability, fetch, or receive-pack wire
contracts. Current evidence is useful but still lane-local/pending, so this
row remains inactive.

### `quadrable-proof-transport-codec-core`

- Status: `candidate`.
- Priority: `high`.
- Needed by: `quadrable`.
- Activation gate:
  `quadrable-proof-transport-codec-next-or-quadrable-sync-codec-next`.
- Scope added: proof command encoding/decoding, proof strand command jumps,
  BER/base-128 varints, compressed key-hash paths, FullKeys/HashedKeys proof
  encodings, sync request/response frames, proof import/export byte contracts,
  canonical byte rendering, and deterministic diagnostics.
- Malformed/error coverage required: malformed varints, frames, opcodes,
  jumps, roots, counts, truncation, and mismatched-root diagnostics.
- Explicit exclusions: LMDB/database engines, network sync services, `quadb`
  shell-out progress, unrelated SQL/page codecs, BLAKE2s implementation,
  whole `quadb` application behavior, and database servers.

Rationale: Quadrable proof/sync transport bytes are distinct from hash and
storage codec rows. The new row keeps that boundary explicit and prevents
proof transport work from being hidden under `sql-storage-codec-core`.

## Row Refined

### `tree-sitter-grammar-subset`

- Status kept as `candidate`.
- Priority kept as `high`.
- `neededBy` changed from `["difftastic", "esbuild", "lightningcss"]` to
  `["difftastic"]`.
- Scope and blocker text now make the row Difftastic-first, one language family
  at a time, with explicit non-goals for parser-generator/runtime completeness,
  Cargo parser execution, esbuild JS parser replacement, LightningCSS CSS
  parser replacement, and syntax-highlighter application ports.
- esbuild and LightningCSS remain in reuse notes only until an explicit shared
  structural-review-span gate opens.

## Rows Intentionally Not Touched

- Pandoc DOC/DOCX/PDF/EPUB/ODT/doctemplate/citation/math/table/ZIP/XML/HTML/
  Unicode/charset/JSON/archive rows remained unchanged because the previous
  routing audit already found them covered at bounded component granularity.
- markerPDF PDF text/page/OCR/table rows remained unchanged.
- rclone WebDAV, URL, provider-metadata, ZIP/package, and archive rows
  remained unchanged.
- Syncthing `protobuf-wire-core` and blocked `qr-code-matrix-core` remained
  unchanged.
- Dolt `mysql-wire-protocol-core`, `sql-expression-semantics-core`, and
  `sql-storage-codec-core` remained unchanged.
- `checksum-hash-suite` remained unchanged; no separate BLAKE2s row was added.
- No row was marked active.

## Validation Run

Completed from `/home/claude/port-libs`:

- `jq empty dependency-backlog.json`
- duplicate dependency id check
- required key check for every dependency item
- count/status/priority summary
- `git diff --check -- dependency-backlog.json progress.md audits/support-library-essential-audit-followup-20260524T110535Z.md`
- no-index whitespace check for this audit artifact while untracked

## Remaining Activation Gates

- Freeze active writers/status publishers and take a stable source snapshot.
- Accept one coherent base-lane batch from that frozen snapshot, or mark a lane
  accepted-blocked on exactly one support component.
- For `git-wire-protocol-core`, require a Git protocol-specific denominator,
  mapped pkt-line/protocol/sideband/capability/fetch/receive-pack fixtures,
  native PHP pass/fail counts, malformed packet cases, and no Git/network/auth
  shell-out progress.
- For `quadrable-proof-transport-codec-core`, require a Quadrable proof/sync
  transport denominator, mapped proof import/export and sync frame fixtures,
  native PHP pass/fail counts, malformed varint/frame/opcode/jump/root cases,
  and no LMDB, network service, `quadb`, or SQL/page-codec progress credit.
- For `tree-sitter-grammar-subset`, require a Difftastic-first grammar/query
  denominator for one selected language family with PHP pass/fail evidence and
  parser-error fallbacks before any shared support-library credit.

## Correction - 2026-05-24T11:22:37Z

- `quadrable-proof-transport-codec-core` now explicitly excludes
  secret-bearing inputs, credential material, credentials, and secret-bearing
  configs in both `scopeBoundary` and `testExpectation`. The row remains
  inactive (`candidate`), `high`, and bounded to Quadrable proof/sync transport
  bytes.
