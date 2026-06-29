# Pandoc JSON/native WordPress attribute/raw handoff - 2026-06-29

Bead: plib-6805u

This slice closes the JSON/native AST constructor completeness blocker around WordPress and Markdown handoff behavior:

- Pandoc JSON/native `RawInline` constructors with unsupported formats now stay inert in Markdown and WordPress output, while HTML-family and TeX-family aliases remain available where the writers already support them.
- Textual native `RawInline` parsing preserves generic RawInline constructor provenance, so unsupported raw inline formats remain inert after NativeWriter/NativeReader text round trips too.
- WordPress block output preserves sanitized `xml:lang` attributes alongside existing `data-*`, `aria-*`, class/id/title, and related safe attributes across inline, block, table, image, and image-figure surfaces.
- Image-backed Pandoc figure captions use normalized plain caption text, preserving metadata caption text without leaking inline markup into WordPress image captions; non-image figures keep the rich inline caption path.
- Image figure wrappers now carry safe JSON/native attributes such as `data-*`, `xml:lang`, and `title`, while still mapping `latex-placement` to `data-pandoc-latex-placement` and filtering style/event attributes.

Validation:

- `php -l lanes/pandoc/src/MarkdownWriter.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/src/WordPressBlockWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeRawHtmlAdjacencyBoundaryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownWriterRawFormatAliasCompletionTest.php lanes/pandoc/tests/PandocJsonNativeRawHtmlAdjacencyBoundaryTest.php` passed with 122 assertions and 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php` passed with 6,075 assertions and 0 failures.

No upstream Pandoc, office, TeX, browser, ZIP, Jupyter, Node, or external validator process was invoked.
