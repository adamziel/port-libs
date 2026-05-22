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

`examples/wordpress-table-score.php` maps `marker/benchmark/table.py` into a table import quality check. It compares an OCR-noisy Markdown table against the expected WordPress table content and verifies the score clears Marker's upstream table report threshold of `0.7`.

## Next Task

Acquire an actual external upstream benchmark PDF/reference pair from the `benchmark_data` archive, then map another focused layout/table/OCR behavior against a concrete upstream artifact.
