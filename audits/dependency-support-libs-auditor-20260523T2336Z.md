# Dependency Support Libraries Audit 2026-05-23T23:36Z

## Scope Read

- Read `goal.md`, `progress.md`, `dependency-backlog.json`, `porting-summary.json`, and the current dashboard generator behavior.
- Read all lane status and upstream manifest files under `lanes/*/{lane-status.json,UPSTREAM_TEST_MANIFEST.json}` for the 12 goal lanes: Gitoxide, LightningCSS, markerPDF, libsqlite, Readability, Pandoc, Quadrable, Syncthing, Difftastic, rclone, Dolt, and esbuild.
- Did not inspect or edit lane `src`, tests, fixtures, provider credentials, secrets, or live remotes.

## Decision

The previous 18-item backlog was close but not sufficient for the latest support-library direction. It covered document packages, PDF handoffs, source maps, tree-sitter subsets, protobuf wire helpers, checksums, compression/archive helpers, glob/pathspec matching, Unicode, charset, DOCX, legacy DOC/CFB, EPUB, ODT, tables, and shared XML/HTML/ZIP foundations.

Missing bounded support areas were added as deferred candidates, not active or critical work:

- `citation-bibliography-csl-core`: Pandoc citation, bibliography, and CSL conversion behavior.
- `math-tex-conversion-core`: bounded math/TeX conversion and equation handoff for Pandoc, markerPDF, and Readability.
- `sql-storage-codec-core`: Dolt/libsqlite/Quadrable row, value, key, page, and chunk codec support.
- `provider-metadata-normalization-core`: local-only rclone/Syncthing provider and config metadata normalization without live-service tests.

The backlog now has 22 items: 4 critical, 8 high, and 10 medium. Status remains gated: 12 candidate, 10 deferred, 0 active.

## Existing Rows Tightened

- `unicode-text-repair-width` now includes esbuild, Gitoxide, Dolt, and Quadrable in addition to markerPDF, Pandoc, Difftastic, Readability, and LightningCSS.
- `charset-encoding-core` now includes esbuild, LightningCSS, Gitoxide, Dolt, and Quadrable in addition to Difftastic, Readability, Pandoc, markerPDF, and rclone.
- `checksum-hash-suite` now includes Dolt content-addressed storage evidence.
- `archive-compression-streams` now includes Syncthing and explicitly covers bounded LZ4 frame/block handling for mapped protocol payloads.

## Coverage Notes

- Pandoc is covered for DOCX/OpenXML, legacy `.doc` CFB/MS-DOC, EPUB, ODT, PDF text/page/table/OCR handoffs, citation/CSL, math/TeX, ZIP, XML/HTML, Unicode, charset, and table foundations.
- markerPDF is covered for PDF text dictionaries, page/crop planning, OCR/layout result ingestion, table geometry, image/page metadata handoff through the render planning row, malformed/corrupt evidence, and external-engine exclusions.
- esbuild and LightningCSS are covered for source maps, bounded tree-sitter/query compatibility where useful, Unicode, charset, and no Node/Rust/tool shell-out credit.
- rclone and Syncthing are covered for checksums, compression/archive streams including Syncthing LZ4, glob/path filters, protobuf/wire helpers, and local-only provider/config normalization while excluding live remotes by default.
- Gitoxide, Dolt, Difftastic, and Quadrable are covered for hashes/checksums where relevant, compression/pack-adjacent helpers, tree-sitter or bounded grammar support, encoding/Unicode handling, SQL/storage codecs, and protocol/helper boundaries where those are support surfaces instead of the base lane itself.

## Explicit Non-Additions

- No wrappers around LibreOffice/OpenOffice, Pandoc, Tesseract, PDFium, Ghostscript, Poppler, cloud providers, external model stacks, or shell commands were added or counted.
- No separate broad JS/CSS parser runtime item was added because current esbuild/LightningCSS parser/tokenizer work is base-lane native behavior; the backlog keeps only shared source-map, encoding, Unicode, and bounded grammar/query support.
- No separate full Git protocol item was added because Gitoxide's protocol implementation is the base tool scope; shared support remains in checksum, compression/pack-adjacent, glob/pathspec, Unicode/charset, and any future concrete blocker can activate a narrower row.

## Validation

- `jq empty dependency-backlog.json porting-summary.json`: exit 0, no output.
- `php -l tools/generate-dashboard.php`: exit 0, `No syntax errors detected in tools/generate-dashboard.php`.
- `php tools/generate-dashboard.php`: exit 0, `Generated porting.html and porting-summary.json with 12 lanes`.
- `git diff --check`: exit 0, no output.
