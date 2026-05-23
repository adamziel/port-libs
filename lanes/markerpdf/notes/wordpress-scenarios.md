# markerPDF WordPress Scenario

PDF import into clean post content and Data Liberation document conversion workflows.

## Current Native Slice

Native PDF content stream text-line extraction for literal, array, hex, UTF-16 hex, FlateDecode streams, adjacent same-line text operators, PDF line continuations, and text line movement operators.

The lane now also maps the upstream `pdftext` dictionary boundary from `marker/pdf/extract_text.py::pdftext_format_to_blocks`. `PdfTextBlockConverter` converts supplied pdftext page dictionaries into Marker's native Page/Block/Line/Span arrays, including font flag suffixes, span IDs, rotation-aware page bboxes, and pdftext hyphen/newline cleanup before later layout annotation.

The lane now also maps the supplied-data boundary of `marker/pdf/extract_text.py::get_text_blocks`. `PdfTextDocumentExtractor` applies upstream `start_page`/`max_pages` page-range semantics to supplied pdftext dictionaries, restarts span IDs relative to the selected range, preserves original PDF page numbers, and carries PDF TOC metadata for partial WordPress imports.

The lane now also maps `marker/schema/page.py` page helper properties. `PageInspector` flattens page lines, filters nonblank lines and spans, extracts font-size and line-height distributions, and exposes Marker's `prelim_text` view for WordPress review metadata before editorial handoff.

The lane now also maps `marker/pdf/utils.py::find_filetype`. `FiletypeDetector` uses magic-MIME style detection to accept PDF uploads, reject extension-spoofed non-PDF payloads as `other`, and preserve the upstream settings-backed MIME mapping branch.

The lane now also maps top-level `convert.py` batch processing. `BatchConverter` plans folder conversions with upstream chunk sizing, loads `--metadata_file` JSON keyed by basename, applies existing-output skips, min-length preflight, empty-output skips, nonfatal error reporting, and Marker-style output artifact persistence around a supplied native conversion callback.

The lane now also maps top-level `chunk_convert.py` and `chunk_convert.sh` sharded conversion planning. `ChunkConversionPlanner` validates `NUM_DEVICES`, `NUM_WORKERS`, input/output folders, optional metadata/min-length flags, and emits one queueable Marker job per device with upstream chunk arguments without executing the upstream subprocess.

The lane now also maps top-level `convert_single.py` single-document processing. `SingleDocumentConverter` preserves upstream comma-separated language parsing, hands `max_pages`, `start_page`, `langs`, and `batch_multiplier` to a supplied converter callback, saves through Marker's per-document output layout, and intentionally persists empty output because the upstream single-file script does not apply batch skip gates.

The lane now also maps the supplied-output boundary of `marker/layout/layout.py::surya_layout`. `LayoutAnnotator` computes the upstream layout batch size, records text-line detections that would be passed to Surya, assigns supplied layout predictions with Python `zip` semantics, and then feeds the existing native annotation path before WordPress block rendering.

The lane now also maps the pure supplied-boundary half of `marker/ocr/recognition.py::surya_recognition`. `OcrRecognition` scales detector polygons for the higher-resolution Surya OCR pass, drops zero-area polygons before model handoff, reconstructs Marker Page/Block/Line/Span arrays from supplied OCR text lines, and feeds those pages through the existing `run_ocr` stats/replacement boundary.

The lane now also maps `marker/debug/data.py::dump_bbox_debug_data`. `DebugDataExporter` writes Marker-style bbox JSON when debug mode is enabled, omits page images and heavy layout/text-line model fields, and keeps the layout labels, block boxes, and text-line boxes that WordPress import reviewers need.

The lane now also maps `marker/debug/render.py::render_on_image` at the operation-planning boundary. `DebugRenderPlanner` preserves upstream bbox integer casting, `draw_bbox`, scalar/per-box colors, label offsets, white label backgrounds, and zero-size label skipping so a WordPress admin preview can draw the same overlays without PIL or font downloads.

The lane now also maps `marker_app.py::img_to_html` and `marker_app.py::markdown_insert_images`. `MarkdownImageEmbedder` turns supplied PNG image bytes into upstream-style data URI image HTML and replaces Marker Markdown image spans for WordPress preview/review screens without loading Streamlit or pypdfium.

The lane now also maps `marker_server.py` API/upload behavior. `MarkerServerAdapter` normalizes FastAPI-style request params, validates uploaded PDFs, writes and removes the temporary upload path, returns Marker's local success/error response shape with base64 image payloads, and models Datalab remote polling through a supplied callback rather than running FastAPI, Uvicorn, requests, or Python models.

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

`examples/upstream-surrogate-score.php` scores small README-linked surrogate pairs sampled from Marker's committed `data/examples/marker` and `data/examples/nougat` outputs. These remain useful comparison fixtures alongside the now-inspected external CI benchmark archive.

`examples/wordpress-list-import.php` maps Marker's upstream bullet/text cleaners into a Gutenberg list import path. It extracts PDF text lines containing Marker-supported bullet glyphs, normalizes them to Markdown `- ` markers with `TextCleaner`, and emits a core list block.

`examples/wordpress-header-footer-import.php` maps Marker's upstream header/footer cleaner into a repeated-page cleanup path. It removes common first/last page lines after Marker's three-page minimum and emits only the imported body paragraphs as core paragraph blocks.

`examples/wordpress-code-block-import.php` maps Marker's upstream code cleaner into a Gutenberg code-block import path. It uses the native line-length, comment-prefix, indentation-majority, and indentation-reconstruction heuristics from `marker/cleaners/code.py` to keep PDF code samples out of ordinary paragraph blocks.

`examples/wordpress-inline-style-import.php` maps Marker's upstream font-style cleaner and styled-span Markdown post-processing into a paragraph import path. It detects bold and italic spans from PDF font names/weights, emits upstream-style `**bold**` and `*italic*` markers, and converts that focused inline Markdown into `<strong>` and `<em>` inside a core paragraph block.

`examples/wordpress-span-merge-import.php` maps Marker's upstream `merge_spans` wrapper into the paragraph import path. It converts Page/Block/Line/Span arrays into Marker's MergedBlock shape, skips empty-span lines, carries lowercased font metadata, applies the upstream first/last-span emphasis guard, and renders the resulting Markdown emphasis as `<strong>` and `<em>` inside a core paragraph block.

`examples/wordpress-pdftext-block-import.php` maps Marker's upstream pdftext dictionary conversion into a shared-hosting WordPress import path. It accepts already-supplied pdftext output, produces Marker's Page/Block/Line/Span shape natively, normalizes span text, preserves font metadata, and feeds Gutenberg paragraph rendering without loading the Python pdftext, pypdfium, or Surya stacks.

`examples/wordpress-pdftext-page-range-import.php` maps Marker's upstream `get_text_blocks` page-range boundary into a partial WordPress import path. It selects only the requested pages from supplied pdftext dictionaries, preserves page metadata and TOC review comments, and emits Gutenberg paragraph blocks without loading pdftext, pypdfium, or Surya.

`examples/wordpress-page-inspection-preflight.php` maps Marker's upstream Page helper properties into a WordPress review preflight. It records nonblank line/span counts, font-size and line-height distributions, and the upstream `prelim_text` page view as block metadata before content is sent to editorial review.

`examples/wordpress-toc-import.php` maps Marker's upstream heading and TOC cleaners into a document-outline import path. It splits detected heading lines out of text blocks by bounding-box overlap, infers heading levels from line heights, emits a core list as a table of contents, and renders the heading blocks with Marker-style Markdown heading levels.

`examples/wordpress-pdf-outline-import.php` maps Marker's upstream `get_pdf_toc` helper into a PDF bookmark import path. It accepts a pypdfium-style `get_toc(max_depth)` adapter, preserves upstream `title`, `level`, and `page_index` metadata, and emits a Gutenberg list with page/level review attributes before any OCR or layout model runs.

`examples/wordpress-paginated-import.php` maps Marker's upstream Markdown block-merge and full-text assembly path into a paginated Gutenberg import. It preserves PDF page-start markers as separator blocks, then emits merged headings, paragraphs, and lists after applying block type transitions and bbox-based line continuation rules.

`examples/wordpress-reading-order-import.php` maps Marker's upstream layout ordering path into a two-column Gutenberg import. It applies model order positions by maximum bounding-box overlap, uses Marker's vertical-bucket/horizontal tie sorting, keeps page headers before body content and footers after it, then emits the body text as paragraph blocks in reading order.

`examples/wordpress-layout-annotation-import.php` maps Marker's upstream layout annotation path into a Gutenberg import preflight. It assigns Title/Text/Picture labels from supplied layout boxes, merges title fragments that came from the same layout region, applies Marker's bad-span type filtering for Picture text, and emits clean heading plus paragraph blocks without calling Surya.

`examples/wordpress-layout-detection-preflight.php` maps Marker's upstream `surya_layout` assignment boundary into a Gutenberg import preflight. It accepts supplied Surya layout predictions, records the layout batch plan and assigned labels, then emits heading and paragraph blocks through the native annotation path without loading the Surya model.

`examples/wordpress-ocr-triage.php` maps Marker's upstream OCR heuristics into a pre-render import decision. It uses text quality, detected-line coverage, all-empty document detection, and force-OCR flags to send scanned or garbled pages to OCR before Gutenberg block conversion while leaving clean extracted pages on the native text path.

`examples/wordpress-ocr-language-preflight.php` maps Marker's upstream OCR language normalization into multilingual import metadata. It converts human language names from a WordPress PDF metadata file to OCRmyPDF/Tesseract codes, applies Marker's default English fallback when languages are omitted, and rejects invalid engine-specific codes before the OCR handoff.

`examples/wordpress-ocr-detection-preflight.php` maps Marker's upstream `surya_detection` boundary into a WordPress detection preflight. It accepts supplied Surya text-line predictions, attaches them to `page.text_lines` with upstream zip semantics, checks whether the detected text lines cover the extracted PDF line boxes, and preserves those boxes as review metadata before deciding whether a page needs OCR.

`examples/wordpress-ocr-recognition-handoff.php` maps Marker's upstream `run_ocr` orchestration into a WordPress OCR handoff. It uses native page selection and `ocr_stats` accounting, accepts supplied recognized page content from a later OCR adapter, replaces only successful OCR pages, and renders the recovered text as a core paragraph without loading Surya, Tesseract, or OCRmyPDF.

`examples/wordpress-surya-ocr-recognition-import.php` maps Marker's upstream `surya_recognition` pre/post-processing boundary into a WordPress OCR import path. It records the scaled detector polygons that would be sent to Surya, skips a zero-area detection box, builds a Marker page from supplied OCR text-line output, and renders the recovered text as a core paragraph without loading Surya.

`examples/wordpress-bad-span-filter-import.php` maps Marker's upstream block span cleanup into a WordPress import path. It removes a repeated header span ID and clears OCR text from a `Picture` block via `BAD_SPAN_TYPES` before Markdown rendering, while preserving the image filename and bbox metadata needed to render a core image block.

`examples/wordpress-empty-line-filter-import.php` maps the upstream empty-line compaction inside `Block.filter_spans` and `filter_bad_span_types` into a WordPress paragraph import. It starts with extracted lines whose span lists are already empty, keeps the one live text line, and emits a single core paragraph plus review metadata showing the source/kept line counts.

`examples/wordpress-conversion-finalizer-import.php` maps the late `convert_single_pdf` cleanup and assembly order into a WordPress import handoff. It accepts supplied native pages after earlier OCR/layout/table/equation boundaries, removes bad header spans, marks bold body spans, computes TOC and image metadata, normalizes bullet list markers, and emits heading/paragraph/list blocks without loading the upstream Python model stack.

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

`examples/wordpress-marker-api-upload.php` maps Marker's FastAPI upload endpoint into a WordPress import endpoint. It accepts an uploaded PDF payload, applies the upstream `application/pdf` guard, writes a temporary file for the supplied native converter, returns the upstream local response shape, and verifies the upload is cleaned up after conversion.

`examples/wordpress-pdf-image-crop.php` maps Marker's upstream PDF image rendering boundary into a media-review import path. It computes the same `dpi / 72` scale and `render_bbox_image` crop bbox that pypdfium/PIL would use, then stores that crop as Gutenberg image metadata without performing native raster rendering.

`examples/wordpress-bbox-geometry-import.php` maps Marker's upstream bbox geometry helpers into a WordPress import preflight. It merges adjacent PDF span boxes into one reviewable paragraph bbox, preserves that geometry as block metadata, and uses Marker's strict intersection semantics before emitting a related image block.

`examples/wordpress-equation-import.php` maps Marker's upstream equation replacement path into a WordPress math import. It uses Formula layout-box matching, removes the source equation text line, inserts supplied LaTeX as a Formula block after upstream validation, renders the equation as a constrained core HTML block, and emits Marker-style equation metadata for editorial review. The native slice intentionally stops before Texify model inference and equation crop rendering.

`examples/wordpress-output-artifact.php` maps Marker's upstream output writer into a WordPress import handoff. It persists converted block Markdown, `_meta.json` review metadata, and extracted image artifacts under the same per-document folder naming that `marker/output.py` uses.

`examples/wordpress-debug-bbox-export.php` maps Marker's upstream bbox debug dump into a WordPress review workflow. It writes a `_bbox.json` artifact, exposes layout labels and text-line counts for editorial tooling, and confirms heavy model fields are not stored in the review payload.

`examples/wordpress-debug-render-plan.php` maps Marker's upstream debug overlay renderer into a WordPress review workflow. It emits bbox rectangles, label backgrounds, and label text operations that admin-side preview tooling can draw over a page image without loading Python, PIL, or remote fonts.

`examples/wordpress-batch-convert-import.php` maps top-level `convert.py` into a WordPress bulk import job. It plans a folder of PDFs, loads a basename-keyed metadata JSON file with per-file titles, runs native min-length preflight, writes per-document Markdown/metadata artifacts, and reports converted/skipped/error counts without loading Python model workers.

`examples/wordpress-chunk-convert-queue.php` maps top-level `chunk_convert.py` plus `chunk_convert.sh` into a WordPress sharded import queue. It emits per-device queue items with `CUDA_VISIBLE_DEVICES`, `--num_chunks`, `--chunk_idx`, `--workers`, optional metadata, and optional min-length gates while keeping execution in native PHP/planned queue metadata instead of invoking the upstream `marker` command.

`examples/wordpress-single-convert-import.php` maps top-level `convert_single.py` into a WordPress single-upload import job. It passes single-file conversion options to a supplied native converter, writes Marker's Markdown/metadata output layout, and reports the import options a WordPress review screen can display before publishing the converted blocks.

`examples/wordpress-settings-preflight.php` maps Marker's upstream settings defaults into a WordPress import preflight. It accepts `application/pdf`, rejects unsupported MIME types, applies shared-hosting overrides for image extraction and pagination, and exposes page separator and bad-span defaults without importing Torch or model dependencies.

`examples/wordpress-filetype-preflight.php` maps Marker's upstream filetype detection into a WordPress upload preflight. It accepts a real PDF fixture by magic bytes and rejects a ZIP-like `.pdf` payload before heavier conversion steps run.

## Next Task

Next bounded task: use the two external CI benchmark pairs for a larger document-level extraction parity slice, starting with supplied pdftext/layout/table dictionaries for `multicolcnn.pdf` or `switch_trans.pdf`.
