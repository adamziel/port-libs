# Pandoc HTML Reader Microdata Value Summaries

2026-07-02 UTC

- `HtmlReader` now records metadata-only value source/type count summaries for
  each bounded Microdata item and for the full HTML document import.
- The summaries are derived from existing per-property records, so they do not
  add a new parser surface, fetch external resources, execute browser DOM code,
  or expose payload bytes beyond the existing bounded property metadata.
- Focused coverage maps a document-level Event item with text, URL, content,
  value, datetime, and nested-item Microdata properties into
  `htmlMicrodataValueSourceCounts`, `htmlMicrodataValueTypeCounts`, and
  per-item `valueSourceCounts` / `valueTypeCounts`.

Validation:

- `php -l lanes/pandoc/src/HtmlReader.php`
- `php -l lanes/pandoc/tests/HtmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/HtmlReaderTest.php`
  - 1 test file, 78 assertions, 0 failures

No external Pandoc, office suite, TeX/browser engine, Typst, Jupyter, Node,
zip/unzip, validators, fetchers, or live services were invoked.
