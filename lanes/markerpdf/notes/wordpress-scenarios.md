# markerPDF WordPress Scenario

PDF import into clean post content and Data Liberation document conversion workflows.

## Current Native Slice

Native PDF content stream text-line extraction for literal, array, hex, UTF-16 hex, FlateDecode streams, adjacent same-line text operators, PDF line continuations, and text line movement operators.

The lane now also maps the upstream `pdftext` dictionary boundary from `marker/pdf/extract_text.py::pdftext_format_to_blocks`. `PdfTextBlockConverter` converts supplied pdftext page dictionaries into Marker's native Page/Block/Line/Span arrays, including font flag suffixes, span IDs, rotation-aware page bboxes, and pdftext hyphen/newline cleanup before later layout annotation.

The lane now also maps `marker/pdf/utils.py::find_filetype`. `FiletypeDetector` uses magic-MIME style detection to accept PDF uploads, reject extension-spoofed non-PDF payloads as `other`, and preserve the upstream settings-backed MIME mapping branch.

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

`examples/wordpress-toc-import.php` maps Marker's upstream heading and TOC cleaners into a document-outline import path. It splits detected heading lines out of text blocks by bounding-box overlap, infers heading levels from line heights, emits a core list as a table of contents, and renders the heading blocks with Marker-style Markdown heading levels.

`examples/wordpress-pdf-outline-import.php` maps Marker's upstream `get_pdf_toc` helper into a PDF bookmark import path. It accepts a pypdfium-style `get_toc(max_depth)` adapter, preserves upstream `title`, `level`, and `page_index` metadata, and emits a Gutenberg list with page/level review attributes before any OCR or layout model runs.

`examples/wordpress-paginated-import.php` maps Marker's upstream Markdown block-merge and full-text assembly path into a paginated Gutenberg import. It preserves PDF page-start markers as separator blocks, then emits merged headings, paragraphs, and lists after applying block type transitions and bbox-based line continuation rules.

`examples/wordpress-reading-order-import.php` maps Marker's upstream layout ordering path into a two-column Gutenberg import. It applies model order positions by maximum bounding-box overlap, uses Marker's vertical-bucket/horizontal tie sorting, keeps page headers before body content and footers after it, then emits the body text as paragraph blocks in reading order.

`examples/wordpress-layout-annotation-import.php` maps Marker's upstream layout annotation path into a Gutenberg import preflight. It assigns Title/Text/Picture labels from supplied layout boxes, merges title fragments that came from the same layout region, applies Marker's bad-span type filtering for Picture text, and emits clean heading plus paragraph blocks without calling Surya.

`examples/wordpress-ocr-triage.php` maps Marker's upstream OCR heuristics into a pre-render import decision. It uses text quality, detected-line coverage, all-empty document detection, and force-OCR flags to send scanned or garbled pages to OCR before Gutenberg block conversion while leaving clean extracted pages on the native text path.

`examples/wordpress-ocr-language-preflight.php` maps Marker's upstream OCR language normalization into multilingual import metadata. It converts human language names from a WordPress PDF metadata file to OCRmyPDF/Tesseract codes, applies Marker's default English fallback when languages are omitted, and rejects invalid engine-specific codes before the OCR handoff.

`examples/wordpress-ocr-detection-preflight.php` maps Marker's upstream `surya_detection` boundary into a WordPress detection preflight. It accepts supplied Surya text-line predictions, attaches them to `page.text_lines` with upstream zip semantics, checks whether the detected text lines cover the extracted PDF line boxes, and preserves those boxes as review metadata before deciding whether a page needs OCR.

`examples/wordpress-ocr-recognition-handoff.php` maps Marker's upstream `run_ocr` orchestration into a WordPress OCR handoff. It uses native page selection and `ocr_stats` accounting, accepts supplied recognized page content from a later OCR adapter, replaces only successful OCR pages, and renders the recovered text as a core paragraph without loading Surya, Tesseract, or OCRmyPDF.

`examples/wordpress-table-score.php` maps `marker/benchmark/table.py` into a table import quality check. It compares an OCR-noisy Markdown table against the expected WordPress table content and verifies the score clears Marker's upstream table report threshold of `0.7`.

`examples/wordpress-table-box-plan.php` maps Marker's upstream `get_table_boxes` boundary into a Gutenberg table review path. It merges adjacent Table layout boxes with the locked `tabled-pdf` helper semantics, emits high-resolution crop bboxes that a table-recognition adapter would consume, duplicates supplied text-line detections for non-OCR pages, and marks OCRed pages with null text-line payloads so cell boxes can be redetected before rendering.

`examples/wordpress-table-format-import.php` maps the post-recognition half of `marker/tables/table.py::format_tables` into a Gutenberg table import path. It removes the source PDF Table block, inserts supplied Markdown table output at Marker's layout-derived insertion point, and renders the result as a core table block without shelling out to the upstream Surya/tabled recognition stack.

`examples/wordpress-table-recognition-handoff.php` maps the supplied table-recognition handoff into a Gutenberg table import path. It accepts tabled-style cell/row/column geometry from a later detector adapter, applies native `assign_rows_columns`-style row/column assignment, preserves row/column span metadata for multi-column headers and row-spanning labels, renders spans as table-cell attributes, cleans dot leaders and embedded newlines, formats Markdown, and then reuses Marker's `format_tables` replacement path before rendering a core table block.

`examples/wordpress-table-cleanup-import.php` maps `marker/tables/utils.py` into a Gutenberg table cleanup path. It sorts recognized cell blocks into row order, removes long dot leaders used as visual fillers, collapses embedded table-cell newlines, and emits the cleaned result as a core table block.

`examples/wordpress-image-import.php` maps Marker's upstream image insertion path into a Gutenberg image-block import. It uses deterministic `page_image_index.png` filenames, Figure/Picture layout-box matching, intersecting text-span removal, and Marker-style Markdown image spans before rendering a core image block. The native slice intentionally stops before raster crop rendering because upstream delegates that work to `pypdfium2` and PIL.

`examples/wordpress-pdf-image-crop.php` maps Marker's upstream PDF image rendering boundary into a media-review import path. It computes the same `dpi / 72` scale and `render_bbox_image` crop bbox that pypdfium/PIL would use, then stores that crop as Gutenberg image metadata without performing native raster rendering.

`examples/wordpress-bbox-geometry-import.php` maps Marker's upstream bbox geometry helpers into a WordPress import preflight. It merges adjacent PDF span boxes into one reviewable paragraph bbox, preserves that geometry as block metadata, and uses Marker's strict intersection semantics before emitting a related image block.

`examples/wordpress-equation-import.php` maps Marker's upstream equation replacement path into a WordPress math import. It uses Formula layout-box matching, removes the source equation text line, inserts supplied LaTeX as a Formula block after upstream validation, renders the equation as a constrained core HTML block, and emits Marker-style equation metadata for editorial review. The native slice intentionally stops before Texify model inference and equation crop rendering.

`examples/wordpress-output-artifact.php` maps Marker's upstream output writer into a WordPress import handoff. It persists converted block Markdown, `_meta.json` review metadata, and extracted image artifacts under the same per-document folder naming that `marker/output.py` uses.

`examples/wordpress-settings-preflight.php` maps Marker's upstream settings defaults into a WordPress import preflight. It accepts `application/pdf`, rejects unsupported MIME types, applies shared-hosting overrides for image extraction and pagination, and exposes page separator and bad-span defaults without importing Torch or model dependencies.

`examples/wordpress-filetype-preflight.php` maps Marker's upstream filetype detection into a WordPress upload preflight. It accepts a real PDF fixture by magic bytes and rejects a ZIP-like `.pdf` payload before heavier conversion steps run.

## Next Task

Tighten the tabled heuristic layout edge cases, especially DBSCAN-style column separator clustering, then use the two external CI benchmark pairs for a larger document-level extraction parity slice.
