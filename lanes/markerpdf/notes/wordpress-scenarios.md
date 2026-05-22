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

## Next Task

Acquire an actual external upstream benchmark PDF/reference pair from the `benchmark_data` archive, or map another README-linked committed example surrogate before broadening layout/table/OCR behavior.
