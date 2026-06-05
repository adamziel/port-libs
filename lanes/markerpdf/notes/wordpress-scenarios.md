# markerPDF WordPress Scenario

PDF import into clean post content and Data Liberation document conversion workflows.

## Current Native Slice

Native PDF content stream text-line extraction for literal, array, hex, UTF-16 hex, FlateDecode streams, adjacent same-line text operators, PDF line continuations, and text line movement operators.

The 2026-06-02 05:39 UTC text rendering mode slice maps PDF `Tr` visibility before WordPress import. `examples/wordpress-pdf-text-rendering-mode-import.php` demonstrates that invisible mode `3` OCR text, clipping-only mode `7` text, hidden `ActualText`, and `q`/`Q` scoped hidden text are excluded from Gutenberg paragraphs, while visible clipping modes `4`, `5`, and `6` remain importable without Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

The 2026-06-02 07:02 UTC object-stream length/filter slice resolves indirect PDF object-stream `/Length`, `/Filter`, `/N`, and `/First` entries before WordPress import. `examples/wordpress-pdf-object-stream-length-filter-import.php` demonstrates that xref-selected compressed catalog/page/font objects recover `Object stream length filter page` and `Recovered compressed resources` as Gutenberg paragraphs while excluding an unreferenced direct fallback stream and an unlisted compressed catalog member, without Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

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

The 2026-06-02 Type0 CID CMap declared-row-count slice honors `begincidchar` and `begincidrange` declared row counts before applying descendant CIDFont widths to ToUnicode surrogate-pair text. `examples/wordpress-pdf-font-tounicode-surrogate-cid-width-review-currentbase.php` demonstrates the WordPress import effect by emitting `😀ImportWP😃` and `DataFlow` as Gutenberg paragraphs while recording `surrogate_scalars_decoded=true`, `declared_cid_range_count_honored=true`, and `stale_cid_width_row_excluded=true`, without Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

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

`examples/wordpress-benchmark-runner.php` uses the native `BenchmarkRunner` port of the outer `benchmarks/overall.py` loop. It stages supplied PDF/reference pairs from the actual CI benchmark excerpts, runs supplied native Marker and comparison callbacks, writes upstream-style `marker_*.md` / `nougat_*.md` outputs, records upstream runtime options such as batch sizes and memory snapshot names without executing external tools, and verifies the upstream Marker thresholds before a WordPress import batch reaches editorial review.

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

`examples/wordpress-pdftext-dictionary-core-import.php` maps a stricter `marker/pdf/extract_text.py` dictionary-output boundary into the WordPress import path. It validates supplied pdftext page dictionaries, preserves font metadata, `char_start_idx`, `char_end_idx`, rotation, and selected-range `pdftext_options`, strips raw per-character `chars` plus non-core block/line keys from the document `keep_chars=false` handoff, then emits a Gutenberg paragraph without loading Python pdftext, pypdfium, or model workers.

`examples/wordpress-pdftext-dictionary-script-style-currentbase.php` maps current pdftext/Marker span script flags into the WordPress import path. It preserves boolean `superscript` and `subscript` metadata as review-only script flags, trims script span text before paragraph composition, rejects non-boolean adapter values, and excludes private span payloads without loading Python pdftext, pypdfium, models, or external PDF tools.

`examples/wordpress-text-length-preflight.php` maps Marker's upstream `get_length_of_text` boundary into a WordPress import queue preflight. It records the native naive text, trimmed text length, min-length threshold, and whether the PDF should enter the heavier conversion queue.

`examples/wordpress-page-inspection-preflight.php` maps Marker's upstream Page helper properties into a WordPress review preflight. It records nonblank line/span counts, font-size and line-height distributions, and the upstream `prelim_text` page view as block metadata before content is sent to editorial review.

`examples/wordpress-toc-import.php` maps Marker's upstream heading and TOC cleaners into a document-outline import path. It splits detected heading lines out of text blocks by bounding-box overlap, infers heading levels from line heights, emits a core list as a table of contents, and renders the heading blocks with Marker-style Markdown heading levels.

`examples/wordpress-pdf-outline-import.php` maps Marker's upstream `get_pdf_toc` helper plus PDF document-info metadata into a bookmark import path. It parses a native PDF catalog `/Outlines` tree and trailer `/Info` dictionary, preserves upstream-style `title`, `level`, and zero-based `page` metadata, and emits a Gutenberg list with document-info review attributes before any OCR or layout model runs.

`examples/wordpress-pdf-named-destinations-import.php` maps a native PDF named-destination outline boundary into a WordPress navigation-list import path. It resolves `/Outlines` entries through catalog `/Names` name trees, legacy `/Dests`, direct destination arrays, and `/A /GoTo` actions, then emits Gutenberg list items with page indexes and `data-marker-destination-name` attributes without loading pdftext, pypdfium, Python models, or external PDF tools.

The 2026-06-05 named-destination indirect view-operand slice extends that smoke so a standalone catalog `/Names /Dests` row can store `/FitH` and its numeric top coordinate in indirect objects. WordPress import metadata now emits the resolved fit and coordinate while preserving `/Limits`, PDFDocEncoding, and generation-mismatch exclusions without loading Python, pypdfium, pdftext, models, or external PDF tools.

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

`examples/wordpress-table-merged-cell-geometry-currentbase.php` maps current-base tabled-style merged-cell geometry into a Gutenberg table import path. It uses native assigned `row_ids` and `col_ids` to emit review metadata with occupied grid cells, anchor positions, original cell bboxes, row/column-band grid bboxes, and stable `rowspan`/`colspan` attributes without reparsing Markdown tables or running Python/model workers.

`examples/wordpress-table-ocr-merged-cell-geometry-currentbase.php` maps forced-OCR merged-cell geometry into a Gutenberg table import path. It runs supplied OCR detector cells through native table recognition, preserves OCR-applied tabled `row_ids` and `col_ids` as `table_merged_cell_geometry`, emits `colspan` and `rowspan` attributes from that metadata, and excludes stale pdftext table lines without running Python, Surya, tabled, pdftext, pypdfium, PIL, or model workers.

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

`examples/wordpress-pdf-cmap-source-width-fallback-import.php` maps a native ToUnicode CMap extraction edge into a WordPress import path. It chooses exact mapped source-key widths when a minimal CMap omits `begincodespacerange`, including CIDFonts whose only width source is `/DW`, falls back to mapped ToUnicode source keys when predefined `/Identity-H` chunks only have `/DW` default metric evidence, and preserves source-width-aware `TJ` numeric word gaps in text runs, so zero-padded source operands emit `ABCD EFGH` and metric-miss operands emit `ABCDEFGH` without loading pdftext, pypdfium, Python models, or external PDF tools.

`examples/wordpress-pdf-malformed-cmap-filter-import.php` maps a native malformed CMap extraction boundary into a WordPress import path. It ignores an unusable `/ToUnicode` CMap stream after its `/FlateDecode` filter fails, falls back to `/Encoding /Identity-H`, and emits a clean Gutenberg paragraph without raw NUL bytes, pdftext, pypdfium, Python models, or external PDF tools.

`examples/wordpress-pdf-image-xobject-boundary-import.php` maps a native stream fallback safety boundary into a WordPress import path. It skips `/Subtype /Image` XObject payloads before text-token parsing, so raster bytes that happen to contain PDF text syntax cannot leak into Gutenberg paragraphs.

`examples/wordpress-structure-boundary-import.php` maps Marker's supplied-document stage priority into a WordPress import path. It demonstrates a Table layout region containing nested Formula and Picture regions, then emits one Gutenberg table plus surrounding document blocks while suppressing duplicate supplied equation and image output without loading Python, pdftext, pypdfium, Surya, tabled, Texify, Torch, or external PDF tools.

`examples/wordpress-pdf-dctdecode-filter-import.php` maps a native stream-filter safety boundary into a WordPress import path. It treats `/DCTDecode` and `/DCT` PDF streams as JPEG/image-only payloads, emits only the surrounding Gutenberg paragraphs, and excludes PDF-looking text operators embedded in raster bytes without loading Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

`examples/wordpress-pdf-ccitt-fax-filter-import.php` maps a native stream-filter safety boundary into a WordPress import path. It treats `/CCITTFaxDecode` and `/CCF` PDF streams as fax/image-only payloads, emits only the surrounding Gutenberg paragraphs, and excludes fake text operators embedded in scanner bytes without loading Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

`examples/wordpress-pdf-page-labels-import.php` maps catalog `/PageLabels` number-tree metadata into a WordPress import path. It preserves labels such as `front-ii`, `Body 1`, and `App-AA` as page-break separator metadata while emitting each page's Gutenberg paragraph without loading Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

`examples/wordpress-pdf-xmp-metadata-import.php` maps catalog XMP document metadata into a WordPress import path. It prefers XMP title, authors, description, keywords, and dates over trailer `/Info` fallback values, excludes metadata streams from visible paragraph extraction, and emits document review metadata without loading Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

`examples/wordpress-pdf-trailer-id-fingerprint-import.php` maps PDF trailer `/ID` permanent/changing identifiers into a WordPress review metadata path. It emits a stable `document_fingerprint` derived from the permanent ID plus review-safe hex and changed-since-creation metadata while preserving visible Gutenberg paragraphs, without loading Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

`examples/wordpress-pdf-cidfont-widths-import.php` maps CIDFont `/W` and `/DW` width metrics into a WordPress import path. It keeps wide adjacent CID glyphs together as `WideBlock`, inserts the expected gap for narrow text as `Thin Text`, and emits Gutenberg paragraphs without loading Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

`examples/wordpress-pdf-inline-image-boundary-import.php` maps inline image `BI ... ID ... EI` page-content data into a WordPress import path. It emits only the surrounding paragraphs and excludes text-looking image payload bytes without loading Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

`examples/wordpress-pdf-optional-content-layer-import.php` maps PDF optional content group layer visibility into a WordPress import path. It honors catalog `/OCProperties` default view state, page resource `/Properties` names, marked-content `/OC ... BDC ... EMC` spans, and Form XObject `/OC` visibility so hidden review/drafting layers do not leak into Gutenberg paragraphs without loading Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

`examples/wordpress-pdf-nested-xobject-form-import.php` maps nested Form XObject resource font scoping into a WordPress import path. It emits page text, a parent form paragraph, and a child form paragraph while proving reused `/F1` names resolve through each form's own `/Resources`, without loading Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

`examples/wordpress-pdf-cidset-glyph-widths-import.php` maps descendant CIDFont `/CIDSet` subset membership into a WordPress import path. It emits `WideBlock` as one Gutenberg paragraph from embedded-subset CID glyphs whose `/W` array is absent but whose present CIDs use the CIDFont default `/DW 1000` advance, without loading Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

`examples/wordpress-pdf-marked-content-actualtext-import.php` maps PDF marked-content `/ActualText` and `/Alt` accessibility replacements into a WordPress import path. It emits ActualText-backed paragraphs, falls back to Alt text for no-text figure content and marked spans, resolves named `/Properties` resources, and excludes original glyph/image payload noise without loading Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

`examples/wordpress-pdf-type3-charproc-widths-import.php` maps Type3 font `/CharProcs` d0/d1 width declarations into a WordPress import path. It uses declared glyph widths to keep wide Type3 glyph clusters together and insert spacing for narrow advances, emitting `WideBlock` and `Thin Text` without loading Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

`examples/wordpress-pdf-trailer-id-fingerprint-import.php` maps trailer `/ID` document fingerprint metadata into a WordPress review path. It emits stable and changing identifier metadata for editorial/import dedupe workflows while keeping visible PDF text extraction native and review-safe.

`examples/wordpress-pdf-catalog-lang-viewer-preferences-import.php` maps catalog `/Lang` and `/ViewerPreferences` metadata into a WordPress import review path. It exposes document language plus viewer hints such as display title, print scaling, and reading direction without executing Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

`examples/wordpress-pdf-openaction-safety-review.php` maps catalog OpenAction metadata into a non-executing WordPress safety-review path. It classifies local `GoTo`, remote `GoToR`, `URI`, and `Launch` actions for review and keeps them out of same-document TOC rows before import.

`examples/wordpress-pdf-asciihex-runlength-filter-import.php` maps a length-bounded native PDF stream-filter edge into a WordPress import path. It decodes an `/ASCIIHexDecode` to `/RunLengthDecode` filter chain after slicing the stream by direct or indirect `/Length`, then emits `ASCIIHex RunLength Import` and `Block Ready Content` without loading Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

`examples/wordpress-pdf-destination-view-import.php` maps catalog and outline destination page-view metadata into a WordPress navigation review path. It exposes `/PageMode`, `/PageLayout`, `/OpenAction`, and outline `/Fit`/`/FitH`/`/XYZ` view fields, including indirect view-mode and coordinate operands, while preserving the existing basic TOC rows.

`examples/wordpress-pdf-xfa-form-packet-import.php` maps catalog `/AcroForm /XFA` packet arrays into a review-only WordPress form path. It reports packet names, field hints, and data-node names without merging dynamic XFA XML into static AcroForm fields or executing external tooling.

`examples/wordpress-pdf-xfa-xdp-stream-import.php` maps a single-stream UTF-16 XDP `/AcroForm /XFA` package into the same review-only WordPress form path. It reports the decoded XML encoding, `xdp:xdp` root, top-level XDP packet names, field names, and data-node names without rendering dynamic XFA values, executing XFA JavaScript, or loading external PDF/model tooling.

`examples/wordpress-pdf-embedded-files-import.php` maps catalog `/Names /EmbeddedFiles` attachment metadata into a WordPress import path. It emits a core file block and review metadata for the embedded file while proving embedded-file payload streams do not leak into visible paragraph extraction.

`examples/wordpress-pdf-richmedia-annotation-review.php` maps `/Screen` and `/RichMedia` annotations into WordPress review metadata. It records rendition/media files and actions as non-executing review rows while keeping media appearance streams out of imported paragraph text.

`examples/wordpress-pdf-highlight-review-import.php` maps text-markup annotation `/QuadPoints` into an editorial review path. It applies review metadata to overlapping supplied pdftext spans and emits `<mark>` markup for Gutenberg paragraph review without executing external PDF tooling.

`examples/wordpress-pdf-highlight-review-import.php` now also maps annotation border and popup metadata into the same editorial review path. It preserves `/Border` dash arrays, `/BS` border-style dictionaries, and linked `/Popup` review contents as `data-markerpdf-*` attributes on highlighted Gutenberg text without rendering annotations, executing actions, or loading Python/pdftext/pypdfium.

`examples/wordpress-pdf-acroform-submit-reset-actions.php` maps AcroForm `/SubmitForm` and `/ResetForm` actions into a review-only WordPress form path. It reports submit targets, reset modes, and field lists without submitting data, resetting state, or executing PDF actions.

`examples/wordpress-pdf-associated-files-import.php` maps catalog `/AF` associated Filespec arrays into a WordPress import review path. It emits source and alternative file rows with `/AFRelationship`, description, MIME, size, and declared-size metadata while keeping payload bytes out of visible paragraph extraction.

`examples/wordpress-pdf-associated-pieceinfo-indirect-boundary.php` maps catalog `/AF` associated Filespec `/PieceInfo` entries whose application dictionary is indirect. It emits attachment review metadata plus private-stream checksum state while proving attachment payload bytes and indirect PieceInfo private-stream bytes stay out of visible Gutenberg paragraphs without loading Python, pdftext, pypdfium, Poppler, Ghostscript, models, or external PDF tools.

`examples/wordpress-pdf-javascript-action-safety.php` maps document name-tree, catalog, page, and annotation JavaScript actions into a non-executing safety-review path. It reports source, event, script preview, hashes, and object references while keeping JavaScript out of text extraction and Markdown links.

`examples/wordpress-pdf-base14-font-metrics-import.php` maps Base14 and explicit simple-font width metrics into a WordPress text extraction path. It uses native width evidence for same-line gap decisions, preserving expected output such as `Ill Word`, `WWWImport`, `iii Word`, and `CourierText`.

`examples/wordpress-pdf-xref-stream-index-width-import.php` maps PDF 1.5 xref stream `/Index` ranges and zero-width `/W` defaults into a WordPress import path. It extracts only the current page text, excludes stale rebuilt page content, and avoids NUL-byte leakage without external PDF tooling.

`examples/wordpress-pdf-xref-prev-stream-generation-repair-import.php` maps xref-stream `/Prev` generation repair into a WordPress paragraph import path. It trusts exact xref byte offsets before stale previous-stream generation rows, preserving shared previous font resources while extracting only the current generation page text and excluding stale previous-generation paragraphs without loading Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

`examples/wordpress-pdf-linearized-hint-table-import.php` maps linearized PDF `/H` hint-table byte ranges into a damaged-upload fallback import path. It keeps hint-table stream bytes out of native object maps and raw stream fallback extraction, emitting only the real fallback Gutenberg paragraphs without Python, pdftext, pypdfium, Poppler, Ghostscript, models, or external PDF tools.

`examples/wordpress-pdf-cmap-usecmap-cycle-codespace-guard-import.php` maps cyclic ToUnicode `usecmap` inheritance and declared codespace counts into a WordPress text extraction path. It emits `Import Blocks! OK` while proving mutual CMap references do not loop and extra codespace rows do not corrupt source-width fallback.

`examples/wordpress-pdf-annotation-border-color-popup-import.php` maps page annotation presentation metadata into a WordPress review path. It reports annotation subtype, colors, opacity, border style, and popup state without executing PDF actions, Python, models, or external PDF tools.

`examples/wordpress-pdf-thread-bead-reading-order-import.php` maps catalog `/Threads` article bead reading order into a WordPress paragraph import path. It follows bead rectangles and linked-list order so multi-column article text emits as `one, two, three, four` instead of raw content stream order.

`examples/wordpress-pdf-object-generation-free-entry-import.php` maps object-generation free-entry reuse into a WordPress paragraph import path. It keeps the current direct page text while excluding a stale object-stream member whose object number is reserved by a current xref free entry.

`examples/wordpress-pdf-object-stream-nested-token-boundary-import.php` maps unfiltered PDF 1.5 object-stream parser boundaries into a WordPress paragraph import path. It recovers the catalog, page tree, and font resource from xref-selected compressed members whose nested dictionaries and literal strings contain fake `obj`, `endobj`, and `stream` tokens, while excluding unrelated fallback streams and unlisted compressed catalogs without loading Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

`examples/wordpress-pdf-cmap-comment-import.php` maps ToUnicode CMap PDF/PostScript line-comment handling into a WordPress paragraph import path. It strips `%` comments before CMapName/usecmap/codespace/bfchar/bfrange parsing so commented fake glyph mappings such as `Noise` and `XY` do not override the real `ACleanDE` paragraph, without loading Python, pdftext, pypdfium, Poppler, Ghostscript, models, or external PDF tools.

`examples/wordpress-pdf-indirect-nametree-destinations-import.php` maps indirect `/Names /Dests` string keys and destination dictionaries into a WordPress navigation review path. It emits Gutenberg list items with resolved `data-marker-destination-name` attributes, `/FitBH` and `/FitR` view metadata, and non-executing catalog OpenAction review metadata without loading Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

`examples/wordpress-pdf-annotation-geometry-import.php` maps page annotation geometry for line, ink, polygon, callout, square, and circle annotations into review-only WordPress list rows. It exposes derived bounding boxes and shape metadata while confirming no PDF actions are executed and annotation appearances are not rendered.

`examples/wordpress-pdf-icc-softmask-image-review.php` maps ICCBased image color-space and soft-mask metadata into a WordPress media-review path. It exposes profile component count, `/Alternate`, `/Range`, direct/indirect `/SMask`, `/Matte`, matte-unblend, and RGB preview intent without loading Python, pypdfium, PIL, Poppler, Ghostscript, or external PDF tools.

`examples/wordpress-pdf-image-xobject-smask-filter-currentbase.php` maps an image XObject `/SMask` stream filter chain into a WordPress media-review path. It resolves the current soft-mask object, decodes supported `ASCIIHexDecode` and `FlateDecode` filters before RGB preview planning, records decoded mask hash/sample bytes, and keeps stale mask objects plus pypdfium/PIL/model execution out of the import path.

`examples/wordpress-pdf-encryption-permission-metadata-import.php` maps Standard PDF encryption permission metadata into a review-only WordPress import path. It reports crypt-filter settings, permission allow/deny labels, print quality, and hashed `/Perms`, blocks encrypted text extraction, and suppresses raw owner/user keys without attempting password validation or decryption.

`examples/wordpress-pdf-embedded-files-import.php` now also maps embedded-file `/Params /CheckSum` into attachment review metadata. It exposes declared checksum, computed MD5, and match state so WordPress import workflows can flag stale embedded payloads without dropping the file or executing external PDF tooling.

`examples/wordpress-pdf-xmp-metadata-import.php` now also maps XMP and trailer `/Info` timezone-bearing dates into WordPress review metadata. It preserves raw source date strings and adds UTC-normalized fields only when the PDF supplies an explicit timezone, avoiding false precision for timezone-free metadata.

`examples/wordpress-pdf-stream-filter-error-boundary-import.php` maps declared stream-filter failure boundaries into a WordPress import path. It keeps visible unfiltered page content while excluding unsupported `/Crypt`, corrupt `/FlateDecode`, stacked unknown, and missing indirect filter streams so raw filtered bytes cannot leak PDF-looking text operators into Gutenberg paragraphs.

`examples/wordpress-pdf-pdfdocencoding-metadata-import.php` maps PDFDocEncoding trailer `/Info` text strings into a WordPress document metadata review path. It decodes high-bit PDF text-string bytes such as bullet, ligatures, smart quotes, minus, per-mille, `Lslash`/`lslash`, Euro, and Latin-1 letters before emitting title, authors, description, keywords, creator, and producer metadata without loading Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

`examples/wordpress-pdf-filter-name-array-indirect-import.php` maps a PDF stream `/Filter` array whose individual filter names are indirect objects into a WordPress import path. It resolves `2 0 R` to `/ASCIIHexDecode`, resolves `3 0 R` to `/FlateDecode`, ignores a `null` filter-array entry, and emits `Name Array Indirect Filter` plus `Block Ready Import` as Gutenberg paragraphs without loading Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

`examples/wordpress-pdf-image-decode-stencil-preview.php` maps base image `/Decode` arrays and `/ImageMask` stencil decode arrays into a WordPress media-review path. It records decoded RGB component values, inverted stencil opacity, and RGB preview intent without loading Python, pypdfium, PIL, Poppler, Ghostscript, or external PDF tools.

`examples/wordpress-pdf-image-colorkey-mask-decode-currentbase.php` maps image XObject ColorKey `/Mask` arrays plus base image `/Decode` arrays into a WordPress media-review path. It records raw-sample transparency decisions before Decode-adjusted RGB preview components, proving transparent and opaque samples are reviewable without loading Python, pypdfium, PIL, Poppler, Ghostscript, or external PDF tools.

`examples/wordpress-pdf-annotation-appearance-import.php` now also maps annotation appearance Form XObject resource boundaries into a WordPress import path. It imports only current page-referenced `/AP /N` appearance text, clips text outside the appearance `/BBox`, preserves nested appearance-local `/Resources /Font` scopes, and excludes stale/off/unreferenced appearance noise without loading Python, pdftext, pypdfium, Poppler, Ghostscript, rendering annotations, or executing PDF actions.

`examples/wordpress-pdf-annotation-popup-appearance-action-boundary.php` maps generic page annotation popup, selected appearance, destination, and action review metadata into a WordPress-safe review path. It nests current popup rows, preserves selected `/AP /N` state metadata, reports `/A`, `/AA`, `/Dest`, and `/Next` chains without executing actions, and excludes popup text, stale/off appearances, detached annotations, and action scripts from visible Gutenberg paragraphs.

`examples/wordpress-pdf-page-annotation-thread-currentbase.php` maps page annotation reply threads into a WordPress review path. It records current-page `/IRT` reply links, `/RT` reply/group labels, `/State` and `/StateModel` review state, and detached reply targets while keeping annotation contents, popup contents, and detached target payloads out of visible Gutenberg paragraphs without executing PDF actions, rendering annotations, loading Python models, or using external PDF tools.

`examples/wordpress-pdf-type0-encoding-cmap-boundary-import.php` maps Type0 font `/Encoding` CMap code-space and CID mapping boundaries into a WordPress paragraph import path when `/ToUnicode` is absent. It preserves mixed one-byte and two-byte source codes for descendant CIDFont width grouping, emitting `WideBlock` and `Thin Text` without NUL bytes, false spaces, Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

`examples/wordpress-pdf-parser-stream-filter-object-boundary.php` maps the fallback PDF stream parser boundary into a WordPress import path. It decodes only current xref-selected direct stream objects and ignores fake nested stream tokens inside a current stream payload, emitting `Current filtered object boundary` and `Current base fallback` while excluding stale filtered bytes and inline-image fake stream text without Python, pdftext, pypdfium, Poppler, Ghostscript, or external PDF tools.

`examples/wordpress-pdf-xref-generation-repair-boundary.php` maps hybrid xref generation repair into a WordPress paragraph import path. It keeps the current xref table direct generation row authoritative over a companion `/XRefStm` compressed stale member, emitting `Current direct generation page` and `Hybrid table boundary kept` while excluding previous-generation compressed text without Python, pdftext, pypdfium, Poppler, Ghostscript, models, or external PDF tools.

`examples/wordpress-pdf-xref-object-stream-trailer-boundary-import.php` maps current trailer/xref object-stream repair boundaries into a WordPress paragraph import path. It keeps the latest trailer-selected direct page tree authoritative while excluding unselected `/ObjStm` members that try to overwrite page-tree objects, emitting `Current trailer boundary page` and `Direct page tree repaired` without Python, pdftext, pypdfium, Poppler, Ghostscript, models, or external PDF tools.

`examples/wordpress-pdf-signature-reference-transform-review.php` maps PDF signature `/Reference` transform dictionaries into a WordPress security-review path. It exposes FieldMDP and UR3 usage-rights metadata, field locks, digest presence, and allowed right categories while suppressing digest/signature bytes and without executing signature validation, signing, rights enforcement, Python, models, or external PDF tools.

`examples/wordpress-pdf-signature-dss-currentbase.php` maps catalog `/DSS` long-term-validation material into a WordPress security-review path. It exposes Cert/OCSP/CRL/VRI/timestamp-token counts, decoded validation hashes, and indirect `/Filter` operand metadata while importing visible signed text natively, suppressing raw validation/signature bytes, and avoiding signature validation, revocation checks, trust-chain validation, signing, Python, models, or external PDF tools.

`examples/wordpress-pdf-cidfont-indirect-w2-vertical-import.php` maps indirect CIDFont `/DW2` and `/W2` vertical metrics into a WordPress paragraph import path. It resolves the metric arrays before writing-mode 1 text advance grouping, emitting `VertImport` and `DataFlow` without inserting false spaces and without loading Python, pdftext, pypdfium, Poppler, Ghostscript, models, or external PDF tools.

`examples/wordpress-pdf-type0-vertical-usecmap-cidset-currentbase.php` maps a Type0 `/Encoding` CMap stream that inherits predefined `/Identity-V` through `/UseCMap` into a WordPress paragraph import path. It preserves two-byte source decoding and CIDSet vertical default displacement boundaries, emitting `A Word` and `VertImport` without NUL bytes, false spaces, Python, pdftext, pypdfium, Poppler, Ghostscript, models, or external PDF tools.

`examples/wordpress-pdf-outline-destination-action-transition-currentbase.php` maps outline `/Dest` entries that resolve to GoTo action dictionaries into WordPress navigation review rows. It preserves local destination labels, target page transition metadata, and page-open action review rows while surfacing chained URI/JavaScript followups as non-executing metadata and keeping action operands out of visible Gutenberg paragraphs.

`examples/wordpress-pdf-object-stream-nested-filter-currentbase.php` maps malformed object-stream `/Filter` arrays into a WordPress-safe fallback path. It rejects nested filter arrays on an xref-selected `/ObjStm`, excludes xref-stream bytes from fallback pages, and emits only the direct safe paragraph `Direct fallback survives nested filter review` without Python, pdftext, pypdfium, Poppler, Ghostscript, models, or external PDF tools.

`examples/wordpress-pdf-page-structparents-parenttree-reading-order-currentbase.php` maps page `/StructParents` and StructTreeRoot `/ParentTree` arrays into a WordPress tagged-content import path. It emits page-local H2 and paragraph blocks in tagged MCID order, resolves `/RoleMap`, and excludes unlisted `/Artifact` MCIDs without Python, pdftext, pypdfium/PDFium execution, Poppler, Ghostscript, models, or external PDF tools.

`examples/wordpress-pdf-page-structparents-pieceinfo-thread-currentbase.php` maps page `/StructParents` ParentTree MCID rows into a WordPress import path while preserving page `/PieceInfo`, catalog `/Threads`, and StructElem-associated FileSpec metadata as review-only comments. It emits visible Gutenberg paragraphs in ParentTree order and proves attachment payloads, article thread dictionaries, PieceInfo private values, and accessibility review strings stay out of body text without Python, pdftext, pypdfium/PDFium execution, Poppler, Ghostscript, models, or external PDF tools.

`examples/wordpress-pdf-xref-prev-object-stream-generation-currentbase.php` maps incremental `/Prev` object-stream carrier generation reuse into a WordPress paragraph import path. It skips stale previous type-2 rows when their carrier object number was itself only a compressed decoy, records review-only owner policies for skipped and preserved previous type-2 rows, and keeps the current direct page text while excluding stale previous and replacement-generation object-stream member text without Python, pdftext, pypdfium, Poppler, Ghostscript, models, or external PDF tools.

`examples/wordpress-pdf-xref-object-stream-generation-prev-currentbase.php` maps incremental `/Prev` object-stream carrier generation replacement into a WordPress paragraph import path. It skips a stale previous type-2 page member when the previous xref chain selected carrier `6 0` but the current xref stream replaces that carrier with generation `6 1`, emitting only `Current carrier generation page` and `Previous member generation skipped` without Python, pdftext, pypdfium, Poppler, Ghostscript, models, or external PDF tools.

`examples/wordpress-pdf-xref-object-stream-hybrid-generation-owner-currentbase.php` maps hybrid xref object-stream carrier generation ownership into a WordPress paragraph import path. It expands the companion `/XRefStm` type-2 page member from the current hybrid table-selected `6 1` `/ObjStm` carrier, records the carrier generation/owner policy as review metadata, and emits only current Gutenberg paragraphs while excluding stale carrier-generation-zero payload text without Python, pdftext, pypdfium, Poppler, Ghostscript, models, or external PDF tools.

`examples/wordpress-table-ocr-rowspan-caption-accessibility-currentbase.php` maps a forced-OCR rowspanned table with surrounding section and caption blocks into WordPress table HTML. It emits stable `id`, `aria-describedby`, `aria-labelledby`, `<caption id>`, row/column header IDs, and body-cell `headers` attributes while proving stale pdftext table lines stay excluded without Python, tabled-pdf execution, models, OCR engines, or external PDF tools.

`examples/wordpress-pdf-metadata-trailer-id-lang-viewer-preference-currentbase.php` maps a current xref-stream trailer `/ID` plus top-level catalog `/Lang`, `/PageLayout`, `/PageMode`, and indirect `/ViewerPreferences` into WordPress review metadata. It proves nested catalog review dictionaries and stale appended objects cannot override the document language, viewer preferences, fingerprint source, or visible Gutenberg paragraph without Python, pdftext, pypdfium, Poppler, Ghostscript, models, or external PDF tools.

`examples/wordpress-pdf-parser-name-array-comment-escape-currentbase.php` maps escaped xref-stream `/W`, `/Index`, and `/Size` name keys plus comment-heavy numeric arrays into a WordPress paragraph import path. It keeps the current startxref-selected page text and excludes stale later direct catalog/page objects without Python, pdftext, pypdfium, Poppler, Ghostscript, models, or external PDF tools.

`examples/wordpress-pdf-malformed-cmap-filter-boundary-currentbase.php` maps malformed ToUnicode CMap `/Filter` and `/DecodeParms` operands into a WordPress-safe text import path. It rejects direct dictionary/literal operands, selected indirect malformed operands, stale valid filter generations, malformed DecodeParms, trailing/unapplied malformed DecodeParms array entries, and stale-generation filter references whose current xref-selected owner is a dictionary, then falls back to `/Encoding /Identity-H` and emits only safe Gutenberg paragraphs while excluding decoded fake CMap text and filter helper strings without Python, pdftext, pypdfium, Poppler, Ghostscript, models, or external PDF tools.

`examples/wordpress-pdf-image-xobject-boundary-currentbase.php` maps inherited page `/Resources /XObject` image streams into a WordPress review path. It records decoded resource names, `Do` invocation counts, dimensions, filters, safe decoded hashes, and RGB-preview handoff metadata while proving image XObject payload bytes stay out of visible Gutenberg paragraphs without Python, pdftext, pypdfium/PDFium execution, PIL, Poppler, Ghostscript, models, or external PDF tools.

`examples/wordpress-pdf-dctdecode-filter-import.php` now also maps DCTDecode image `/DecodeParms /ColorTransform` review metadata into the WordPress media-review path. It exposes public renderer and page Image XObject color-transform values while keeping JPEG payload bytes out of visible Gutenberg paragraphs and without invoking Python, pdftext, pypdfium/PDFium, PIL, Poppler, Ghostscript, models, or external PDF tools.

`examples/wordpress-pdf-dctdecode-post-filter-boundary-currentbase.php` maps DCTDecode image streams whose declared filter stack continues after preview-only JPEG data into a WordPress media-review path. It exposes `dctdecode_filter_boundary` metadata for post-DCT native and preview-only filters, emits only clean Gutenberg paragraphs, and excludes JPEG payload bytes without invoking Python, pdftext, pypdfium/PDFium, PIL, Poppler, Ghostscript, models, or external PDF tools.

`examples/wordpress-pdf-dctdecode-stream-terminator-boundary-currentbase.php` maps DCTDecode JPEG SOI/EOI stream ownership plus explicit prefix-filter EOD boundaries into a fallback WordPress text-import path. It rejects stale `/Length` fake `endstream/endobj` markers and embedded fake objects inside JPEG payload bytes, emits only the surrounding Gutenberg paragraphs, and avoids Python, pdftext, pypdfium/PDFium, PIL, Poppler, Ghostscript, models, and external PDF tools.

`examples/wordpress-pdf-dctdecode-comment-terminator-boundary-currentbase.php` maps DCTDecode streams whose recovered true JPEG EOI is followed by a PDF comment before the real `endstream` token into a WordPress-safe import path. It rejects stale `/Length` false-EOI fake stream/object boundaries, emits only surrounding Gutenberg paragraphs, preserves review-only image metadata, and avoids Python, pdftext, pypdfium/PDFium, PIL, Poppler, Ghostscript, models, and external PDF tools.

`examples/wordpress-pdf-dctdecode-runlength-prefix-boundary-currentbase.php` maps `/RunLengthDecode` prefix streams before preview-only `/DCTDecode` image data into a WordPress-safe import path. It rejects an early RunLength EOD plus fake `endstream/endobj` decoy before an incomplete JPEG, recovers the complete review-only image payload for metadata, emits only surrounding Gutenberg paragraphs, and avoids Python, pdftext, pypdfium/PDFium, PIL, Poppler, Ghostscript, models, and external PDF tools.

`examples/wordpress-pdf-dctdecode-indirect-filter-boundary-currentbase.php` maps DCTDecode image streams whose `/Filter` helper object appears after the owner stream into a WordPress-safe import path. It uses raw JPEG SOI/EOI framing during the preliminary owner scan, later resolves the indirect `/DCTDecode` review metadata, emits only surrounding Gutenberg paragraphs, and excludes fake JPEG payload objects without Python, pdftext, pypdfium/PDFium, PIL, Poppler, Ghostscript, models, or external PDF tools.

`examples/wordpress-pdf-type3-charprocs-generation-boundary-currentbase.php` maps same-number Type3 `/CharProcs` stream references with different object generations into a WordPress paragraph import path. It preserves `3 0 R` wide glyph d0 metrics and `3 1 R` thin glyph d1 metrics independently, emitting `WideBlock` and `Thin Text` while excluding CharProc payload text without Python, pdftext, pypdfium/PDFium execution, Poppler, Ghostscript, models, or external PDF tools.

`examples/wordpress-pdf-xref-object-stream-skipped-header-index-currentbase.php` maps PDF 1.5 xref-stream type-2 member indexes through object streams that contain a skipped zero object-number header row. It keeps explicit archive index `1` aligned to the original `/ObjStm` header row, emits the current direct and compressed page paragraphs, records `selection_policy=explicit_member_index`, and excludes skipped header decoy text without Python, pdftext, pypdfium/PDFium execution, Poppler, Ghostscript, models, or external PDF tools.

`examples/wordpress-pdftext-dictionary-source-page-currentbase.php` maps pdftext dictionary page-level source geometry into WordPress review metadata. It preserves source `page`, `bbox`, `width`, `height`, and `rotation` separately from the Marker rendered page bbox, emits a Gutenberg paragraph unchanged, and avoids Python pdftext, models, pypdfium, Poppler, Ghostscript, or external PDF tools.

`examples/wordpress-pdftext-dictionary-source-dimension-boundary-currentbase.php` maps supplied pdftext dictionary page dimensions into WordPress review metadata with a fail-closed boundary. It preserves positive source `width` and `height`, rejects zero-width and negative-height dictionaries before metadata output, emits the valid Gutenberg paragraph, and avoids Python pdftext, models, pypdfium, Poppler, Ghostscript, or external PDF tools.

`examples/wordpress-pdf-ccitt-fax-decodeparms-failclosed-currentbase.php` maps malformed CCITTFaxDecode/CCF `/DecodeParms` into a WordPress media-review path. It exposes invalid fax fields for review while keeping CCITT raster payload bytes out of Gutenberg paragraphs and without loading Python, pdftext, pypdfium/PDFium, PIL, Poppler, Ghostscript, OCR, models, or external PDF tools.

`examples/wordpress-pdf-type3-charprocs-fontmatrix-boundary-currentbase.php` maps Type3 `/CharProcs` `d0`/`d1` widths through a non-default `/FontMatrix` into a WordPress paragraph import path. It normalizes glyph-space width `500` with matrix scale `0.002` into the expected text advance, emitting `WideBlock` and `Thin Text` while excluding CharProc payload text without Python, pdftext, pypdfium/PDFium execution, Poppler, Ghostscript, models, or external PDF tools.

`examples/wordpress-pdf-type3-charprocs-encoding-generation-boundary-currentbase.php` maps comment-split, exact-generation Type3 `/Encoding` dictionaries into a WordPress paragraph import path before CharProc glyph-name mapping. It selects generation `21 0` over a stale `21 1` encoding dictionary, emits `WideBlock` and `Thin Text`, preserves Type3 `d0`/`d1` spacing, and excludes CharProc payload text without Python, pdftext, pypdfium/PDFium execution, Poppler, Ghostscript, models, or external PDF tools.

`examples/wordpress-pdftext-dictionary-layout-order-string-marker-currentbase.php` maps pdftext dictionary layout/order artifact alignment when adapter page identity arrives as whitespace-padded numeric strings. It normalizes the string markers before selected-page matching, orders the selected WordPress paragraph columns with the selected artifact, excludes cover/appendix artifacts, and avoids Python, pdftext, pypdfium/PDFium execution, Surya/Torch models, OCR, and external PDF tools.

`examples/wordpress-pdftext-dictionary-layout-order-signed-marker-currentbase.php` maps pdftext dictionary layout/order artifact alignment when adapter page identity arrives as plus-signed numeric strings such as `+951` or `+951.0`. It normalizes those markers before selected-page matching, orders the selected WordPress paragraph columns with the selected artifact, excludes cover/appendix artifacts, and avoids Python, pdftext, pypdfium/PDFium execution, Surya/Torch models, OCR, and external PDF tools.

`examples/wordpress-pdftext-dictionary-char-core-boundary-currentbase.php` maps supplied pdftext `keep_chars` character dictionaries into WordPress review metadata. It preserves upstream-shaped `char`, `bbox`, `rotation`, `font`, and `char_idx` rows while excluding legacy `c` aliases plus raw span/character font payload keys before Marker `char_blocks`, and emits a Gutenberg paragraph without Python, pdftext, pypdfium/PDFium execution, models, OCR, or external PDF tools.

`examples/wordpress-pdf-type3-charprocs-resource-comment-currentbase.php` maps PDF comments inside Type3 CharProc glyph-private `/Resources /XObject` references into a WordPress-safe fallback path. It treats comments as whitespace before resource traversal, emits only `Visible fallback content`, and excludes direct CharProc payloads plus top-level, stream-local, and nested glyph-private Form XObject text without Python, pdftext, pypdfium/PDFium execution, Poppler, Ghostscript, models, OCR, or external PDF tools.

`examples/wordpress-pdf-xref-object-stream-attachment-header-comment-currentbase.php` maps xref-stream type-2 compressed FileSpec attachment preflight through an `/ObjStm` header that contains commented numeric decoys. It keeps the explicit member index aligned to the current compressed FileSpec, excludes the stale direct FileSpec and comment-decoy member, omits embedded payload bytes, and avoids Python, pdftext, pypdfium/PDFium execution, Poppler, Ghostscript, models, OCR, and external PDF tools.

`examples/wordpress-pdftext-dictionary-layout-order-zero-area-currentbase.php` maps supplied pdftext dictionary layout/order handoff when zero-width or zero-height order boxes are returned by an adapter/model boundary. It preserves selected-page source order, excludes unusable order geometry and raw payload markers, keeps the cover page out of Gutenberg paragraphs, and avoids Python, pdftext, pypdfium/PDFium execution, Surya/Torch models, OCR, and external PDF tools.

`examples/wordpress-pdftext-dictionary-layout-order-payload-wrapper-list-currentbase.php` maps supplied pdftext dictionary layout/order handoff when adapter-typed `layout_result` and `order_result` payload wrappers contain multiple dictionaries. It preserves selected-page source order, counts the ambiguous artifacts as reviewed but unassigned, excludes raw payload dictionaries from WordPress output, and avoids Python, pdftext, pypdfium/PDFium execution, Surya/Torch models, OCR, and external PDF tools.

`examples/wordpress-pdf-image-xobject-imagemask-pattern-currentbase.php` maps ImageMask XObject stencil paint colors through named nonstroking `/ColorSpace` and `/Pattern` resources into WordPress media-review metadata. It resolves the named RGB color space, verifies the tiling pattern resource, emits only surrounding Gutenberg paragraphs, and excludes stencil payload bytes without Python, pdftext, pypdfium/PDFium execution, PIL, Poppler, Ghostscript, models, OCR, or external PDF tools.

`examples/wordpress-pdf-image-xobject-tiling-pattern-currentbase.php` maps Image XObjects invoked from painted PatternType 1 tiling-pattern streams into WordPress media-review metadata. It records the pattern resource/object, paint bbox, pattern matrix, image placement bbox, safe decoded hashes for painted and unpainted pattern resources, emits only surrounding Gutenberg paragraphs, and excludes pattern image payload bytes without Python, pdftext, pypdfium/PDFium execution, PIL, Poppler, Ghostscript, models, OCR, or external PDF tools.

`examples/wordpress-pdf-acroform-fields-direct-dictionary-currentbase.php` maps direct AcroForm field dictionaries in catalog `/Fields` and field `/Kids` arrays into WordPress form-review metadata. It materializes top-level direct root and child fields, preserves indirect widget page annotation indexes, excludes literal, nested-array, nested-dictionary, comment, and detached field-like decoys, and keeps form values out of visible Gutenberg paragraphs without executing form actions, JavaScript, Python models, OCR, or external PDF tools.

`examples/wordpress-pdf-acroform-fields-direct-page-widget-currentbase.php` maps direct top-level page `/Annots` Widget dictionaries into WordPress form-review metadata. It materializes direct inline Widget fields and direct Widget `/Parent` fields that omit `/Kids`, preserves matching page `/P` ownership, excludes wrong-page, non-Widget, and explicit empty-/Kids decoys, and keeps form values out of visible Gutenberg paragraphs without executing form actions, JavaScript, Python models, OCR, or external PDF tools.

## Next Task

Choose the next bounded markerPDF/PDF extraction gap on current base, favoring AcroForm value dictionaries, page/action metadata, annotation geometry, object-stream/xref edges, Base14/font flag metrics, parser, object, resource, metadata, and supplied-dictionary edges that can ship with focused and full markerPDF PHP evidence.
