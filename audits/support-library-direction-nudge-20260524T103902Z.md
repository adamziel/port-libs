# Support Library Direction Nudge - 2026-05-24T10:39:02Z

Scope read: `goal.md`, `progress.md`, `dependency-backlog.json`,
`audits/latest.md`, `audits/evaluator-feedback.md`, all 12
`lanes/*/lane-status.json` files, and all 12
`lanes/*/UPSTREAM_TEST_MANIFEST.json` files.

No lane implementation files were edited. No lane feature work, staging,
commit, push, reset, revert, publish, or live-service/provider tests were
attempted.

## Tracker Decision

`dependency-backlog.json` now has 34 inactive support-library rows. This audit
added two missing bounded rows:

- `qr-code-matrix-core`: Syncthing's current `nextTask` names a bounded `/qr`
  route-body slice and requires a native PHP QR component decision before
  implementation. This row gates QR matrix/output contracts without porting
  scanner apps, browser/mobile pairing apps, live pairing, or QR shell-outs.
- `mysql-wire-protocol-core`: Dolt's base goal includes MySQL-compatible
  behavior where practical, while the current Dolt status explicitly excludes
  SQL-server/client suites. This row gates only packet/result-set contracts and
  fake-runner protocol fixtures, not MySQL/Dolt servers, clients, network
  listeners, SQL engines, credentials, or ORM/application suites.

No existing row was activated. Both new rows are gated behind concrete base-lane
slices and remain non-progress until they have dependency-specific upstream or
spec denominators, mapped fixtures, PHP pass/fail evidence, malformed/error
cases, and no-shell-out/no-whole-application exclusions.

## Pandoc Coverage Evidence

The latest user directive called out Pandoc's essential conversion support.
The current tracker already covers those gates, so duplicate Pandoc rows were
not added:

- DOC: `legacy-doc-cfb-core`
- DOCX/OpenXML: `docx-openxml-core`
- PDF input/text extraction: `pdf-text-dictionary-core`
- PDF output handoff: `pandoc-pdf-engine-handoff-core`
- EPUB: `epub3-package-core`
- ODT/OpenDocument: `odf-open-document-core`
- Templates: `pandoc-doctemplates-core`
- Citations: `citation-bibliography-csl-core`
- Math: `math-tex-conversion-core`
- Tables: `table-geometry-core`
- Package containers: `shared-zip-package-core`
- XML/HTML: `xml-html5-dom-core`
- Unicode and charset: `unicode-text-repair-width`,
  `charset-encoding-core`
- Archive/compression: `archive-compression-streams`
- Syntax highlighting needed by document output: `pandoc-syntax-highlighting-core`

The Pandoc lane currently records Markdown/HTML/Native/LaTeX/WordPress-focused
fixtures and several Native DOCX/ODT/EPUB review packets. Those remain valid
lane-local evidence, but rich DOC/DOCX/PDF/EPUB/ODT conversion credit still
requires opening the relevant support rows once the base lane starts package,
binary, PDF, template, citation, math, table, syntax-highlighting, charset, or
compression work beyond lane-local fixtures.

## Cross-Lane Coverage

- Gitoxide: covered by `url-percent-encoding-core`,
  `sequence-diff-merge-core`, `checksum-hash-suite`,
  `archive-compression-streams`, `glob-filter-pathspec-core`,
  `unicode-text-repair-width`, and `charset-encoding-core`.
- LightningCSS: covered by `url-percent-encoding-core`,
  `source-map-v3-core`, `browser-compat-target-data-core`,
  `js-package-resolution-core`, `unicode-text-repair-width`, and
  `charset-encoding-core`.
- markerPDF: covered by `pdf-text-dictionary-core`,
  `pdf-page-render-plan-core`, `layout-ocr-result-core`,
  `table-geometry-core`, `math-tex-conversion-core`,
  `unicode-text-repair-width`, `charset-encoding-core`,
  `shared-zip-package-core`, and `checksum-hash-suite`.
- libsqlite: covered by `charset-encoding-core`,
  `json-json5-document-core`, `sql-storage-codec-core`, and
  `sql-expression-semantics-core`.
- Readability: covered by `xml-html5-dom-core`,
  `url-percent-encoding-core`, `json-json5-document-core`,
  `epub3-package-core`, `math-tex-conversion-core`,
  `table-geometry-core`, `unicode-text-repair-width`, and
  `charset-encoding-core`.
- Quadrable: covered by `sequence-diff-merge-core`,
  `checksum-hash-suite`, `sql-storage-codec-core`, and
  `unicode-text-repair-width`.
- Syncthing: covered by `protobuf-wire-core`, `checksum-hash-suite`,
  `archive-compression-streams`, `glob-filter-pathspec-core`,
  `provider-metadata-normalization-core`, `json-json5-document-core`,
  `unicode-text-repair-width`, and new `qr-code-matrix-core`.
- Difftastic: covered by `tree-sitter-grammar-subset`,
  `sequence-diff-merge-core`, `source-map-v3-core`,
  `xml-html5-dom-core`, `glob-filter-pathspec-core`,
  `unicode-text-repair-width`, and `charset-encoding-core`.
- rclone: covered by `webdav-protocol-core`,
  `url-percent-encoding-core`, `checksum-hash-suite`,
  `archive-compression-streams`, `glob-filter-pathspec-core`,
  `provider-metadata-normalization-core`, `json-json5-document-core`,
  `xml-html5-dom-core`, and `shared-zip-package-core`.
- Dolt: covered by `sequence-diff-merge-core`, `checksum-hash-suite`,
  `sql-storage-codec-core`, `sql-expression-semantics-core`,
  `json-json5-document-core`, `unicode-text-repair-width`, and new
  `mysql-wire-protocol-core`.
- esbuild: covered by `source-map-v3-core`,
  `browser-compat-target-data-core`, `js-package-resolution-core`,
  `url-percent-encoding-core`, `json-json5-document-core`,
  `glob-filter-pathspec-core`, `unicode-text-repair-width`, and
  `charset-encoding-core`.

## Required Blocker Wording

Generic evaluator/integrator wording:

`Acceptance blocker: this rich conversion/runtime slice depends on support-library gate <row-id(s)>, but that gate is not active and lacks a dependency-specific upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, malformed/corrupt/error cases, and explicit no-shell-out/no-whole-application exclusions. Lane-local fixtures, generated oracles, supplied external outputs, and converter/service shell-outs may be recorded as evidence, but they do not count as support-library progress or rich conversion/runtime completion.`

Pandoc-specific wording:

`Acceptance blocker: Pandoc rich conversion for <format/capability> requires <row-id(s)> before progress credit. The lane may keep bounded Native or writer fixtures, but DOC, DOCX/OpenXML, PDF input/output, EPUB, ODT/OpenDocument, templates, citations, math, tables, package containers, XML/HTML, Unicode/charset, or archive/compression claims must not be accepted until the relevant support row records an upstream/spec denominator, mapped fixtures, PHP pass/fail counts, malformed/corrupt cases, and no credit for Pandoc, Office suites, PDF engines, TeX engines, OCR/model stacks, browser engines, or converter shell-outs.`

Concrete Pandoc row mapping for that wording:

- DOCX/OpenXML: `docx-openxml-core`, plus `shared-zip-package-core` and
  `xml-html5-dom-core` when package parts or relationships are parsed.
- Legacy DOC: `legacy-doc-cfb-core`, plus `charset-encoding-core` when
  non-UTF-8 text decoding is required.
- PDF input/text extraction: `pdf-text-dictionary-core`; page handoff uses
  `pdf-page-render-plan-core`; output-to-PDF uses
  `pandoc-pdf-engine-handoff-core`.
- EPUB: `epub3-package-core`, plus ZIP/XML/HTML/charset rows when package
  parsing or writing begins.
- ODT/OpenDocument: `odf-open-document-core`, plus ZIP/XML/table/media rows
  when package parsing or writing begins.
- Templates/citations/math/tables/syntax highlighting:
  `pandoc-doctemplates-core`, `citation-bibliography-csl-core`,
  `math-tex-conversion-core`, `table-geometry-core`, and
  `pandoc-syntax-highlighting-core`.

Other exact blocker clauses:

- markerPDF PDF extraction: `Acceptance blocker: markerPDF PDF text, page, OCR/layout, table, or benchmark-archive progress requires the relevant pdf/table/ZIP/checksum support row before rich extraction credit; external PDFium/PIL/Ghostscript/Poppler/OCR/model output is only oracle or supplied-result evidence.`
- rclone WebDAV/archive/provider work: `Acceptance blocker: rclone local WebDAV, archive, checksum, metadata, or filter progress requires the matching support row before shared support-library credit; live providers, FUSE, Docker serve suites, auth proxies, credentials, and rclone shell-outs remain excluded.`
- Syncthing BEP/QR work: `Acceptance blocker: Syncthing protocol serialization or QR route-body progress requires protobuf-wire-core or qr-code-matrix-core before support-library credit; protoc, QR shell-outs, scanner apps, live pairing, and secret-bearing device IDs do not count.`
- Dolt MySQL-compatible work: `Acceptance blocker: Dolt MySQL wire/server/client compatibility requires mysql-wire-protocol-core before protocol progress credit; launching Dolt sql-server, mysqld, mysql clients, live network listeners, ORM suites, or credential-bearing configs does not count.`
- Difftastic grammar/diff work: `Acceptance blocker: Difftastic shared grammar or sequence-diff claims require tree-sitter-grammar-subset or sequence-diff-merge-core before shared support-library credit; parser-generator runtimes, Cargo parsers, and difftastic shell-outs do not count.`
- esbuild/LightningCSS source maps, target data, package resolution, URLs, or
  JSON: `Acceptance blocker: JS/CSS rich output or bundler support requires the matching source-map, target-data, package-resolution, URL, JSON, Unicode, or charset row before shared support-library credit; Node, npm, browser engines, esbuild/lightningcss shell-outs, and live package fetches do not count.`
- libsqlite/Dolt SQL semantics: `Acceptance blocker: shared SQL expression,
  JSON/JSON5, storage-codec, or MySQL packet progress requires its support row
  before shared support-library credit; sqlite3, mysql, dolt, database servers,
  or external database engines do not count.`
- Readability rich cleanup/export: `Acceptance blocker: Readability DOM,
  URL cleanup, JSON-LD, EPUB export, math, table, Unicode, or charset progress
  requires the matching support row before shared support-library credit;
  browser engines, service fetchers, converter shell-outs, and live network
  behavior do not count.`

## Remaining Gaps And Activation

No missing tracker row remains from this audit's lane/status review. The
operational gap is that all support rows are still inactive and carry no
support-library PHP pass/fail evidence yet.

Next single activation candidate: `shared-zip-package-core`, but only after a
concrete Pandoc DOCX/EPUB/ODT package-container blocker, markerPDF benchmark
archive blocker, or rclone archive-provider blocker is selected. If Syncthing
chooses `/qr` as the next accepted slice instead, `qr-code-matrix-core` is the
bounded alternative candidate for that specific lane only.
