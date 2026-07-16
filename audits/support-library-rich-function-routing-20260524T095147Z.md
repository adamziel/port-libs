# Support Library Rich Function Routing - 2026-05-24T095147Z

Scope: tracker and audit only. I inspected `goal.md`, `progress.md`, `dependency-backlog.json`, `porting-summary.json`, all 12 `lanes/*/lane-status.json` files, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json` files, and recent dependency/support-library audit notes under `audits/`. I did not edit lane source, lane tests, lane fixtures, provider credentials/config, dashboard files, or runner scripts. I did not inspect secrets, process environments, credential stores, OAuth/browser state, provider configs, or cloud remotes. I did not run live-service provider tests or broad upstream suites.

## Tracker Decision

The 32 existing backlog rows cover the required rich-function support surface. No new row was added and no row was marked active.

I refined four existing rows because their `neededBy` consumers were broader than their concrete activation gates:

- `xml-html5-dom-core`: added `markerpdf-html-output-next` and `difftastic-xml-structure-next` to the gate and replaced the vague `none` blocker with concrete activation guidance.
- `json-json5-document-core`: added `rclone-json-metadata-next`, `syncthing-json-config-next`, `dolt-json-scalar-next`, and `pandoc-metadata-json-next`; the test expectation now includes local rclone/Syncthing payloads, Dolt JSON scalars, and Pandoc metadata JSON.
- `source-map-v3-core`: added `difftastic-source-map-review-next` and Difftastic review-span fixture expectations.
- `checksum-hash-suite`: added `markerpdf-benchmark-integrity-next` and markerPDF benchmark/package integrity fixture expectations.

Intentionally unchanged:

- Pandoc/markerPDF document rows already cover DOC, DOCX/OpenXML, PDF input/output handoff, EPUB, ODT/OpenDocument, templates, citations, math, rich tables, ZIP/package containers, XML/HTML, Unicode/charset, archive/compression, syntax highlighting, OCR/layout supplied-result contracts, and PDF page/text boundaries.
- Whole applications and shell-outs remain excluded: OpenOffice/LibreOffice/Microsoft Word, Ghostscript/PDFium/Poppler, Tesseract/OCRMyPDF, model stacks, service wrappers, converter CLIs, browser engines, Node/npm, parser generators, database/server processes, and live provider remotes.
- `archive-compression-streams` was not broadened for markerPDF because `shared-zip-package-core` is the right first benchmark-archive/package row; compression streams should open only for a concrete gzip/LZ4/pack/package-compression blocker beyond ZIP container parsing.

## Coverage Findings

Pandoc is covered by bounded rows for DOCX/OpenXML, legacy DOC/CFB, PDF output handoff, PDF input/text handoff, EPUB, ODT/OpenDocument, templates, citations/CSL, math/TeX, tables, syntax highlighting, ZIP/package containers, XML/HTML, JSON metadata, Unicode/charset, and archive/compression. The current lane has 345 focused PHP tests passing and a 2,276-item manifest; full Haskell runner parity remains unexecuted, so support rows must require dependency-specific denominators before progress credit.

markerPDF is covered by bounded rows for searchable PDF text dictionaries, page/crop/render planning, supplied OCR/layout result ingestion, table geometry, math/equation handoff, ZIP/package benchmark archives, XML/HTML output, Unicode/charset, checksums, and archive/compression. The current lane has 467 focused PHP tests passing and a 379-item manifest; heavy Python/PDF/model/runtime stacks remain blockers or supplied-result contracts, not dependency-port progress.

Non-document lanes are covered by the shared rows requested in the directive:

- Source maps: `source-map-v3-core` for esbuild, LightningCSS, and now Difftastic review spans.
- Browser target data: `browser-compat-target-data-core` for LightningCSS and esbuild.
- Package resolution: `js-package-resolution-core` for esbuild and LightningCSS.
- Glob/pathspec: `glob-filter-pathspec-core` for rclone, Syncthing, Gitoxide, esbuild, and Difftastic.
- Checksums/hashes: `checksum-hash-suite` for rclone, Gitoxide, Dolt, Quadrable, Syncthing, and markerPDF.
- Archive/compression streams: `archive-compression-streams` for rclone, Syncthing, Pandoc, markerPDF, and Gitoxide.
- Protobuf/BEP wire format: `protobuf-wire-core` for Syncthing.
- SQL storage/expression semantics: `sql-storage-codec-core` and `sql-expression-semantics-core` for Dolt/libsqlite/Quadrable boundaries.
- URL percent encoding: `url-percent-encoding-core` for rclone, Gitoxide, esbuild, LightningCSS, and Readability.
- Unicode/charset: `unicode-text-repair-width` and `charset-encoding-core` across document, code, path, sync, SQLite, and query-diff lanes.
- Provider metadata normalization: `provider-metadata-normalization-core` for local rclone/Syncthing metadata only.
- Sequence diff/merge: `sequence-diff-merge-core` for Difftastic, Gitoxide, Dolt, and Quadrable.

## Routing Map

| Lane | Current sampled evidence | Next support row to activate at gate | Gate note |
| --- | --- | --- | --- |
| difftastic | 3,071 PHP assertions pass; manifest denominator 974; current next task is another bounded TypeScript/grammar shape. | `tree-sitter-grammar-subset` | Activate only if the next grammar/query slice is promoted into a shared denominator; otherwise keep current grammar work lane-local. |
| dolt | 417 PHP checks pass; manifest total is still prose/non-normalized; current work is query-diff scalar/function behavior. | `sql-expression-semantics-core` | Activate for the next bounded query-diff scalar family that would otherwise duplicate shared SQL expression semantics. |
| esbuild | 406 PHP checks pass; manifest denominator 2,567; current next task is source-map `sourcesContent` hydration. | `source-map-v3-core` | This is the clearest immediate gate because the lane is already on Source Map v3 behavior. |
| gitoxide | 6,910 PHP assertions pass; manifest denominator 2,877; current next task is gix-index writer/verifier parity. | `glob-filter-pathspec-core` | Not for the current index slice; activate when pathspec/ignore matching becomes the next shared boundary. |
| libsqlite | 336 PHP checks pass; manifest denominator 1,589; current next task is JSON operator RHS parity. | `json-json5-document-core` | Activate if JSON/JSON5 parsing/path/operator behavior moves out of lane-local fixtures into a shared component. |
| LightningCSS | 3,896 PHP assertions pass; manifest denominator 3,535; current next task is another bounded CSS helper. | `source-map-v3-core` | Use when LightningCSS source-map output/consumption becomes the selected support boundary; target data stays behind `browser-compat-target-data-core`. |
| markerPDF | 467 PHP checks pass; manifest denominator 379; current next task is malformed/corrupt PDF metadata or another PDF resource edge. | `pdf-text-dictionary-core` | First PDF support activation for searchable text/span/box/font dictionary extraction; OCR-only work uses `layout-ocr-result-core`. |
| pandoc | 345 behavior tests / 3,557 assertions pass; manifest denominator 2,276; current next task is another bounded writer/reader fixture. | `shared-zip-package-core` | First rich-format activation for real DOCX/EPUB/ODT/package work; format rows follow one at a time. If the selected next slice is broader highlighting, use `pandoc-syntax-highlighting-core` instead. |
| quadrable | 224 PHP checks pass; manifest denominator 55; upstream runner evidence exists. | `sql-storage-codec-core` | Activate only if raw store/proof dump or shared byte-codec work becomes the blocker; otherwise keep remaining `check.cpp` edges lane-local. |
| rclone | 869 PHP checks pass; manifest denominator 1,601; current next task is local-only WebDAV MOVE directory behavior. | `webdav-protocol-core` | The next local WebDAV protocol/property slice should use this row rather than growing more lane-local WebDAV primitives. |
| readability | 247 PHP tests pass; manifest denominator 1,984; current next task is broader URL normalization/cleanup. | `url-percent-encoding-core` | Activate for the next relative link/media URI normalization blocker shared with WebDAV, JS/CSS asset URLs, or Git URL parsing. |
| syncthing | 7,314 PHP assertions pass; manifest denominator 658; current next task is REST pending-device query handling. | `protobuf-wire-core` | REST route work remains lane-local; activate this row when `syncthing-protocol-next` or BEP wire serialization/unknown-field work opens. |

## Next Activation Order

1. `shared-zip-package-core` for Pandoc DOCX/EPUB/ODT packages, markerPDF benchmark archives, or rclone archive-provider work.
2. `pdf-text-dictionary-core` for markerPDF searchable PDF text or Pandoc PDF input handoff.
3. `webdav-protocol-core` if rclone continues local WebDAV protocol/property slices.
4. `source-map-v3-core` because esbuild is already on Source Map v3 hydration and LightningCSS/Difftastic reuse is now explicitly gated.
5. `xml-html5-dom-core` for package XML/HTML, Readability parser gaps, markerPDF HTML output, Difftastic XML structure, or WebDAV XML payloads.
6. `url-percent-encoding-core` for Readability URL cleanup, WebDAV URL escaping, Git URL parsing, or JS/CSS asset URL handling.
7. `json-json5-document-core` for libsqlite JSON/JSON5, esbuild JSON loading, Readability JSON-LD, rclone/Syncthing local payloads, Dolt JSON scalar behavior, or Pandoc metadata JSON.
8. One rich document row at a time after prerequisites: DOCX/OpenXML, legacy DOC/CFB, EPUB, ODT/OpenDocument, templates, citations, math, tables, then syntax highlighting as the selected slice requires.
9. Runtime rows only at exact gates: browser target data, JS package resolution, tree-sitter subsets, sequence diff/merge, protobuf, checksum/hash, archive/compression, glob/pathspec, provider metadata, SQL expression, and SQL storage codecs.

## Remaining Tracking Gaps

- Support-library coverage is still backlog-only; no support row has a dedicated implementation manifest, fixture matrix, PHP pass/fail evidence, or full/spec denominator yet.
- `porting-summary.json` is stale relative to `dependency-backlog.json` and still reports the older dependency-backlog shape.
- Dolt's manifest still exposes a prose/non-normalized `benchmarkDenominator.total`, which makes tracker automation brittle.
- Current lane evidence is moving dirty-tree evidence and root/integrator acceptance remains outside this tracker slice.

## Verification

- `jq empty dependency-backlog.json`: passed with exit 0 and no output.
- `jq empty dependency-backlog.json porting-summary.json lanes/*/lane-status.json lanes/*/UPSTREAM_TEST_MANIFEST.json`: passed with exit 0 and no output.
- Duplicate-ID and count check: `jq -e '([.items[].id] | length == (unique | length)) and (.items | length == 32)' dependency-backlog.json`: passed and returned `true`.
- Status split: 22 `candidate`, 10 `deferred`.
- Required-field check for changed rows `xml-html5-dom-core`, `json-json5-document-core`, `source-map-v3-core`, and `checksum-hash-suite`: passed.
- `git diff --check -- dependency-backlog.json progress.md audits/support-library-rich-function-routing-20260524T095147Z.md`: passed with exit 0 and no output before staging.
- `git diff --cached --check`: passed with exit 0 and no output after staging only the owned tracker/audit files.
