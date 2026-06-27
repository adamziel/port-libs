# Pandoc JSON/native WordPress attribute and raw alias slice

Area: Pandoc JSON/native AST constructor completeness.

This bounded slice adds JSON/native coverage for native-text raw format aliases
already handled by the PHP reader stack: markdown-family `RawBlock` and
`RawInline` payloads hydrate to `raw_markdown`, TeX-family block aliases hydrate
to `raw_tex`, TeX-family inline aliases hydrate to `raw_tex_inline`, and
unsupported formats such as `opml` stay as generic `raw_inline` nodes while
round-tripping through JSON/native writers.

The WordPress handoff now preserves safe Pandoc JSON attributes on Div, Figure,
Span, Link, Code, and Image output, including `xml:lang` plus safe `data-*`,
`aria-*`, `title`, `id`, and `class` values. Unsafe inline styles and event
handler attributes remain filtered. Figure-level `latex-placement` continues to
emit only as `data-pandoc-latex-placement`.

Validation:
- `php -l lanes/pandoc/src/WordPressBlockWriter.php` passed.
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  reached 6,078 assertions with 3 remaining baseline failures outside this
  slice: default Markdown generic raw-attribute output and rich figure caption
  markup expectations.
