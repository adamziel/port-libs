# Support Library Progress Tracker - 2026-05-24T083724Z

Scope: dependency progress tracking only. I inspected `goal.md`, `progress.md`, `dependency-backlog.json`, `audits/support-library-direction-nudge-20260524T080714Z.md`, `audits/latest.md`, all 12 requested `lanes/*/lane-status.json` files, and all 12 requested `lanes/*/UPSTREAM_TEST_MANIFEST.json` files. I did not edit lane source, tests, fixtures, dashboard files, summaries, or implementation code. I did not run live-service/provider tests, no-argument root PHP, or credential/auth inspection.

## Backlog Changes

The current dirty backlog already contained 24 rows, including the prior Pandoc PDF engine handoff row and rclone/XML/archive gate tightening. This pass expands the tracker to 29 rows and keeps all activation behind explicit gates.

Added:

- `webdav-protocol-core`: candidate/high for rclone local-only WebDAV provider/runtime work, with RFC/x/net/rclone denominator expectations, local mutation/property/lock/If/gzip evidence, malformed XML/header/path/property cases, and explicit live-provider/auth/FUSE/Docker non-goals.
- `json-json5-document-core`: candidate/high for libsqlite JSON/JSON5, esbuild JSON loader/preflight, Readability JSON-LD, rclone/Syncthing payloads, Dolt JSON scalar fixtures, and Pandoc metadata import.
- `browser-compat-target-data-core`: deferred/high for LightningCSS prefixer/target and esbuild target-lowering data; kept deferred until a concrete target-data slice is selected.
- `js-package-resolution-core`: deferred/high for esbuild and LightningCSS package.json/exports/imports/tsconfig/CSS package resolution; kept deferred until package-resolution work moves beyond lane-local file fixtures.
- `sql-expression-semantics-core`: candidate/high for Dolt query-diff scalar/function work and libsqlite SQL execution/JSON operator slices, separate from row/page/storage codecs.

Updated:

- `charset-encoding-core`: moved from deferred/medium to candidate/high because markerPDF's next stated slice includes PDFDocEncoding metadata fallback and several lanes need non-UTF-8 import decoding.
- `source-map-v3-core`: activation gate now includes LightningCSS source-map work, not only esbuild.
- `tree-sitter-grammar-subset`: moved from deferred/medium to candidate/high with a concrete-grammar activation gate because Difftastic is already mapping grammar/query subsets lane-locally.
- `protobuf-wire-core`: moved from deferred/medium to candidate/high because Syncthing is green enough for bounded BEP wire-format slices.
- `sql-storage-codec-core`: activation gate now includes libsqlite write and Quadrable store-codec slices, not only Dolt storage.

Kept deferred:

- ODT/OpenDocument, citations/CSL, math/TeX, Pandoc PDF output handoff, archive/compression streams, glob/pathspec, provider metadata normalization, browser target data, and JS package resolution remain deferred unless their explicit activation gates become the next accepted slice or a base lane blocker.

## Lane Mapping

- Pandoc: `shared-zip-package-core`, `xml-html5-dom-core`, `docx-openxml-core`, `legacy-doc-cfb-core`, `epub3-package-core`, `odf-open-document-core`, `pandoc-doctemplates-core`, `citation-bibliography-csl-core`, `math-tex-conversion-core`, `pandoc-pdf-engine-handoff-core`, `table-geometry-core`, `unicode-text-repair-width`, `charset-encoding-core`, `json-json5-document-core`, and `archive-compression-streams`.
- markerPDF: `pdf-text-dictionary-core`, `pdf-page-render-plan-core`, `layout-ocr-result-core`, `table-geometry-core`, `math-tex-conversion-core`, `unicode-text-repair-width`, `charset-encoding-core`, `shared-zip-package-core`, `archive-compression-streams`, and `checksum-hash-suite`.
- Readability: `xml-html5-dom-core`, `unicode-text-repair-width`, `charset-encoding-core`, `json-json5-document-core`, `table-geometry-core`, `math-tex-conversion-core`, and `epub3-package-core`.
- rclone: `webdav-protocol-core`, `xml-html5-dom-core`, `checksum-hash-suite`, `archive-compression-streams`, `glob-filter-pathspec-core`, `provider-metadata-normalization-core`, `charset-encoding-core`, `unicode-text-repair-width`, and `json-json5-document-core`.
- esbuild: `source-map-v3-core`, `json-json5-document-core`, `browser-compat-target-data-core`, `js-package-resolution-core`, `glob-filter-pathspec-core`, `charset-encoding-core`, and `unicode-text-repair-width`.
- Difftastic: `tree-sitter-grammar-subset`, `charset-encoding-core`, `unicode-text-repair-width`, `xml-html5-dom-core`, `source-map-v3-core`, and `glob-filter-pathspec-core`.
- LightningCSS: `source-map-v3-core`, `browser-compat-target-data-core`, `js-package-resolution-core`, `unicode-text-repair-width`, and `charset-encoding-core`.
- libsqlite: `sql-storage-codec-core`, `sql-expression-semantics-core`, `json-json5-document-core`, `charset-encoding-core`, and `unicode-text-repair-width`.
- Gitoxide: `checksum-hash-suite`, `glob-filter-pathspec-core`, `archive-compression-streams`, `charset-encoding-core`, and `unicode-text-repair-width`.
- Syncthing: `protobuf-wire-core`, `archive-compression-streams`, `checksum-hash-suite`, `glob-filter-pathspec-core`, `provider-metadata-normalization-core`, `json-json5-document-core`, `charset-encoding-core`, and `unicode-text-repair-width`.
- Dolt: `sql-expression-semantics-core`, `sql-storage-codec-core`, `checksum-hash-suite`, `json-json5-document-core`, `charset-encoding-core`, and `unicode-text-repair-width`.
- Quadrable: `checksum-hash-suite`, `sql-storage-codec-core`, `charset-encoding-core`, and `unicode-text-repair-width`.

## Non-Goals

These must not count as support-library dependency-port progress: LibreOffice, OpenOffice, Microsoft Word, Pandoc/office converter shell-outs, Tesseract, OCRMyPDF, Ghostscript, PDFium, PIL, Poppler, Torch, Surya, Texify, Nougat, Streamlit, FastAPI, Uvicorn, TeX/ConTeXt/Typst/Groff/WebKit/WeasyPrint/Prince/PagedJS engines, Node/npm/yarn/pnpm, esbuild/LightningCSS/Browserslist CLIs, tree-sitter/Cargo parser engines, protoc/gRPC runtimes, sqlite3/dolt/mysql server or CLI subprocesses, live cloud/provider remotes, OAuth flows, FUSE mounts, Docker-backed provider suites, package registry downloads, and secret-bearing auth/config inspection.

## Validation

- `jq empty dependency-backlog.json`: passed with exit 0 and no output after the backlog edit.
- `git diff --check`: passed with exit 0 and no output after this audit artifact was written. This exact command checks the tracked diff; the new audit file remains untracked until the supervisor integrates it.

No no-argument root PHP harness was run by this worker.
