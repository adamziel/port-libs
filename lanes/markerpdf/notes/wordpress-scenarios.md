# markerPDF WordPress Scenario

PDF import into clean post content and Data Liberation document conversion workflows.

## Current Native Slice

Native PDF content stream text-line extraction for literal, array, hex, UTF-16 hex, FlateDecode streams, adjacent same-line text operators, PDF line continuations, and text line movement operators.

The lane now also ports a narrow slice of `marker/postprocessors/markdown.py`: hyphenated line dewrapping, sentence paragraph breaks, heading/list/text wrapping, and `#` escaping. That gives the WordPress import path a native cleanup step before block rendering.

`examples/wordpress-import.php` reads `fixtures/wordpress-import-content.pdf` and emits heading/paragraph block comments. This keeps the lane tied to a practical Data Liberation workflow: extracting embedded PDF text into block-ready post content without shelling out to Python or native PDF binaries.

`examples/wordpress-import-wrapped.php` reads `fixtures/wordpress-wrapped-content.pdf`, dewraps split PDF text lines with `MarkdownPostProcessor`, and emits a clean paragraph block:

```html
<!-- wp:paragraph -->
<p>Clean hyphenated paragraphs keep WordPress imports readable.</p>
<!-- /wp:paragraph -->
```

`examples/wordpress-quality-score.php` uses the native `BenchmarkScorer` port of `marker/benchmark/scoring.py` to compare extracted and dewrapped import text against expected WordPress post content. It emits a JSON score and checks whether the result clears a Marker CI-style quality threshold.

`examples/upstream-surrogate-score.php` scores a small README-linked `multicolcnn.pdf` surrogate pair sampled from Marker's committed `data/examples/marker` and `data/examples/nougat` outputs. This keeps benchmark scoring tied to an upstream document-output pair while the external `benchmark_data` PDF/reference archive remains unavailable in this VM.

`examples/wordpress-list-import.php` maps Marker's upstream bullet/text cleaners into a Gutenberg list import path. It extracts PDF text lines containing Marker-supported bullet glyphs, normalizes them to Markdown `- ` markers with `TextCleaner`, and emits a core list block.

`examples/wordpress-header-footer-import.php` maps Marker's upstream header/footer cleaner into a repeated-page cleanup path. It removes common first/last page lines after Marker's three-page minimum and emits only the imported body paragraphs as core paragraph blocks.

`examples/wordpress-code-block-import.php` maps Marker's upstream code cleaner into a Gutenberg code-block import path. It uses the native line-length, comment-prefix, indentation-majority, and indentation-reconstruction heuristics from `marker/cleaners/code.py` to keep PDF code samples out of ordinary paragraph blocks.

`examples/wordpress-inline-style-import.php` maps Marker's upstream font-style cleaner and styled-span Markdown post-processing into a paragraph import path. It detects bold and italic spans from PDF font names/weights, emits upstream-style `**bold**` and `*italic*` markers, and converts that focused inline Markdown into `<strong>` and `<em>` inside a core paragraph block.

`examples/wordpress-toc-import.php` maps Marker's upstream heading and TOC cleaners into a document-outline import path. It splits detected heading lines out of text blocks by bounding-box overlap, infers heading levels from line heights, emits a core list as a table of contents, and renders the heading blocks with Marker-style Markdown heading levels.

`examples/wordpress-paginated-import.php` maps Marker's upstream Markdown block-merge and full-text assembly path into a paginated Gutenberg import. It preserves PDF page-start markers as separator blocks, then emits merged headings, paragraphs, and lists after applying block type transitions and bbox-based line continuation rules.

`examples/wordpress-reading-order-import.php` maps Marker's upstream layout ordering path into a two-column Gutenberg import. It applies model order positions by maximum bounding-box overlap, uses Marker's vertical-bucket/horizontal tie sorting, keeps page headers before body content and footers after it, then emits the body text as paragraph blocks in reading order.

`examples/wordpress-ocr-triage.php` maps Marker's upstream OCR heuristics into a pre-render import decision. It uses text quality, detected-line coverage, all-empty document detection, and force-OCR flags to send scanned or garbled pages to OCR before Gutenberg block conversion while leaving clean extracted pages on the native text path.

`examples/wordpress-table-score.php` maps `marker/benchmark/table.py` into a table import quality check. It compares an OCR-noisy Markdown table against the expected WordPress table content and verifies the score clears Marker's upstream table report threshold of `0.7`.

`examples/wordpress-table-format-import.php` maps the post-recognition half of `marker/tables/table.py::format_tables` into a Gutenberg table import path. It removes the source PDF Table block, inserts supplied Markdown table output at Marker's layout-derived insertion point, and renders the result as a core table block without shelling out to the upstream Surya/tabled recognition stack.

`examples/wordpress-table-cleanup-import.php` maps `marker/tables/utils.py` into a Gutenberg table cleanup path. It sorts recognized cell blocks into row order, removes long dot leaders used as visual fillers, collapses embedded table-cell newlines, and emits the cleaned result as a core table block.

`examples/wordpress-image-import.php` maps Marker's upstream image insertion path into a Gutenberg image-block import. It uses deterministic `page_image_index.png` filenames, Figure/Picture layout-box matching, intersecting text-span removal, and Marker-style Markdown image spans before rendering a core image block. The native slice intentionally stops before raster crop rendering because upstream delegates that work to `pypdfium2` and PIL.

`examples/wordpress-equation-import.php` maps Marker's upstream equation replacement path into a WordPress math import. It uses Formula layout-box matching, removes the source equation text line, inserts supplied LaTeX as a Formula block after upstream validation, renders the equation as a constrained core HTML block, and emits Marker-style equation metadata for editorial review. The native slice intentionally stops before Texify model inference and equation crop rendering.

## Next Task

Acquire an actual external upstream benchmark PDF/reference pair from the `benchmark_data` archive, then map focused table-layout/raster-rendering behavior or exact Texify tokenizer/model boundaries against a concrete upstream artifact.
