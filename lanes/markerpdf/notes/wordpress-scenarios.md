# markerPDF WordPress Scenario

PDF import into clean post content and Data Liberation document conversion workflows.

## Current Native Slice

Native PDF content stream text-line extraction for literal, array, hex, UTF-16 hex, FlateDecode streams, adjacent same-line text operators, PDF line continuations, and text line movement operators.

The 2026-05-24 reduced handoff adds only the minimal positioned-text foundation and text-state spacing slice. `PdfTextExtractor` now estimates text end positions from font size and text-showing operands before same-line `Tm` gap decisions, then applies `Tc`, `Tw`, `Tz`, and double-quote showing spacing operands to that estimate. `examples/wordpress-pdf-text-state-spacing-import.php` demonstrates the WordPress import effect by emitting `Database`, `Intro`, `Import Profiles`, and `Media Importer` as clean Gutenberg paragraphs rather than `Data base`, `Profile s`, or `Import er`, without running Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

The 2026-05-24 19:13 UTC graphics-state slice keeps those text-state spacing fields scoped through PDF `q`/`Q` save and restore. `examples/wordpress-pdf-graphics-state-import.php` demonstrates that a scoped character-spacing override used for one positioned fragment does not leak into the later `Import Tool` text, producing one clean Gutenberg paragraph without Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

The 2026-05-24 19:30 UTC TJ positioning slice applies numeric adjustments inside PDF `TJ` arrays to the same native end-X estimate before later `Tm` gap decisions. `examples/wordpress-pdf-tj-positioning-import.php` demonstrates the WordPress import effect by emitting `Import Profiles` and `SiteMap Index` as readable Gutenberg paragraphs without Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

The 2026-05-24 19:52 UTC Tm horizontal-scale slice applies a non-identity text matrix horizontal scale to the same native end-X estimate before later `Tm` gap decisions. `examples/wordpress-pdf-tm-horizontal-scale-import.php` demonstrates the WordPress import effect by emitting `Import Profiles` from widened matrix text and `SiteMap Index` from compressed matrix text without Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

The 2026-05-24 20:08 UTC ASCIIHex stream-filter slice decodes encoded PDF content streams before native text-token parsing, including an ordered `/ASCIIHexDecode` then `/FlateDecode` filter-array path. `examples/wordpress-pdf-asciihex-filter-import.php` demonstrates the WordPress import effect by emitting `Encoded PDF Import` and `Clean WordPress Blocks` from an encoded content stream without Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

The 2026-05-24 22:58 UTC literal-string escape slice decodes PDF literal escapes in `Tj` and `TJ` text operands before native line assembly. `examples/wordpress-pdf-literal-escape-import.php` demonstrates the WordPress import effect by emitting `Editor's (PDF) import notes`, `Clean+blocks keep nested (review) text`, and `Linecontinued and slashqkept` from escaped delimiters, octal bytes, CRLF continuation, nested `TJ` literal text, and an unknown escaped byte without Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

The 2026-05-24 23:20 UTC indirect filter/DecodeParms slice resolves `/Filter` and `/DecodeParms` through indirect objects before native stream decoding. `examples/wordpress-pdf-indirect-filter-import.php` demonstrates the WordPress import effect by emitting `Indirect PDF Filter Import` and `DecodeParms Predictor One` from an indirect FlateDecode stream with `/Predictor 1` parameters without Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

The 2026-05-24 23:37 UTC ASCII85 stream-filter slice decodes `/ASCII85Decode` and `/A85` content streams before native text-token parsing, including optional delimiters, whitespace, `z` zero groups, partial final groups, and a stacked ASCII85-to-Flate path. `examples/wordpress-pdf-ascii85-filter-import.php` demonstrates the WordPress import effect by emitting `ASCII85 PDF Import` and `Block Ready Content` as Gutenberg paragraphs without Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

The 2026-05-25 00:25 UTC RunLength stream-filter slice decodes `/RunLengthDecode` and `/RL` content streams before native text-token parsing, including literal runs, repeated-byte runs, EOD termination, malformed truncation rejection, and a stacked RunLength-to-Flate path. `examples/wordpress-pdf-runlength-filter-import.php` demonstrates the WordPress import effect by emitting `RunLength PDF Import` and `Block Ready Content` as Gutenberg paragraphs without Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

The 2026-06-02 LZW stream-filter slice decodes `/LZWDecode` content streams before native text-token parsing, including variable-width dictionary growth and reset/end-code handling. `examples/wordpress-pdf-lzw-filter-import.php` demonstrates the WordPress import effect by emitting `LZW PDF Import` and `Native Blocks` as Gutenberg paragraphs without Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

The 2026-05-25 01:02 UTC Flate predictor slice applies `/FlateDecode` `/DecodeParms` predictors after inflation, including TIFF Predictor 2 horizontal differencing and PNG Predictor 10-15 row filters before native text-token parsing. `examples/wordpress-pdf-flate-predictor-import.php` demonstrates the WordPress import effect by emitting `Predictor PDF Import` and `Block Ready Content` as Gutenberg paragraphs without Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

The 2026-05-25 01:28 UTC PDF name escape slice decodes `#XX` escapes in PDF name tokens before native stream decoding and ToUnicode font lookup. `examples/wordpress-pdf-escaped-name-import.php` demonstrates the WordPress import effect by emitting `ImportBlocks` from an escaped `/F#31` font resource and covering an escaped `/Fl#61teDecode` filter path without Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

The 2026-05-25 02:36 UTC ToUnicode bfrange-array slice parses explicit destination arrays in `beginbfrange` CMap blocks before native text lookup. `examples/wordpress-pdf-cmap-bfrange-import.php` demonstrates the WordPress import effect by emitting `Import Blocks` as a Gutenberg paragraph from encoded glyph IDs whose Unicode targets are not a simple sequential range, without Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

The 2026-05-25 03:19 UTC ToUnicode usecmap slice resolves named base CMap inheritance before local ToUnicode entries. `examples/wordpress-pdf-cmap-usecmap-import.php` demonstrates the WordPress import effect by emitting `Import Blocks` as a Gutenberg paragraph from a derived CMap that inherits base glyph mappings and supplies a local space mapping, without Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

The 2026-05-25 06:05 UTC ToUnicode codespacerange fallback slice chooses CMap source-code widths before applying mappings or fallback decoding. `examples/wordpress-pdf-cmap-codespace-fallback-import.php` demonstrates the WordPress import effect by emitting `A` as a Gutenberg paragraph from an unmapped two-byte CID without letting an unrelated one-byte mapping split the source bytes, and without Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

The 2026-05-25 17:39 UTC simple-font Encoding Differences slice decodes `/Encoding << /Differences [...] >>` glyph names when a PDF font lacks a `/ToUnicode` CMap. `examples/wordpress-pdf-encoding-differences-import.php` demonstrates the WordPress import effect by emitting `WP Import Blocks` as a Gutenberg paragraph from custom single-byte glyph codes without Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

The 2026-05-25 23:43 UTC simple-font WinAnsiEncoding slice decodes `/Encoding /WinAnsiEncoding` high-bit punctuation when a PDF font lacks a `/ToUnicode` CMap. `examples/wordpress-pdf-winansi-import.php` demonstrates the WordPress import effect by emitting curly quotes, an en dash, and apostrophe punctuation in `“Data Liberation” – WP’` as a Gutenberg paragraph without Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

The 2026-06-02 Type0 Identity-H/V CMap slice uses direct font `/Encoding /Identity-H` and `/Encoding /Identity-V` code-space widths when no `/ToUnicode` CMap is present. `examples/wordpress-pdf-identity-cmap-import.php` demonstrates the WordPress import effect by emitting `WP Import` and `Blocks!` as Gutenberg paragraphs from two-byte CIDs without raw NUL bytes and without Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

The 2026-06-01 00:18 UTC page `/Contents` stream slice follows the PDF catalog page tree, merges array-valued `/Contents` streams per page, preserves page-order `naive_get_text` boundaries, and excludes unrelated unreferenced stream objects such as form XObjects from imported text. `examples/wordpress-pdf-page-contents-import.php` demonstrates the WordPress import effect by emitting `Page One Intro`, `Clean Blocks`, and `Second Page` as Gutenberg paragraphs while recording `excluded_unreferenced_stream_text=true`, without Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

The 2026-06-02 Form XObject invocation slice expands only page-resource `/Subtype /Form` XObjects that are invoked by the page content `Do` operator, preserving execution order and continuing to exclude unreferenced form streams. `examples/wordpress-pdf-xobject-form-import.php` demonstrates the WordPress import effect by emitting `Page Before Form`, `Reusable Form Block`, `Imported Once`, and `Page After Form` as Gutenberg paragraphs while recording `invoked_form_text_imported=true` and `excluded_unreferenced_form_text=true`, without Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

The lane now also maps the upstream `pdftext` dictionary boundary from `marker/pdf/extract_text.py::pdftext_format_to_blocks`. `PdfTextBlockConverter` converts supplied pdftext page dictionaries into Marker's native Page/Block/Line/Span arrays, including font flag suffixes, span IDs, rotation-aware page bboxes, and pdftext hyphen/newline cleanup before later layout annotation.

The lane now also maps the supplied-data boundary of `marker/pdf/extract_text.py::get_text_blocks`. `PdfTextDocumentExtractor` applies upstream `start_page`/`max_pages` page-range semantics to supplied pdftext dictionaries, restarts span IDs relative to the selected range, preserves original PDF page numbers, and carries PDF TOC metadata for partial WordPress imports.

The lane now also maps `marker/pdf/extract_text.py::naive_get_text` and `get_length_of_text`. `PdfTextExtractor` appends one newline per extracted page-text boundary, trims before length counting, and exposes the same preflight value that top-level `convert.py --min_length` uses before queuing heavier model work.

The lane now also maps `marker/schema/page.py` page helper properties. `PageInspector` flattens page lines, filters nonblank lines and spans, extracts font-size and line-height distributions, and exposes Marker's `prelim_text` view for WordPress review metadata before editorial handoff.

The lane now also maps focused `marker/schema/block.py` block-structure helpers. `BlockStructure` computes line-derived block bboxes, splits a PDF text block at an upstream-style line index boundary, and reports the first-span x-coordinate needed by import review tooling.

The lane now also maps `marker/pdf/utils.py::find_filetype`. `FiletypeDetector` uses magic-MIME style detection to accept PDF uploads, reject extension-spoofed non-PDF payloads as `other`, and preserve the upstream settings-backed MIME mapping branch.

The lane now also maps top-level `convert.py` batch processing. `BatchConverter` plans folder conversions with upstream chunk sizing, loads `--metadata_file` JSON keyed by basename, applies existing-output skips, min-length preflight, empty-output skips, nonfatal error reporting, and Marker-style output artifact persistence around a supplied native conversion callback.

The lane now also maps top-level `chunk_convert.py` and `chunk_convert.sh` sharded conversion planning. `ChunkConversionPlanner` validates `NUM_DEVICES`, `NUM_WORKERS`, input/output folders, optional metadata/min-length flags, and emits one queueable Marker job per device with upstream chunk arguments without executing the upstream subprocess.

The lane now also maps top-level `convert_single.py` single-document processing. `SingleDocumentConverter` preserves upstream comma-separated language parsing, hands `max_pages`, `start_page`, `langs`, and `batch_multiplier` to a supplied converter callback, saves through Marker's per-document output layout, and intentionally persists empty output because the upstream single-file script does not apply batch skip gates.

The lane now also maps the supplied-output boundary of `marker/layout/layout.py::surya_layout`. `LayoutAnnotator` computes the upstream layout batch size, records text-line detections that would be passed to Surya, assigns supplied layout predictions with Python `zip` semantics, and then feeds the existing native annotation path before WordPress block rendering.

The lane now also maps the pure supplied-boundary half of `marker/ocr/recognition.py::surya_recognition`. `OcrRecognition` scales detector polygons for the higher-resolution Surya OCR pass, drops zero-area polygons before model handoff, reconstructs Marker Page/Block/Line/Span arrays from supplied OCR text lines, and feeds those pages through the existing `run_ocr` stats/replacement boundary.

The lane now also maps `marker/debug/data.py::dump_bbox_debug_data`. `DebugDataExporter` writes Marker-style bbox JSON when debug mode is enabled, omits page images and heavy layout/text-line model fields, and keeps the layout labels, block boxes, and text-line boxes that WordPress import reviewers need.

The lane now also maps `marker/debug/render.py::render_on_image` at the operation-planning boundary. `DebugRenderPlanner` preserves upstream bbox integer casting, `draw_bbox`, scalar/per-box colors, label offsets, white label backgrounds, and zero-size label skipping so a WordPress admin preview can draw the same overlays without PIL or font downloads.

The lane now also maps `marker/debug/data.py::draw_page_debug_images` and `draw_layout_page_debug_images` at the artifact-planning boundary. `DebugPageImagePlanner` preserves the upstream `DEBUG` guard, document-base debug folder, `layout_page_N.png` and `pdf_page_N.png` artifact names, text-line image size, PDF-line text overlays, detector-line boxes, layout labels, order labels, and block overlays for a WordPress admin review preview without PIL.

The lane now also maps `marker_app.py::img_to_html` and `marker_app.py::markdown_insert_images`. `MarkdownImageEmbedder` turns supplied PNG image bytes into upstream-style data URI image HTML and replaces Marker Markdown image spans for WordPress preview/review screens without loading Streamlit or pypdfium.

The lane now also maps `marker_app.py::open_pdf`, `page_count`, and `get_page_image` at the upload-preview boundary. `MarkerAppPreview` counts PDF pages from uploaded bytes, preserves direct or inherited page boxes, and emits the pypdfium-style page index, scale, RGB conversion, default annotation mode, and rendered-size metadata a WordPress review screen needs before any raster preview adapter runs.

The lane now also maps `marker_server.py` API/upload behavior. `MarkerServerAdapter` normalizes FastAPI-style request params, validates uploaded PDFs, writes and removes the temporary upload path, returns Marker's local success/error response shape with base64 image payloads, and models Datalab remote polling through a supplied callback rather than running FastAPI, Uvicorn, requests, or Python models.

The lane now also maps `marker/models.py` and `marker/utils.py` at the model-loading preflight boundary. `ModelPipelinePlanner` records the upstream `PYTORCH_ENABLE_MPS_FALLBACK` environment flag, setup helper loader and processor attachment semantics, `load_all_models` load order versus the returned `model_lst` order consumed by `convert_single_pdf`, explicit device/dtype propagation, and CUDA-only cache cleanup without importing Python, Torch, Surya, Texify, or tabled models.

The lane now also maps `marker/logger.py`, `run_marker_app.py`, and `marker_app.py` runtime environment setup. `MarkerRuntimePlanner` records Marker's root/logging/warning suppression choices, Streamlit command, `IN_STREAMLIT=true` and `PDFTEXT_CPU_WORKERS=1` overrides, and Marker app import-time `PYTORCH_ENABLE_MPS_FALLBACK=1` metadata for a WordPress import worker without launching Streamlit, Python, or model code.

The lane now also maps the early `marker/convert.py::convert_single_pdf` orchestration boundary. `CorePdfConverter` applies metadata language override, engine-specific OCR language normalization, OCR-all-pages folding, filetype metadata, unsupported-filetype short-circuiting, supplied page/TOC metadata, and low-resolution image render planning before handing supplied pages to a native downstream pipeline.

The lane now also maps a document-level supplied-boundary conversion path for `marker/convert.py::convert_single_pdf`. `SuppliedDocumentConverter` composes supplied pdftext page dictionaries, layout predictions, ordering predictions, recognized table dictionaries, table Markdown insertion, supplied image payloads, upstream-shaped no-OCR `ocr_stats`, the upstream zero-extracted-block short-circuit after OCR, and the late finalizer into a BenchmarkRunner-ready native callback without loading pdftext, pypdfium2, Surya, tabled, Texify, Torch, or Nougat. The `switch_trans.pdf` supplied page slices use that path to replace extracted Contents text with one upstream-shaped recognized Markdown table for a Gutenberg TOC/table preview and to import Table 1 benchmark cells plus caption text as a Gutenberg table preview; the forced-OCR table slice routes supplied detector cells plus OCR text through the upstream `get_cells`/`recognize_tables` boundary when `OCR_ALL_PAGES` forces table cell redetection.

The lane now also ports a narrow slice of `marker/postprocessors/markdown.py`: hyphenated line dewrapping, sentence paragraph breaks, heading/list/text wrapping, and `#` escaping. That gives the WordPress import path a native cleanup step before block rendering.

`examples/wordpress-import.php` reads `fixtures/wordpress-import-content.pdf` and emits heading/paragraph block comments. This keeps the lane tied to a practical Data Liberation workflow: extracting embedded PDF text into block-ready post content without shelling out to Python or native PDF binaries.

`examples/wordpress-import-wrapped.php` reads `fixtures/wordpress-wrapped-content.pdf`, dewraps split PDF text lines with `MarkdownPostProcessor`, and emits a clean paragraph block:

```html
<!-- wp:paragraph -->
<p>Clean hyphenated paragraphs keep WordPress imports readable.</p>
<!-- /wp:paragraph -->
```

`examples/wordpress-quality-score.php` uses the native `BenchmarkScorer` port of `marker/benchmark/scoring.py` to compare extracted and dewrapped import text against expected WordPress post content. It emits a JSON score and checks whether the result clears a Marker CI-style quality threshold.

`examples/wordpress-benchmark-report.php` uses the native `BenchmarkReportBuilder` port of `benchmarks/overall.py` and `BenchmarkReportVerifier` port of `scripts/verify_benchmark_scores.py` against the actual CI `benchmark_data_short.zip` references for `multicolcnn.pdf` and `switch_trans.pdf`. This gives WordPress import tooling a review gate: imported block content can be scored into the upstream report shape and rejected before editorial review if either Marker threshold fails.

`examples/wordpress-benchmark-runner.php` uses the native `BenchmarkRunner` port of the outer `benchmarks/overall.py` loop. It stages supplied PDF/reference pairs from the actual CI benchmark excerpts, runs a supplied native marker conversion callback, writes upstream-style `marker_*.md` outputs, and verifies the upstream marker thresholds before a WordPress import batch reaches editorial review.

`examples/wordpress-supplied-document-benchmark.php` maps the new document-level supplied-boundary path into a WordPress quality gate. It converts supplied pdftext/layout/order/table/image dictionaries into block-ready Markdown, runs that Markdown through the upstream benchmark report shape, and reports the supplied model boundaries, table count, and image count that a WordPress import UI can store as review metadata.

`examples/wordpress-supplied-equation-import.php` maps the supplied-document path with a Formula layout region and supplied Texify-style equation result dictionary. It replaces the extracted equation text after span/code cleanup, emits a Gutenberg HTML math block, and reports upstream-shaped equation stats without loading Texify, pypdfium, or Torch.

`examples/wordpress-texify-equation-batch-preflight.php` maps Marker's upstream Texify equation-recognition batching control flow into a WordPress import worker preflight. It calculates batch size and dynamic `max_tokens`, accepts supplied model outputs, blanks run-on predictions at the same sentinel boundary as `get_latex_batched`, and keeps the slice native without loading Texify.

`examples/wordpress-multicolcnn-supplied-benchmark.php` maps a fuller upstream `multicolcnn.pdf` supplied-dictionary excerpt into a WordPress Data Liberation quality gate. It imports supplied pdftext/layout/order payloads for the title, authors, abstract, and introduction, preserves no-OCR `ocr_stats`, keeps Python-style hyphen title casing for `Perspective-Free`, and reports a `0.9778095238095238` score against the committed surrogate threshold.

`examples/wordpress-switch-transformers-supplied-benchmark.php` maps a fuller upstream `switch_trans.pdf` supplied-dictionary excerpt into a WordPress Data Liberation quality gate. It imports supplied pdftext/layout/order payloads for the title, authors, abstract, and introduction, preserves styled emphasis from pdftext spans, and reports a `0.8827096774193548` score against the committed surrogate threshold.

`examples/wordpress-switch-transformers-toc-table-import.php` maps the upstream committed Marker `switch_trans.pdf` Contents table page slice into a WordPress Data Liberation TOC review. It imports supplied pdftext/layout/order/recognized-table payloads, replaces the raw extracted TOC lines with one recognized Markdown table, emits a Gutenberg table preview, and reports a `0.527` score against the inspected Marker output excerpt.

`examples/wordpress-switch-transformers-table1-import.php` maps the upstream committed Marker `switch_trans.pdf` Table 1 benchmark page slice into a WordPress Data Liberation table review. It imports supplied pdftext/layout/order/recognized-table/caption payloads, preserves Unicode `Speed (↑)` and `Not achieved†` text through upstream-style Markdown padding, emits a Gutenberg table preview, and reports a `0.9122169059011164` score against the inspected Marker output excerpt.

`examples/wordpress-forced-ocr-table-import.php` maps forced table cell redetection for scanned PDFs. It imports supplied detector cells and supplied OCR text, records `needs_ocr` metadata, and renders a Gutenberg-ready Markdown table without requiring Python model workers on shared hosting.

`examples/upstream-surrogate-score.php` scores small README-linked surrogate pairs sampled from Marker's committed `data/examples/marker` and `data/examples/nougat` outputs. These remain useful comparison fixtures alongside the now-inspected external CI benchmark archive.

`examples/wordpress-list-import.php` maps Marker's upstream bullet/text cleaners into a Gutenberg list import path. It extracts PDF text lines containing Marker-supported bullet glyphs, normalizes them to Markdown `- ` markers with `TextCleaner`, and emits a core list block.

`examples/wordpress-header-footer-import.php` maps Marker's upstream header/footer cleaner into a repeated-page cleanup path. It removes common first/last page lines after Marker's three-page minimum and emits only the imported body paragraphs as core paragraph blocks.

`examples/wordpress-code-block-import.php` maps Marker's upstream code cleaner into a Gutenberg code-block import path. It uses the native line-length, comment-prefix, indentation-majority, and indentation-reconstruction heuristics from `marker/cleaners/code.py` to keep PDF code samples out of ordinary paragraph blocks.

`examples/wordpress-inline-style-import.php` maps Marker's upstream font-style cleaner and styled-span Markdown post-processing into a paragraph import path. It detects bold and italic spans from PDF font names/weights, emits upstream-style `**bold**` and `*italic*` markers, and converts that focused inline Markdown into `<strong>` and `<em>` inside a core paragraph block.

`examples/wordpress-span-merge-import.php` maps Marker's upstream `merge_spans` wrapper into the paragraph import path. It converts Page/Block/Line/Span arrays into Marker's MergedBlock shape, skips empty-span lines, carries lowercased font metadata, applies the upstream first/last-span emphasis guard, and renders the resulting Markdown emphasis as `<strong>` and `<em>` inside a core paragraph block.

`examples/wordpress-pdftext-block-import.php` maps Marker's upstream pdftext dictionary conversion into a shared-hosting WordPress import path. It accepts already-supplied pdftext output, produces Marker's Page/Block/Line/Span shape natively, normalizes span text, preserves font metadata, and feeds Gutenberg paragraph rendering without loading the Python pdftext, pypdfium, or Surya stacks.

`examples/wordpress-pdftext-page-range-import.php` maps Marker's upstream `get_text_blocks` page-range boundary into a partial WordPress import path. It selects only the requested pages from supplied pdftext dictionaries, preserves page metadata and TOC review comments, and emits Gutenberg paragraph blocks without loading pdftext, pypdfium, or Surya.

`examples/wordpress-pdftext-dictionary-core-import.php` maps a stricter `marker/pdf/extract_text.py` dictionary-output boundary into the WordPress import path. It validates supplied pdftext page dictionaries, preserves font metadata, `char_start_idx`, `char_end_idx`, `chars`, rotation, and selected-range `pdftext_options`, then emits a Gutenberg paragraph without loading Python pdftext, pypdfium, or model workers.

`examples/wordpress-text-length-preflight.php` maps Marker's upstream `get_length_of_text` boundary into a WordPress import queue preflight. It records the native naive text, trimmed text length, min-length threshold, and whether the PDF should enter the heavier conversion queue.

`examples/wordpress-page-inspection-preflight.php` maps Marker's upstream Page helper properties into a WordPress review preflight. It records nonblank line/span counts, font-size and line-height distributions, and the upstream `prelim_text` page view as block metadata before content is sent to editorial review.

`examples/wordpress-toc-import.php` maps Marker's upstream heading and TOC cleaners into a document-outline import path. It splits detected heading lines out of text blocks by bounding-box overlap, infers heading levels from line heights, emits a core list as a table of contents, and renders the heading blocks with Marker-style Markdown heading levels.

`examples/wordpress-pdf-outline-import.php` maps Marker's upstream `get_pdf_toc` helper plus PDF document-info metadata into a bookmark import path. It parses a native PDF catalog `/Outlines` tree and trailer `/Info` dictionary, preserves upstream-style `title`, `level`, and zero-based `page` metadata, and emits a Gutenberg list with document-info review attributes before any OCR or layout model runs.

`examples/wordpress-pdf-named-destinations-import.php` maps a native PDF named-destination outline boundary into a WordPress navigation-list import path. It resolves `/Outlines` entries through catalog `/Names` name trees, legacy `/Dests`, direct destination arrays, and `/A /GoTo` actions, then emits Gutenberg list items with page indexes and `data-marker-destination-name` attributes without loading pdftext, pypdfium, Python models, or external PDF tools.

`examples/wordpress-pdf-link-annotation-import.php` maps a native PDF link annotation boundary into a WordPress import path. It extracts page-scoped `/Annots` `/Link` URI actions, excludes non-link annotations, applies the safe URI only to overlapping supplied pdftext spans, and emits a Gutenberg paragraph with a linked anchor without loading pdftext, pypdfium, Python models, or external PDF tools.

`examples/wordpress-paginated-import.php` maps Marker's upstream Markdown block-merge and full-text assembly path into a paginated Gutenberg import. It preserves PDF page-start markers as separator blocks, then emits merged headings, paragraphs, and lists after applying block type transitions and bbox-based line continuation rules.

`examples/wordpress-reading-order-import.php` maps Marker's upstream layout ordering path into a two-column Gutenberg import. It applies model order positions by maximum bounding-box overlap, uses Marker's vertical-bucket/horizontal tie sorting, keeps page headers before body content and footers after it, then emits the body text as paragraph blocks in reading order.

`examples/wordpress-order-detection-preflight.php` maps the supplied-output half of `marker/layout/order.py::surya_order` into a WordPress import preflight. It records the bounded layout bboxes that would be sent to Surya ordering, attaches supplied ordering predictions with upstream `zip` semantics, then feeds the existing reading-order sorter before emitting Gutenberg-ready paragraphs without loading the Surya model.

`examples/wordpress-layout-annotation-import.php` maps Marker's upstream layout annotation path into a Gutenberg import preflight. It assigns Title/Text/Picture labels from supplied layout boxes, merges title fragments that came from the same layout region, applies Marker's bad-span type filtering for Picture text, and emits clean heading plus paragraph blocks without calling Surya.

`examples/wordpress-layout-detection-preflight.php` maps Marker's upstream `surya_layout` assignment boundary into a Gutenberg import preflight. It accepts supplied Surya layout predictions, records the layout batch plan and assigned labels, then emits heading and paragraph blocks through the native annotation path without loading the Surya model.

`examples/wordpress-ocr-triage.php` maps Marker's upstream OCR heuristics into a pre-render import decision. It uses text quality, detected-line coverage, all-empty document detection, and force-OCR flags to send scanned or garbled pages to OCR before Gutenberg block conversion while leaving clean extracted pages on the native text path.

`examples/wordpress-ocr-language-preflight.php` maps Marker's upstream OCR language normalization and Surya language-token boundary into multilingual import metadata. It converts human language names from a WordPress PDF metadata file to OCRmyPDF/Tesseract or Surya codes, applies Marker's default English fallback when languages are omitted, emits Surya tokenizer language-token IDs for review metadata, and rejects invalid engine-specific codes before the OCR handoff.

`examples/wordpress-ocr-detection-preflight.php` maps Marker's upstream `surya_detection` boundary into a WordPress detection preflight. It accepts supplied Surya text-line predictions, attaches them to `page.text_lines` with upstream zip semantics, checks whether the detected text lines cover the extracted PDF line boxes, and preserves those boxes as review metadata before deciding whether a page needs OCR.

`examples/wordpress-ocr-recognition-handoff.php` maps Marker's upstream `run_ocr` orchestration into a WordPress OCR handoff. It uses native page selection and `ocr_stats` accounting, accepts supplied recognized page content from a later OCR adapter, replaces only successful OCR pages, and renders the recovered text as a core paragraph without loading Surya, Tesseract, or OCRmyPDF.

`examples/wordpress-surya-ocr-recognition-import.php` maps Marker's upstream `surya_recognition` pre/post-processing boundary into a WordPress OCR import path. It records the scaled detector polygons that would be sent to Surya, skips a zero-area detection box, builds a Marker page from supplied OCR text-line output, and renders the recovered text as a core paragraph without loading Surya.

`examples/wordpress-bad-span-filter-import.php` maps Marker's upstream block span cleanup into a WordPress import path. It removes a repeated header span ID and clears OCR text from a `Picture` block via `BAD_SPAN_TYPES` before Markdown rendering, while preserving the image filename and bbox metadata needed to render a core image block.

`examples/wordpress-empty-line-filter-import.php` maps the upstream empty-line compaction inside `Block.filter_spans` and `filter_bad_span_types` into a WordPress paragraph import. It starts with extracted lines whose span lists are already empty, keeps the one live text line, and emits a single core paragraph plus review metadata showing the source/kept line counts.

`examples/wordpress-block-line-split-import.php` maps upstream `split_block_lines` into a WordPress import review path. It splits a mixed extracted PDF block into a Gutenberg heading and paragraph with recomputed Marker-style bboxes while preserving the native no-Python conversion boundary.

`examples/wordpress-conversion-finalizer-import.php` maps the late `convert_single_pdf` cleanup and assembly order into a WordPress import handoff. It accepts supplied native pages after earlier OCR/layout/table/equation boundaries, removes bad header spans, marks bold body spans, computes TOC and image metadata, normalizes bullet list markers, and emits heading/paragraph/list blocks without loading the upstream Python model stack.

`examples/wordpress-core-convert-preflight.php` maps the early `convert_single_pdf` boundary into a WordPress import preflight. It carries language, filetype, PDF TOC, selected-page count, and low-resolution render metadata into the supplied native finalizer before emitting a core paragraph block without loading the upstream Python model stack.

`examples/wordpress-finalizer-code-block-import.php` maps the integrated `identify_code_blocks` and `indent_blocks` finalizer step into a WordPress import handoff. It keeps ordinary PDF prose as paragraph content, records `block_stats.code`, and emits a Gutenberg code block from supplied PDF line geometry without loading the upstream Python model stack.

`examples/wordpress-table-score.php` maps `marker/benchmark/table.py` into a table import quality check. It compares an OCR-noisy Markdown table against the expected WordPress table content and verifies the score clears Marker's upstream table report threshold of `0.7`.

`examples/wordpress-table-box-plan.php` maps Marker's upstream `get_table_boxes` boundary into a Gutenberg table review path. It merges adjacent Table layout boxes with the locked `tabled-pdf` helper semantics, emits high-resolution crop bboxes that a table-recognition adapter would consume, duplicates supplied text-line detections for non-OCR pages, and marks OCRed pages with null text-line payloads so cell boxes can be redetected before rendering.

`examples/wordpress-table-format-import.php` maps the post-recognition half of `marker/tables/table.py::format_tables` into a Gutenberg table import path. It removes the source PDF Table block, inserts supplied Markdown table output at Marker's layout-derived insertion point, and renders the result as a core table block without shelling out to the upstream Surya/tabled recognition stack.

`examples/wordpress-merged-table-import.php` maps the merged-table half of `marker/tables/table.py::format_tables` into a Gutenberg table import path. It treats adjacent PDF Table layout fragments as one upstream recognition region, removes both source fragments, consumes one supplied Markdown table, and emits one core table block.

`examples/wordpress-table-recognition-handoff.php` maps the supplied table-recognition handoff into a Gutenberg table import path. It accepts tabled-style cell/row/column geometry from a later detector adapter, filters duplicated page text-line payloads to the active table bbox before routing cells, applies native `assign_rows_columns`-style row/column assignment, preserves row/column span metadata for multi-column headers and row-spanning labels, renders spans as table-cell attributes, cleans dot leaders and embedded newlines, formats Markdown, and then reuses Marker's `format_tables` replacement path before rendering a core table block.

`examples/wordpress-table-detector-filter.php` maps the detector fallback half of `tabled.inference.recognition.py::get_cells` into a Gutenberg table import preflight. It drops zero-width and zero-height detector boxes before OCR text assignment, reports how many cells were discarded, and renders the remaining OCR-backed cells as a core table block.

`examples/wordpress-table-multiline-row-import.php` maps `tabled.assignment.py::merge_multiline_rows` into a Gutenberg table import path. It starts with three model-detected table rows, merges a wrapped continuation row into the prior row using initially assigned column IDs despite slight x-coordinate jitter, reports the row merge as review metadata, and renders two clean core table rows.

`examples/wordpress-table-heuristic-columns.php` maps the locked `tabled.heuristics.cells` fallback into a Gutenberg table import path. It records DBSCAN-derived column separator metadata, assigns table cells without supplied model row/column boxes, and renders the resulting Markdown as a core table block.

`examples/wordpress-table-cleanup-import.php` maps `marker/tables/utils.py` into a Gutenberg table cleanup path. It sorts recognized cell blocks into row order, removes long dot leaders used as visual fillers, collapses embedded table-cell newlines, and emits the cleaned result as a core table block.

`examples/wordpress-image-import.php` maps Marker's upstream image insertion path into a Gutenberg image-block import. It uses deterministic `page_image_index.png` filenames, Figure/Picture layout-box matching, intersecting text-span removal, and Marker-style Markdown image spans before rendering a core image block. The native slice intentionally stops before raster crop rendering because upstream delegates that work to `pypdfium2` and PIL.

`examples/wordpress-markdown-image-preview.php` maps Marker's Streamlit preview image embedding into a WordPress review path. It accepts Marker-style image Markdown plus supplied PNG bytes and emits a core HTML block containing the upstream-style base64 data URI preview image.

`examples/wordpress-marker-app-preview.php` maps Marker's Streamlit PDF upload preview into a WordPress review path. It accepts uploaded PDF bytes, reports the upstream page count and one-based selected page, and records the pypdfium-style zero-based page index, render scale, RGB output, default annotation mode, and rendered preview dimensions without loading Streamlit or pypdfium.

`examples/wordpress-marker-api-upload.php` maps Marker's FastAPI upload endpoint into a WordPress import endpoint. It accepts an uploaded PDF payload, applies the upstream `application/pdf` guard, writes a temporary file for the supplied native converter, returns the upstream local response shape, and verifies the upload is cleaned up after conversion.

`examples/wordpress-pdf-image-crop.php` maps Marker's upstream PDF image rendering boundary into a media-review import path. It computes the same `dpi / 72` scale and `render_bbox_image` crop bbox that pypdfium/PIL would use, then stores that crop as Gutenberg image metadata without performing native raster rendering.

`examples/wordpress-bbox-geometry-import.php` maps Marker's upstream bbox geometry helpers into a WordPress import preflight. It merges adjacent PDF span boxes into one reviewable paragraph bbox, preserves that geometry as block metadata, and uses Marker's strict intersection semantics before emitting a related image block.

`examples/wordpress-equation-import.php` maps Marker's upstream equation replacement path into a WordPress math import. It uses Formula layout-box matching, removes the source equation text line, inserts supplied LaTeX as a Formula block after upstream validation, renders the equation as a constrained core HTML block, and emits Marker-style equation metadata for editorial review. The native slice intentionally stops before Texify model inference and equation crop rendering.

`examples/wordpress-output-artifact.php` maps Marker's upstream output writer into a WordPress import handoff. It persists converted block Markdown, `_meta.json` review metadata, and extracted image artifacts under the same per-document folder naming that `marker/output.py` uses.

`examples/wordpress-debug-bbox-export.php` maps Marker's upstream bbox debug dump into a WordPress review workflow. It writes a `_bbox.json` artifact, exposes layout labels and text-line counts for editorial tooling, and confirms heavy model fields are not stored in the review payload.

`examples/wordpress-debug-render-plan.php` maps Marker's upstream debug overlay renderer into a WordPress review workflow. It emits bbox rectangles, label backgrounds, and label text operations that admin-side preview tooling can draw over a page image without loading Python, PIL, or remote fonts.

`examples/wordpress-debug-page-image-plan.php` maps Marker's upstream debug page image writer into a WordPress review workflow. It emits the document debug folder, layout and PDF debug artifact paths, image dimensions, and overlay operation counts that admin-side preview tooling can render without loading Python, PIL, or remote fonts.

`examples/wordpress-batch-convert-import.php` maps top-level `convert.py` into a WordPress bulk import job. It plans a folder of PDFs, loads a basename-keyed metadata JSON file with per-file titles, runs native min-length preflight, writes per-document Markdown/metadata artifacts, and reports converted/skipped/error counts without loading Python model workers.

`examples/wordpress-chunk-convert-queue.php` maps top-level `chunk_convert.py` plus `chunk_convert.sh` into a WordPress sharded import queue. It emits per-device queue items with `CUDA_VISIBLE_DEVICES`, `--num_chunks`, `--chunk_idx`, `--workers`, optional metadata, and optional min-length gates while keeping execution in native PHP/planned queue metadata instead of invoking the upstream `marker` command.

`examples/wordpress-single-convert-import.php` maps top-level `convert_single.py` into a WordPress single-upload import job. It passes single-file conversion options to a supplied native converter, writes Marker's Markdown/metadata output layout, and reports the import options a WordPress review screen can display before publishing the converted blocks.

`examples/wordpress-settings-preflight.php` maps Marker's upstream settings defaults into a WordPress import preflight. It accepts `application/pdf`, rejects unsupported MIME types, applies shared-hosting overrides for image extraction and pagination, and exposes page separator and bad-span defaults without importing Torch or model dependencies.

`examples/wordpress-model-preflight.php` maps Marker's upstream model loader utilities into a WordPress import worker preflight. It emits the model setup load order, the returned `convert_single_pdf` model-list order, deferred Python loader names, checkpoint/device/dtype arguments, MPS fallback environment metadata, and CUDA cache cleanup plan without loading or shelling out to the upstream model stack.

`examples/wordpress-marker-runtime-preflight.php` maps Marker's upstream logger and Streamlit launcher runtime boundary into a WordPress import worker preflight. It emits the Streamlit command and environment overlay that upstream would use, records suppressed third-party loggers and FutureWarning filtering, and keeps the slice native by not launching Streamlit, FastAPI, Python, or model code.

`examples/wordpress-filetype-preflight.php` maps Marker's upstream filetype detection into a WordPress upload preflight. It accepts a real PDF fixture by magic bytes and rejects a ZIP-like `.pdf` payload before heavier conversion steps run.

`examples/wordpress-pdf-literal-utf16-import.php` maps a native PDF text extraction edge into a WordPress import path. It decodes UTF-16BE and UTF-16LE BOM literal strings after PDF literal escape handling and emits Gutenberg paragraphs without loading pdftext, pypdfium, Python models, or external PDF tools.

`examples/wordpress-pdf-cmap-codespace-fallback-import.php` maps a native ToUnicode CMap extraction edge into a WordPress import path. It honors `begincodespacerange` source widths before unmapped CID fallback and emits a Gutenberg paragraph without loading pdftext, pypdfium, Python models, or external PDF tools.

`examples/wordpress-pdf-resource-inheritance-import.php` maps inherited page-tree `/Resources` into a WordPress import path. It proves sibling pages can reuse `/F1` while inheriting different parent CMaps, and emits page-specific Gutenberg paragraphs without loading pdftext, pypdfium, Python models, or external PDF tools.

`examples/wordpress-pdf-object-stream-xref-import.php` maps a native PDF 1.5 object lookup edge into a WordPress import path. It expands compressed `/ObjStm` member dictionaries through an xref stream, ignores a stale unlisted compressed page, preserves an Identity-H font resource from a decoded page object, and emits only the live Gutenberg paragraphs without loading pdftext, pypdfium, Python models, or external PDF tools.

`examples/wordpress-pdf-cmap-source-width-fallback-import.php` maps a native ToUnicode CMap extraction edge into a WordPress import path. It chooses exact mapped source-key widths when a minimal CMap omits `begincodespacerange`, so adjacent one-byte mappings and two-byte mappings emit `Import Blocks` without loading pdftext, pypdfium, Python models, or external PDF tools.

`examples/wordpress-pdf-malformed-cmap-filter-import.php` maps a native malformed CMap extraction boundary into a WordPress import path. It ignores an unusable `/ToUnicode` CMap stream after its `/FlateDecode` filter fails, falls back to `/Encoding /Identity-H`, and emits a clean Gutenberg paragraph without raw NUL bytes, pdftext, pypdfium, Python models, or external PDF tools.

`examples/wordpress-pdf-image-xobject-boundary-import.php` maps a native stream fallback safety boundary into a WordPress import path. It skips `/Subtype /Image` XObject payloads before text-token parsing, so raster bytes that happen to contain PDF text syntax cannot leak into Gutenberg paragraphs.

`examples/wordpress-structure-boundary-import.php` maps Marker's supplied-document stage priority into a WordPress import path. It demonstrates a Table layout region containing nested Formula and Picture regions, then emits one Gutenberg table plus surrounding document blocks while suppressing duplicate supplied equation and image output without loading Python, pdftext, pypdfium, Surya, tabled, Texify, Torch, or external PDF tools.

`examples/wordpress-pdf-dctdecode-filter-import.php` maps a native stream-filter safety boundary into a WordPress import path. It treats `/DCTDecode` and `/DCT` PDF streams as JPEG/image-only payloads, emits only the surrounding Gutenberg paragraphs, and excludes PDF-looking text operators embedded in raster bytes without loading Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

`examples/wordpress-pdf-ccitt-fax-filter-import.php` maps a native stream-filter safety boundary into a WordPress import path. It treats `/CCITTFaxDecode` and `/CCF` PDF streams as fax/image-only payloads, emits only the surrounding Gutenberg paragraphs, and excludes fake text operators embedded in scanner bytes without loading Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

`examples/wordpress-pdf-page-labels-import.php` maps catalog `/PageLabels` number-tree metadata into a WordPress import path. It preserves labels such as `front-ii`, `Body 1`, and `App-AA` as page-break separator metadata while emitting each page's Gutenberg paragraph without loading Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

`examples/wordpress-pdf-xmp-metadata-import.php` maps catalog XMP document metadata into a WordPress import path. It prefers XMP title, authors, description, keywords, and dates over trailer `/Info` fallback values, excludes metadata streams from visible paragraph extraction, and emits document review metadata without loading Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

`examples/wordpress-pdf-cidfont-widths-import.php` maps CIDFont `/W` and `/DW` width metrics into a WordPress import path. It keeps wide adjacent CID glyphs together as `WideBlock`, inserts the expected gap for narrow text as `Thin Text`, and emits Gutenberg paragraphs without loading Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

`examples/wordpress-pdf-inline-image-boundary-import.php` maps inline image `BI ... ID ... EI` page-content data into a WordPress import path. It emits only the surrounding paragraphs and excludes text-looking image payload bytes without loading Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

`examples/wordpress-pdf-optional-content-layer-import.php` maps PDF optional content group layer visibility into a WordPress import path. It honors catalog `/OCProperties` default view state, page resource `/Properties` names, marked-content `/OC ... BDC ... EMC` spans, and Form XObject `/OC` visibility so hidden review/drafting layers do not leak into Gutenberg paragraphs without loading Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

`examples/wordpress-pdf-nested-xobject-form-import.php` maps nested Form XObject resource font scoping into a WordPress import path. It emits page text, a parent form paragraph, and a child form paragraph while proving reused `/F1` names resolve through each form's own `/Resources`, without loading Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

`examples/wordpress-pdf-cidset-glyph-widths-import.php` maps descendant CIDFont `/CIDSet` subset membership into a WordPress import path. It emits `WideBlock` as one Gutenberg paragraph from embedded-subset CID glyphs whose `/W` array is absent but whose present CIDs use the CIDFont default `/DW 1000` advance, without loading Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

`examples/wordpress-pdf-marked-content-actualtext-import.php` maps PDF marked-content `/ActualText` and `/Alt` accessibility replacements into a WordPress import path. It emits ActualText-backed paragraphs, falls back to Alt text for no-text figure content and marked spans, resolves named `/Properties` resources, and excludes original glyph/image payload noise without loading Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

`examples/wordpress-pdf-type3-charproc-widths-import.php` maps Type3 font `/CharProcs` d0/d1 width declarations into a WordPress import path. It uses declared glyph widths to keep wide Type3 glyph clusters together and insert spacing for narrow advances, emitting `WideBlock` and `Thin Text` without loading Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

## Next Task

Choose the next bounded markerPDF/PDF extraction gap on current base, favoring parser, font, object, resource, metadata, and supplied-dictionary edges that can ship with focused and full markerPDF PHP evidence.
