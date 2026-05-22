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

## Next Task

Acquire or sample one upstream benchmark PDF/reference pair and map it to a native PHP parity fixture before broadening layout/table/OCR behavior.
