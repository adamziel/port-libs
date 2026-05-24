# Rich Format Dependency Closure - 2026-05-24T091925Z

Scope: dependency-tracker audit only. I inspected the assigned tracker, prior dependency scout notes, Pandoc and markerPDF manifest/status/notes evidence, and a bounded grep over the other lane manifests/status files for obvious shared dependency gaps. I did not edit lane implementation files, lane tests, lane fixtures, dashboard artifacts, or generated summaries. I did not run live-service/provider tests, inspect secrets, inspect process environments, inspect credential stores, inspect provider configs, or touch cloud/browser auth state.

## Tracker Change

Added one gated row:

- `pandoc-syntax-highlighting-core`: `candidate`/`high`, needed by `pandoc`, behind `pandoc-syntax-highlighting-next`.

Reason: the Pandoc lane is now mapping HTML writer code-role behavior and explicitly records syntax highlighting as a future slice. The existing `tree-sitter-grammar-subset` row is scoped to Difftastic structural parsing plus selected JS/CSS grammar reuse, not Pandoc's Skylighting-style document writer behavior. This is a bounded document-output support boundary, not a broad parser-generator or highlighter-runtime port.

The row's expectation is explicit: use a syntax-highlighting-specific upstream denominator from Pandoc writer fixtures and Skylighting-style syntax definition/style fixtures; record PHP pass/fail evidence for language aliases, token categories, HTML/LaTeX output parity, WordPress code-block metadata, unsupported-language fallbacks, malformed syntax/style definitions, and resource-limit cases; include as much bounded upstream runner/static evidence as can honestly run; do not count shell-outs to Pandoc, Haskell, Skylighting executables, tree-sitter/Cargo parsers, Node/browser highlighters, or external code-formatting tools.

No existing row was marked active.

## Existing Coverage

The existing rows already cover the Pandoc and markerPDF rich-function surface named in the objective:

- DOC/DOCX/OpenXML/legacy Word: `legacy-doc-cfb-core` and `docx-openxml-core`.
- PDF input/output/handoff: `pdf-text-dictionary-core`, `pdf-page-render-plan-core`, `layout-ocr-result-core`, and `pandoc-pdf-engine-handoff-core`.
- EPUB/ODT/package formats: `epub3-package-core`, `odf-open-document-core`, `shared-zip-package-core`, and `archive-compression-streams`.
- Citations/math/tables/templates: `citation-bibliography-csl-core`, `math-tex-conversion-core`, `table-geometry-core`, and `pandoc-doctemplates-core`.
- XML/HTML/Unicode/charset/JSON metadata: `xml-html5-dom-core`, `unicode-text-repair-width`, `charset-encoding-core`, and `json-json5-document-core`.
- markerPDF benchmark/integrity pieces: `shared-zip-package-core`, `checksum-hash-suite`, and `archive-compression-streams`.

markerPDF did not need a new row in this pass. Searchable PDF text and dictionary output belong under `pdf-text-dictionary-core`; page boxes/crops/preview contracts belong under `pdf-page-render-plan-core`; scanned or garbled PDF handoff belongs under `layout-ocr-result-core`; tables and equations belong under `table-geometry-core` and `math-tex-conversion-core`; Unicode repair and encodings belong under `unicode-text-repair-width` and `charset-encoding-core`. The excluded applications and engines remain excluded: Tesseract, OCRMyPDF, Ghostscript, PDFium, Poppler, PIL/Pillow rendering, Torch, Surya, Texify, Nougat, Streamlit, FastAPI, Uvicorn, Pandoc/XeLaTeX helper execution, and PDF converter shell-outs.

## Other Lane Scan

The bounded scan did not justify more rows now:

- rclone WebDAV, URL escaping, archive/compression, checksums, filters, and provider metadata are already covered by existing rows.
- Syncthing BEP protobuf, LZ4/compression, hash, ignore/filter, JSON/config, and metadata support are already covered. Filesystem watch behavior remains lane-local for now; prior scout guidance rejects generic watcher rows unless a later audit names a small upstream/spec denominator and concrete cross-lane gate.
- esbuild and LightningCSS package resolution, source maps, target data, JSON, URL, charset/Unicode, and glob needs are already represented.
- Gitoxide, Difftastic, Dolt, libsqlite, and Quadrable shared gaps remain covered by checksum/hash, archive/compression, glob/pathspec, charset/Unicode, source-map, tree-sitter grammar subset, sequence diff/merge, SQL storage codec, and SQL expression rows.

## Activation Order

Do not activate all optional rows. Recommended order remains:

1. `shared-zip-package-core` only when a concrete Pandoc DOCX/EPUB/ODT, markerPDF benchmark archive, or rclone archive-provider slice opens.
2. `pdf-text-dictionary-core` for markerPDF searchable text or Pandoc PDF input handoff; keep OCR-only work on `layout-ocr-result-core`.
3. `xml-html5-dom-core` for Pandoc package XML/HTML/DocBook, Readability DOM parser gaps, or WebDAV XML payloads.
4. One rich format at a time: DOCX, legacy DOC/CFB, EPUB, ODT, citations, math, tables, templates, then syntax highlighting when `pandoc-syntax-highlighting-next` opens.
5. Runtime rows such as URL, diff/merge, compression, hash, glob/pathspec, provider metadata, and SQL codecs only when their exact base-lane gates open.

## Files Inspected

- `goal.md`
- `progress.md`
- `dependency-backlog.json`
- `audits/doc-format-dependency-scout-20260524T085334Z.md`
- `audits/shared-runtime-dependency-scout-20260524T085334Z.md`
- `audits/dependency-scout-integrator-20260524T090051Z.md`
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `lanes/pandoc/lane-status.json`
- `lanes/pandoc/notes/upstream-inventory.md`
- `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`
- `lanes/markerpdf/lane-status.json`
- `lanes/markerpdf/notes/upstream-test-inventory.md`
- Bounded `rg`/`jq` scans over the other `lanes/*/UPSTREAM_TEST_MANIFEST.json` and `lanes/*/lane-status.json` files for dependency terms only.

## Checks

- `jq empty dependency-backlog.json`: passed.
- Duplicate ID check over `dependency-backlog.json`: passed with no duplicate IDs.
- Count/status summary after changes: 32 rows; 22 `candidate`, 10 `deferred`.
- New-row required field check for `pandoc-syntax-highlighting-core`: passed for `id`, `name`, `source`, `lanes`, `neededBy`, `essentialCapability`, `scopeBoundary`, `priority`, `status`, `activationGate`, `testExpectation`, `reuseNotes`, and `blocker`.
- `git diff --check -- dependency-backlog.json progress.md audits/rich-format-dependency-closure-20260524T091925Z.md`: passed.

## Unresolved Blockers

- This is a backlog/audit change only; no dependency port was activated.
- Full upstream Pandoc runner parity remains unexecuted for the lane-recorded Haskell/Cabal checkout and dependency graph reasons.
- Full markerPDF upstream runner parity remains blocked by the heavy Python/PDF/model/runtime stack recorded in lane status.
