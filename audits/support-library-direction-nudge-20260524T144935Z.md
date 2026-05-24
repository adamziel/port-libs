# Support-Library Direction Nudge - 2026-05-24 14:49 UTC

Scope read: `goal.md`, `progress.md`, `dependency-backlog.json`,
`porting-summary.json`, `.tmux-team/prompts/evaluator.md`,
`.tmux-team/prompts/integrator.md`,
`.tmux-team/prompts/worker-template.md`, the routed worker prompt, and lane
status files for Pandoc, markerPDF, esbuild, lightningcss, difftastic, rclone,
syncthing, gitoxide, dolt, quadrable, readability, and libsqlite.

No lane source, tests, fixtures, dashboard output, commits, pushes, live
services, provider credentials, or secret-bearing inputs were touched.

## Decision

No dependency rows were added, activated, deleted, or newly deferred.
`dependency-backlog.json` remains at 37 gated rows with 25 `candidate`, 11
`deferred`, and 1 `blocked` row. The tracker does represent the latest support
library instruction at backlog granularity: each row has a smallest-useful
native PHP boundary, base-lane activation gate, reuse notes or consumer lanes,
upstream/spec evidence expectations, malformed/corrupt/error expectations where
relevant, and no-shell-out/no-whole-application exclusions.

No row is next-ready for active support-library work in this audit because the
current lane statuses are still lane-local and unaccepted: every named lane
reports root aggregate verification pending for the supervisor/integrator,
several batches remain uncommitted in the shared dirty worktree, and the public
dashboard summary still lags the current lane-status files. The right next
step is an integrator-accepted frozen lane batch first; then activate exactly
the support row required by that accepted next rich-function slice.

## Existing Coverage

Pandoc rich conversion is covered by existing rows, so no duplicate Pandoc row
was added:

- DOC: `legacy-doc-cfb-core`
- DOCX/OpenXML: `docx-openxml-core`
- PDF input/text extraction: `pdf-text-dictionary-core`
- PDF page/output handoff: `pdf-page-render-plan-core`,
  `pandoc-pdf-engine-handoff-core`
- EPUB: `epub3-package-core`
- ODT/OpenDocument: `odf-open-document-core`
- Templates: `pandoc-doctemplates-core`
- Citations: `citation-bibliography-csl-core`
- Math: `math-tex-conversion-core`
- Tables: `table-geometry-core`
- Package containers: `shared-zip-package-core`
- XML/HTML: `xml-html5-dom-core`
- Unicode/charset: `unicode-text-repair-width`,
  `charset-encoding-core`
- Archive/compression: `archive-compression-streams`
- Syntax highlighting for document output: `pandoc-syntax-highlighting-core`
- JSON/YAML metadata: `json-json5-document-core`, `yaml-metadata-core`

Other shared support areas are already represented:

- PDF/OCR/table helpers: `pdf-text-dictionary-core`,
  `pdf-page-render-plan-core`, `layout-ocr-result-core`,
  `table-geometry-core`
- Source maps and JS/CSS rich output: `source-map-v3-core`,
  `browser-compat-target-data-core`, `js-package-resolution-core`,
  `url-percent-encoding-core`
- Text repair, charset, and Unicode handling: `unicode-text-repair-width`,
  `charset-encoding-core`
- Checksums, protocols, and storage codecs: `checksum-hash-suite`,
  `git-wire-protocol-core`, `protobuf-wire-core`,
  `quadrable-proof-transport-codec-core`, `webdav-protocol-core`,
  `mysql-wire-protocol-core`, `sql-storage-codec-core`,
  `sql-expression-semantics-core`
- Archives, paths, metadata, and structural diff helpers:
  `archive-compression-streams`, `shared-zip-package-core`,
  `glob-filter-pathspec-core`, `provider-metadata-normalization-core`,
  `tree-sitter-grammar-subset`, `sequence-diff-merge-core`

## Watch List

The next activation candidates remain bounded but should wait for an accepted
base-lane handoff:

- `pdf-text-dictionary-core` for accepted markerPDF searchable-PDF text
  extraction or Pandoc PDF input handoff.
- `shared-zip-package-core` plus `xml-html5-dom-core` for accepted Pandoc
  DOCX/EPUB/ODT package parsing/writing, markerPDF benchmark archives, or
  rclone archive providers.
- `source-map-v3-core` for an accepted esbuild/LightningCSS source-map slice or
  Difftastic source-review span slice.
- `json-json5-document-core` or `sql-expression-semantics-core` only if the
  integrator promotes the current Dolt/libsqlite JSON/SQL work from lane-local
  behavior into a shared support-library project.
- `protobuf-wire-core` or the already `blocked` `qr-code-matrix-core` only
  after a Syncthing protocol or QR route-body slice is accepted from a frozen
  snapshot.

No support-library tooling blocker was recorded by this audit, so no bounded
`sudo -n` install attempt was needed.

## Validation

Clean commands recorded for this audit:

- `jq empty dependency-backlog.json`
- `jq -r '.items[].id' dependency-backlog.json | sort | uniq -d`
- `git diff --check -- progress.md audits/support-library-direction-nudge-20260524T144935Z.md`
