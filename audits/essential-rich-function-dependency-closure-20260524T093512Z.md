# Essential Rich Function Dependency Closure - 2026-05-24T093512Z

Scope: support-library tracker closure across all 12 native PHP port lanes: difftastic, dolt, esbuild, gitoxide, libsqlite, lightningcss, markerPDF, pandoc, quadrable, rclone, readability, and syncthing. I inspected the current tracker, progress notes, the two scout artifacts, the prior rich-format closure, all lane manifests, and all lane status files. I did not read secrets, process environments, provider credentials, OAuth/browser state, live-service configs, or cloud remotes. I did not run live-service/provider tests or broad root tests.

## Tracker Decision

The tracker did not need new support rows. The 32 existing rows already cover the essential rich-function support surface without adding whole applications, generic dependencies, shell-out wrappers, or live services.

Three existing rows needed narrower closure refinements:

- `url-percent-encoding-core`: added `readability` because the current Readability lane records browser-style relative link/media URI cleanup, path-brace percent encoding, and broader URL normalization as active evidence/next work. The row now gates Readability via `readability-relative-uri-cleanup-next` and expects Readability link/media URL vectors alongside WebDAV, Gitoxide, esbuild, and LightningCSS vectors.
- `unicode-text-repair-width`: added `syncthing` because the Syncthing lane has explicit scanner/path NFC normalization, invalid UTF-8, and normalization-conflict evidence. The row's old broad `shared-infra-after-base-green` gate is replaced by concrete lane gates, including `syncthing-path-normalization-next`.
- `charset-encoding-core`: added `libsqlite` because the libsqlite lane has focused upstream `enc.test` evidence and native UTF-16LE/UTF-16BE SQLite record serialization for non-UTF-8 database images. The row now gates libsqlite via `libsqlite-utf16-record-encoding-next` and excludes sqlite3/database-engine shell-outs.

No dependency row was marked active.

## Lane Coverage

- Difftastic: covered by `tree-sitter-grammar-subset`, `sequence-diff-merge-core`, `source-map-v3-core`, `xml-html5-dom-core`, `unicode-text-repair-width`, `charset-encoding-core`, and `glob-filter-pathspec-core`.
- Dolt: covered by `sql-expression-semantics-core`, `sql-storage-codec-core`, `sequence-diff-merge-core`, `checksum-hash-suite`, `json-json5-document-core`, `unicode-text-repair-width`, and `charset-encoding-core`.
- esbuild: covered by `source-map-v3-core`, `browser-compat-target-data-core`, `js-package-resolution-core`, `url-percent-encoding-core`, `json-json5-document-core`, `tree-sitter-grammar-subset`, `unicode-text-repair-width`, `charset-encoding-core`, and `glob-filter-pathspec-core`.
- Gitoxide: covered by `checksum-hash-suite`, `archive-compression-streams`, `glob-filter-pathspec-core`, `url-percent-encoding-core`, `sequence-diff-merge-core`, `unicode-text-repair-width`, and `charset-encoding-core`.
- libsqlite: covered by `json-json5-document-core`, `sql-storage-codec-core`, `sql-expression-semantics-core`, and now `charset-encoding-core` for SQLite UTF-16 database text.
- LightningCSS: covered by `source-map-v3-core`, `browser-compat-target-data-core`, `js-package-resolution-core`, `url-percent-encoding-core`, `tree-sitter-grammar-subset`, `unicode-text-repair-width`, and `charset-encoding-core`.
- markerPDF: covered by `pdf-text-dictionary-core`, `pdf-page-render-plan-core`, `layout-ocr-result-core`, `table-geometry-core`, `math-tex-conversion-core`, `shared-zip-package-core`, `archive-compression-streams`, `checksum-hash-suite`, `xml-html5-dom-core`, `unicode-text-repair-width`, and `charset-encoding-core`.
- Pandoc: covered by DOCX, legacy DOC/CFB, EPUB, ODT, PDF handoff/text, citations, math, tables, templates, syntax highlighting, ZIP/package, XML/HTML, JSON, Unicode, charset, and archive/compression rows.
- Quadrable: covered by `checksum-hash-suite`, `sql-storage-codec-core`, `sequence-diff-merge-core`, `unicode-text-repair-width`, and `charset-encoding-core`.
- rclone: covered by `webdav-protocol-core`, `url-percent-encoding-core`, `shared-zip-package-core`, `archive-compression-streams`, `checksum-hash-suite`, `glob-filter-pathspec-core`, `provider-metadata-normalization-core`, `xml-html5-dom-core`, `json-json5-document-core`, and `charset-encoding-core`.
- Readability: covered by `xml-html5-dom-core`, `json-json5-document-core`, `epub3-package-core`, `table-geometry-core`, `math-tex-conversion-core`, `unicode-text-repair-width`, `charset-encoding-core`, and now `url-percent-encoding-core`.
- Syncthing: covered by `protobuf-wire-core`, `checksum-hash-suite`, `archive-compression-streams`, `glob-filter-pathspec-core`, `provider-metadata-normalization-core`, `json-json5-document-core`, and now `unicode-text-repair-width`.

## Activation Order

Recommended next activation order remains gated:

1. `shared-zip-package-core` only for a concrete Pandoc DOCX/EPUB/ODT, markerPDF benchmark archive, or rclone archive-provider slice.
2. `pdf-text-dictionary-core` for markerPDF searchable PDF text or Pandoc PDF input handoff; keep OCR-only work on `layout-ocr-result-core`.
3. `xml-html5-dom-core` for Pandoc package XML/HTML/DocBook, Readability DOM parser gaps, markerPDF HTML/image output, Difftastic XML structure, or rclone WebDAV XML.
4. `url-percent-encoding-core` only when WebDAV URL escaping, Git URL parsing, JS/CSS asset URL handling, or Readability relative URI cleanup becomes the selected support boundary.
5. `charset-encoding-core` only for markerPDF PDFDocEncoding, Pandoc legacy/HTML charset work, Readability declared charset handling, libsqlite UTF-16 record encoding, or another concrete non-UTF-8 import blocker.
6. `unicode-text-repair-width` only for concrete text repair, display width, Unicode identifier, query-diff string, key display, or Syncthing path-normalization blockers.
7. One document-rich row at a time: DOCX, legacy DOC/CFB, EPUB, ODT, citations, math, tables, templates, then syntax highlighting.
8. Runtime rows such as source maps, target data, JS package resolution, tree-sitter subsets, diff/merge, protobuf, hash, archive/compression, glob/pathspec, provider metadata, and SQL codecs only when their exact base-lane gates open.

## Files Inspected

- `goal.md`
- `dependency-backlog.json`
- `progress.md`
- `audits/doc-format-dependency-scout-20260524T085334Z.md`
- `audits/shared-runtime-dependency-scout-20260524T085334Z.md`
- `audits/rich-format-dependency-closure-20260524T091925Z.md`
- all `lanes/*/UPSTREAM_TEST_MANIFEST.json`
- all `lanes/*/lane-status.json`

## Checks Run

- `jq empty dependency-backlog.json`: passed.
- Duplicate ID check over `dependency-backlog.json`: passed with no duplicate IDs.
- Item count/status/priority summary: 32 rows; status split 22 `candidate`, 10 `deferred`; priority split 4 `critical`, 24 `high`, 4 `medium`.
- Required-field check for changed rows `url-percent-encoding-core`, `unicode-text-repair-width`, and `charset-encoding-core`: passed for `id`, `name`, `source`, `neededBy`, `essentialCapability`, `scopeBoundary`, `priority`, `status`, `activationGate`, `testExpectation`, `reuseNotes`, and `blocker`.
- `git diff --check -- dependency-backlog.json progress.md audits/essential-rich-function-dependency-closure-20260524T093512Z.md`: passed.
- `git diff --cached --check`: passed after staging only the owned backlog/progress/audit changes.

## Unresolved Blockers

No blocker for this tracker closure. The rows remain backlog-only and inactive until exact base-lane gates open. Root harness, dashboard freshness, and lane implementation blockers remain outside this assigned support-library closure scope.
