# Document Format Dependency Scout - 2026-05-24T085334Z

Scope: audit-only review of document/PDF conversion dependency coverage for Pandoc, markerPDF, and Readability overlap. I did not edit implementation files, lane files, `dependency-backlog.json`, `progress.md`, `porting.html`, or `porting-summary.json`. I did not stage, commit, push, reset, or revert. I used only local bounded reads and `jq`; no web lookups were needed because the local manifests already carry the relevant public upstream/spec evidence.

## Current Coverage Summary

`dependency-backlog.json` currently has enough bounded support-library rows for the essential rich-conversion categories named in the nudge. I do not recommend adding new rows for DOC/DOCX, PDF output, PDF text input, EPUB, ODT/OpenDocument, citations/CSL, math/TeX, tables, ZIP/package containers, XML/HTML, Unicode/charset, or compression/archive pieces. Adding duplicate rows would make the tracker harder to sequence.

Document/PDF rows already present and sufficient:

- `shared-zip-package-core`: covers DOCX, EPUB, ODT, markerPDF benchmark archives, and package containers.
- `xml-html5-dom-core`: covers Pandoc HTML/DocBook/XML surfaces, Readability DOM cleanup, SVG/image metadata, and package XML payloads.
- `docx-openxml-core`: covers bounded DOCX/OpenXML document import/export.
- `legacy-doc-cfb-core`: covers Word 97-2003 `.doc` and Compound File Binary extraction separately from DOCX.
- `epub3-package-core`: covers EPUB package/spine/navigation/assets.
- `odf-open-document-core`: covers ODT/OpenDocument packages without office-suite ports.
- `pandoc-doctemplates-core`: covers Pandoc writer template rendering.
- `citation-bibliography-csl-core`: covers citations, bibliography metadata, CSL data/style parsing, and citeproc-shaped rendering.
- `math-tex-conversion-core`: covers inline/display math, bounded TeX math parsing, MathML/source preservation, and markerPDF/Readability/Pandoc math handoff.
- `pandoc-pdf-engine-handoff-core`: covers Pandoc PDF output orchestration without porting TeX, Typst, browser, or PDF engines.
- `pdf-text-dictionary-core`: covers searchable PDF text/dictionary extraction for markerPDF and later Pandoc PDF input handoff.
- `pdf-page-render-plan-core`: covers page boxes, crops, preview/image placeholder metadata, and supplied renderer callback contracts.
- `layout-ocr-result-core`: covers supplied OCR/layout result ingestion, not OCR/model engines.
- `table-geometry-core`: covers PDF, HTML, and document table geometry/formatting.
- `unicode-text-repair-width` and `charset-encoding-core`: cover non-UTF-8 import, repair, width, segmentation, and display-sensitive conversion behavior.
- `archive-compression-streams`: covers gzip/deflate/tar/LZ4-style stream helpers where ZIP-specific package work is not enough.

Lane evidence supports those rows:

- Pandoc has a cloned static upstream denominator of 2,276 test/data/benchmark artifacts with 1,742 mapped focused checks. The manifest explicitly counts DOCX, ODT, EPUB, HTML reader, tables, citations, Markdown command fixtures, and writer/template evidence. Full Haskell runner parity remains unexecuted, so support rows should require dependency-specific denominators before progress credit.
- markerPDF has 374 counted static behavior/reference units with 325 mapped native semantics and 462 focused PHP behavior tests passing. Evidence includes PDF stream filters, PDF encodings, `/Info` text strings, page content ordering, ToUnicode, Form XObject text, table/equation/OCR supplied handoffs, and benchmark archive inventory. Heavy Python/PDF/model/runtime tools remain blockers, not native progress.
- Readability has upstream `npm test` runner evidence passing 1,984/1,984 Mocha tests over 130 fixture pages, plus 241 local PHP tests passing. It contributes HTML/DOM cleanup, JSON-LD metadata, media, table, math-like technical article, Unicode, and WordPress block serialization evidence.

## Recommended Additions

No new tracker rows are recommended from this document/PDF audit.

Rows that should not be duplicated: `docx-openxml-core`, `legacy-doc-cfb-core`, `epub3-package-core`, `odf-open-document-core`, `pandoc-pdf-engine-handoff-core`, `pdf-text-dictionary-core`, `citation-bibliography-csl-core`, `math-tex-conversion-core`, `table-geometry-core`, `shared-zip-package-core`, `xml-html5-dom-core`, `unicode-text-repair-width`, `charset-encoding-core`, and `archive-compression-streams`.

WXR/WordPress XML should remain covered by `xml-html5-dom-core` for now. Add a WXR-specific row only if a future Pandoc/WXR lane slice exposes a distinct reusable dependency boundary beyond XML parsing/serialization and WordPress block AST handoff.

## Priority And Gate Changes

These are tracker-row recommendations only; they should not activate all rows at once. Suggested order when the base lanes open the matching work: ZIP/package first for Pandoc rich formats; PDF text dictionary first for markerPDF/Pandoc PDF input; XML/HTML immediately after ZIP when package payload parsing is selected; then one concrete format row at a time.

### `shared-zip-package-core`

- neededBy lanes: `pandoc`, `markerpdf`, `rclone`.
- essential capability: DOCX, EPUB, ODT, benchmark archive, and archive package inspection without zip shell-outs.
- scope boundary: central directory parsing, stored/deflated entries, CRC checks, path safety, deterministic writing, and package metadata hooks; exclude encrypted archives and application launchers.
- activation gate: change from only `pandoc-rich-format-next` to `pandoc-rich-format-next-or-markerpdf-benchmark-archive-next-or-rclone-archive-provider-next`. Still activate first only when a concrete package/container slice is selected.
- upstream/spec denominator: PKWARE APPNOTE plus mapped Pandoc DOCX/EPUB/ODT package fixtures and markerPDF benchmark archive fixtures.
- expected PHP evidence: read/list/extract/write/readback, CRC and path-safety checks, package metadata handoff, and mapped fixture parity.
- malformed/corrupt cases: truncated central directory, bad CRC, duplicate names, traversal names, missing required package entries, unsupported compression method.
- reuse notes: one container layer should serve DOCX, EPUB, ODT, markerPDF benchmark archives, and later archive-provider work.
- explicit exclusions: no `zip`/`unzip` shell-outs, no external package tools, no full office suites, no encrypted archive expansion.

### `docx-openxml-core`

- neededBy lanes: `pandoc`.
- essential capability: Word DOCX import/export into the shared Pandoc AST and WordPress block handoff.
- scope boundary: OPC package parts, `document.xml`, relationships, content types, numbering, styles, semantic media references, and bounded writer output; exclude macros, VBA, exact Word layout, and full Office compatibility.
- activation gate: sharpen from generic `pandoc-rich-format-next` to `pandoc-docx-openxml-next` after `shared-zip-package-core` and `xml-html5-dom-core` have the needed reader evidence.
- upstream/spec denominator: Pandoc DOCX reader/writer fixtures plus OpenXML/OPC package specifications.
- expected PHP evidence: semantic AST parity for paragraphs, notes, review spans, tables, styles, media relationships, package readback, and WordPress output fixtures.
- malformed/corrupt cases: missing relationships, missing content types, bad XML, dangling media, cyclic relationships, malformed numbering/styles, corrupt ZIP package.
- reuse notes: build on shared ZIP and XML; share relationship/media helpers with EPUB and ODT.
- explicit exclusions: no Microsoft Word, LibreOffice/OpenOffice, Pandoc, office converter subprocesses, or whole-application automation.

### `legacy-doc-cfb-core`

- neededBy lanes: `pandoc`.
- essential capability: Word 97-2003 `.doc` text/structure extraction without treating DOCX/OpenXML as equivalent.
- scope boundary: CFB directory/FAT/MiniFAT reads, WordDocument and table stream discovery, FIB/piece-table text extraction, fixture-backed paragraph/list/table hints, metadata, and safe errors; exclude OLE execution, macros, and page layout.
- activation gate: sharpen from generic `pandoc-rich-format-next` to `pandoc-legacy-doc-cfb-next`.
- upstream/spec denominator: Microsoft MS-CFB/MS-DOC specifications plus mapped Pandoc or generated legacy `.doc` fixtures.
- expected PHP evidence: CFB stream traversal, text extraction, paragraph/list/table handoff where fixture-backed, metadata, and WordPress block output.
- malformed/corrupt cases: corrupt FAT/MiniFAT chains, missing WordDocument stream, invalid FIB, invalid piece table, truncated text stream, unsupported encryption.
- reuse notes: reuse charset/Unicode repair, table geometry, and shared Pandoc AST helpers.
- explicit exclusions: no Word, LibreOffice/OpenOffice, antiword/wv, Pandoc, binary converter subprocesses, embedded OLE execution, or VBA.

### `epub3-package-core`

- neededBy lanes: `pandoc`, `readability`, `rclone`.
- essential capability: ebook/article packages with ordered spine, metadata, navigation, assets, and WordPress-ready content.
- scope boundary: `container.xml`, OPF metadata, spine/nav/NCX mapping, XHTML asset resolution, media manifest handling, and bounded writer packages; exclude DRM, ebook renderers, store validators, and scripting execution.
- activation gate: sharpen from generic `pandoc-rich-format-next` to `pandoc-epub-package-next-or-readability-epub-export-next`.
- upstream/spec denominator: W3C EPUB 3 specifications plus mapped Pandoc EPUB fixtures and any Readability article-package export fixtures.
- expected PHP evidence: package parse/write/readback, spine/nav ordering, metadata/assets parity, XHTML handoff into AST/blocks.
- malformed/corrupt cases: missing OPF, invalid container path, broken spine refs, duplicate manifest IDs, bad XHTML/XML, corrupt ZIP package.
- reuse notes: reuse ZIP, XML/HTML, charset, Unicode, and media helpers shared with DOCX/ODT and Readability.
- explicit exclusions: no ebook converter shell-outs, external validators as progress, DRM engines, browser renderers, or store applications.

### `odf-open-document-core`

- neededBy lanes: `pandoc`.
- essential capability: ODT/OpenDocument import/export without porting LibreOffice/OpenOffice.
- scope boundary: ODT manifest, `content.xml`, `styles.xml`, `meta.xml`, text/list/table/image mapping required for AST conversion, and bounded writer output; exclude spreadsheets/presentations until separately gated.
- activation gate: sharpen from generic `pandoc-rich-format-next` to `pandoc-odt-open-document-next`.
- upstream/spec denominator: OASIS OpenDocument specifications plus mapped Pandoc ODT fixtures.
- expected PHP evidence: semantic AST parity for text, lists, tables, refs, styles, images, package readback, and WordPress output.
- malformed/corrupt cases: missing manifest/content/style parts, bad XML namespaces, dangling image refs, corrupt ZIP, unsupported encrypted package.
- reuse notes: build on shared ZIP/XML plus table and Unicode width helpers.
- explicit exclusions: no LibreOffice/OpenOffice applications, macro execution, spreadsheet/presentation expansion, page-layout engine, or converter shell-outs.
- priority recommendation: raise from `medium` to `high`, but keep `deferred` until the ODT-specific gate opens.

### `pdf-text-dictionary-core`

- neededBy lanes: `markerpdf`, `pandoc`.
- essential capability: searchable PDF text, spans, boxes, fonts, rotations, TOC/metadata into structured import blocks or a Pandoc PDF input handoff.
- scope boundary: pdftext-compatible dictionary schema, bounded content-stream text extraction, stream filters, text operators, page ranges, fonts/encodings, boxes, and metadata handoff; exclude full rendering, forms/signatures beyond mapped metadata, and PDF applications.
- activation gate: sharpen from `markerpdf-pdf-text-next-or-markerpdf-ocr-next` to `markerpdf-pdf-text-next-or-pandoc-pdf-input-handoff-next`. `markerpdf-ocr-next` alone should open `layout-ocr-result-core` unless searchable text/dictionary extraction is also selected.
- upstream/spec denominator: PDF text/content-stream operators and filters, markerPDF/pdftext dictionary evidence, and mapped markerPDF benchmark/reference pairs.
- expected PHP evidence: stream filter decoding, ToUnicode/simple-font encodings, text-position operators, dictionary conversion, page slicing, metadata, and WordPress/Pandoc handoff parity.
- malformed/corrupt cases: bad xref/object refs, corrupt filters, truncated streams, invalid CMap, unknown encodings, missing page resources, malformed dictionary payloads.
- reuse notes: feed markerPDF now and Pandoc PDF-oriented imports later; share geometry with table and OCR/result cores.
- explicit exclusions: no pdftext subprocess calls, Poppler, PDFium, Ghostscript, pypdfium, OCR/model engines, or external PDF converters.

### `table-geometry-core`

- neededBy lanes: `markerpdf`, `pandoc`, `readability`.
- essential capability: PDF, HTML, DOCX/ODT/Markdown, and article tables into usable Markdown, HTML, AST, and WordPress table blocks with spans/alignment preserved.
- scope boundary: bbox row/column assignment, spanning, multiline merge, column clustering, Unicode-width Markdown padding, CSV/HTML/Markdown formatting, and AST handoff; exclude spreadsheet engines and CV table-detection inference.
- activation gate: change from only `markerpdf-table-next` to `markerpdf-table-next-or-pandoc-table-rich-next-or-readability-table-cleanup-next`.
- upstream/spec denominator: markerPDF/tabled table evidence, Pandoc table fixtures, HTML table semantics needed by Readability, and mapped WordPress table outputs.
- expected PHP evidence: row/column/span parity, formatter parity, Unicode width alignment, Pandoc AST table read/write, Readability retained data-table cleanup, and WordPress block output.
- malformed/corrupt cases: overlapping cells, zero-area boxes, row/column gaps, malformed rowspan/colspan, invalid Markdown table rows, bad supplied detector output.
- reuse notes: share row/column and width helpers across markerPDF, Pandoc, and Readability.
- explicit exclusions: no CV table-detection model ports, spreadsheet engines, Pandoc shell-outs, or renderer-based layout reconstruction.

### `xml-html5-dom-core`

- neededBy lanes: `pandoc`, `readability`, `markerpdf`, `difftastic`, `rclone`.
- essential capability: safe shared XML/HTML parse and serialization for Pandoc readers/package payloads, Readability cleanup, markerPDF HTML/image output, and XML protocol payloads.
- scope boundary: tokenizer/tree construction needed by readers, namespaces, entities, comments, fragments, and safe serializers; exclude browser layout, JavaScript, CSS layout, network fetching, and full browser DOM compatibility.
- activation gate: sharpen from `shared-infra-after-base-green` to `pandoc-html-docbook-next-or-pandoc-rich-package-xml-next-or-readability-dom-parser-gap-next-or-rclone-webdav-xml-next`.
- upstream/spec denominator: WHATWG HTML, XML 1.0/namespaces, Pandoc HTML/DocBook/OpenXML/EPUB/ODT fixtures, Readability fixtures, and WebDAV XML fixtures where selected.
- expected PHP evidence: parse/serialize round trips, entity/namespace/comment handling, fragments, malformed HTML recovery where selected, XML package payload parsing, and lane-specific output parity.
- malformed/corrupt cases: unclosed tags, invalid nesting, bad entities, namespace collisions, invalid XML chars, oversized/deep trees, broken package XML.
- reuse notes: one parser/serializer surface should serve Pandoc, Readability, markerPDF, Difftastic, and rclone instead of per-lane divergent parsers.
- explicit exclusions: no browser engines, JS execution, DOM service wrappers, converter engines, or network fetchers.

### `charset-encoding-core`

- neededBy lanes: `difftastic`, `readability`, `pandoc`, `markerpdf`, `rclone`, `esbuild`, `lightningcss`, `gitoxide`, `dolt`, `quadrable`.
- essential capability: declared or known byte-decoding before structural parsing, including PDF/document/web import text that is not clean UTF-8.
- scope boundary: BOM handling, UTF-8/UTF-16, Windows-1252, declared HTML/XML charset handling, PDFDocEncoding/WinAnsi/MacRoman maps where conversion-boundary fixtures require them, replacement policy, binary detection hints, and byte-span preservation; exclude broad statistical charset detection unless separately fixture-gated.
- activation gate: sharpen from `markerpdf-pdfdocencoding-next-or-shared-nonutf8-import-next` to `markerpdf-pdfdocencoding-next-or-pandoc-legacy-doc-cfb-next-or-pandoc-html-charset-next-or-readability-declared-charset-next-or-shared-nonutf8-import-next`.
- upstream/spec denominator: WHATWG Encoding plus PDF/document encoding tables and mapped markerPDF/Pandoc/Readability byte fixtures.
- expected PHP evidence: decoded text parity, replacement behavior, span preservation, binary-vs-text decisions, declared charset handling, and WordPress output.
- malformed/corrupt cases: invalid byte sequences, mixed BOM/declaration conflicts, truncated UTF-16, undefined bytes, overlong UTF-8, binary payload misclassification.
- reuse notes: sits below PDF text, Pandoc HTML/DOC imports, Readability HTML, source diffs, and package metadata.
- explicit exclusions: no `iconv`/`recode` shell-outs, broad detector services, translation, or NLP.

### `math-tex-conversion-core`

- neededBy lanes: `pandoc`, `markerpdf`, `readability`.
- essential capability: inline/display math and extracted equations through document, PDF, and web imports without losing technical content.
- scope boundary: inline/display math tokenization, bounded TeX math macro parsing, MathML/LaTeX source preservation, equation block/span AST handoff, and renderer-neutral output decisions; exclude full TeX engines, symbolic algebra, browser math runtimes, and image recognition.
- activation gate: change from only `pandoc-math-next` to `pandoc-math-next-or-markerpdf-equation-handoff-next-or-readability-mathjax-next`.
- upstream/spec denominator: Pandoc math fixtures, markerPDF supplied Formula/equation evidence, Readability MathJax/math article fixtures, TeX math subset and MathML specs where mapped.
- expected PHP evidence: inline/display parsing, source preservation, MathML/LaTeX handoff, AST/Markdown/HTML/WordPress output, and deterministic unsupported-macro diagnostics.
- malformed/corrupt cases: unclosed delimiters, malformed MathML, unknown macros, nested environments, bad OCR/result payloads, invalid Unicode math symbols.
- reuse notes: share Unicode/charset repair, AST helpers, and markerPDF supplied-result surfaces.
- explicit exclusions: no TeX/LaTeX engine, MathJax/KaTeX runtime, Texify, Surya, Torch/model inference, Pandoc shell-outs, or equation-recognition engines.
- priority recommendation: raise from `medium` to `high`, but keep `deferred` until one concrete math gate opens.

### `citation-bibliography-csl-core`

- neededBy lanes: `pandoc`.
- essential capability: preserve citations, bibliography metadata, and rendered references for academic/technical conversion into AST and WordPress blocks.
- scope boundary: citation AST normalization, fixture-backed CSL style/data parsing, bibliography metadata handoff, citation cluster rendering needed for conversion parity, and deterministic bibliography block output; exclude bibliography manager applications and network sync.
- activation gate: keep deferred, but sharpen from `pandoc-citation-next` to `pandoc-citation-bibliography-next`.
- upstream/spec denominator: Pandoc citation fixtures, Citation Style Language, CSL JSON, BibTeX/BibLaTeX where mapped, and citeproc behavior needed by conversion parity.
- expected PHP evidence: citation record parsing, citation cluster rendering parity, bibliography AST/output parity, metadata handoff, and WordPress output.
- malformed/corrupt cases: bad CSL JSON, invalid style XML, missing keys, cyclic crossrefs, invalid BibTeX/BibLaTeX fields, unsupported locale/style terms.
- reuse notes: build on XML/HTML, JSON, Unicode/charset, and Pandoc AST helpers; keep library management out of scope.
- explicit exclusions: no Zotero/Mendeley apps, network lookups, style catalog sync, BibTeX/Biber subprocesses, Pandoc shell-outs, or citeproc service wrappers.
- priority recommendation: raise from `medium` to `high`, but keep `deferred` until the citation-specific gate opens.

## Rows To Keep As-Is

- `pandoc-pdf-engine-handoff-core`: already has the correct boundary. It should remain high/deferred until `pandoc-pdf-output-next`; it must use fake/supplied engine runners and must not port or execute TeX, ConTeXt, Typst, Groff, WebKit, WeasyPrint, Prince, PagedJS, or PDF converter engines as progress.
- `pdf-page-render-plan-core`: keep high/candidate behind `markerpdf-ocr-next`; it is a planning/callback-contract row, not a renderer row.
- `layout-ocr-result-core`: keep critical/candidate behind `markerpdf-ocr-next`; it should ingest supplied hOCR/TSV/plain/Surya-style results and never count Tesseract/OCRMyPDF/Ghostscript/Surya/Torch/Texify execution as native progress.
- `unicode-text-repair-width`: existing row is sufficient; activate only for a concrete text-repair, width, segmentation, or table-alignment blocker.
- `archive-compression-streams`: existing row is sufficient and should stay separate from ZIP. Use it for gzip/tar/LZ4/stream helpers beyond package ZIP deflate; do not duplicate `shared-zip-package-core`.
- `json-json5-document-core`: sufficient for Readability JSON-LD, Pandoc metadata, and CSL JSON support. Do not create a separate JSON-LD-only dependency row unless it becomes a distinct reusable parser boundary.

## Explicit Rejects

Do not count these as support-library dependency progress: OpenOffice, LibreOffice, Microsoft Word, Ghostscript, PDFium, Poppler, pypdfium, PIL/Pillow rendering, Tesseract, OCRMyPDF, Torch, Surya, Texify, Nougat, MathJax/KaTeX runtimes, full TeX/LaTeX/ConTeXt/Typst/Groff/WebKit/WeasyPrint/Prince/PagedJS engines, Streamlit, FastAPI, Uvicorn, Pandoc executable shell-outs, antiword/wv, ebook converter shell-outs, office converter shell-outs, model stacks, service wrappers, live cloud/provider applications, credential/auth flows, or any converter subprocess used as native progress.

Supplied adapters, fake runners, generated fixtures, or public upstream oracle checks may be useful test scaffolding, but they must be labeled as scaffolding/oracle evidence and must not count as native PHP component progress.

## Local Files Inspected

- `goal.md`
- `progress.md`
- `dependency-backlog.json`
- `audits/support-library-progress-tracker-20260524T083724Z.md`
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `lanes/pandoc/lane-status.json`
- `lanes/pandoc/notes/upstream-inventory.md`
- `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`
- `lanes/markerpdf/lane-status.json`
- `lanes/markerpdf/notes/upstream-test-inventory.md`
- `lanes/readability/UPSTREAM_TEST_MANIFEST.json`
- `lanes/readability/lane-status.json`

I also used bounded `rg --files` and `rg -n` over the named audit/tracker/lane evidence files to confirm path existence and relevant document/PDF dependency references. I did not read secret-bearing inputs, process environments, credential stores, provider configs, browser/OAuth state, or cloud remotes.

## Checks Run

Read-only inspection commands:

- `sed -n '1,220p' goal.md`
- `sed -n '1,260p' progress.md`
- `sed -n '1,260p' audits/support-library-progress-tracker-20260524T083724Z.md`
- `sed -n '1,260p' lanes/pandoc/notes/upstream-inventory.md`
- `sed -n '1,280p' lanes/markerpdf/notes/upstream-test-inventory.md`
- `jq` summaries and selected-row reads over `dependency-backlog.json`
- `jq` summaries over the Pandoc, markerPDF, and Readability manifest/status JSON files
- bounded `rg -n` dependency-term search over the inspected audit/tracker/lane evidence files

Final validation commands:

- `jq empty dependency-backlog.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json lanes/readability/UPSTREAM_TEST_MANIFEST.json lanes/readability/lane-status.json`: passed with exit 0 and no output.
- `git diff --check -- audits/doc-format-dependency-scout-20260524T085334Z.md`: passed with exit 0 and no output.

## Unresolved Blockers

No blocker for this audit artifact. The broader lane root-harness and full upstream-runner blockers recorded in lane status files remain outside this scout's audit-only scope.
