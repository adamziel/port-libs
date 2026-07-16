# Pandoc HTML Writer Paragraph And Quote Attributes

Slice: extend the native WordPress HTML-writer attribute handoff so safe Pandoc
Attr metadata survives on paragraph, plain, and quote output.

Implementation:
- `WordPressBlockWriter` renders safe `id`, `class`, `data-*`, `aria-*`,
  `lang`, `title`, and related HTML-writer attrs on paragraph/plain tags.
- Quote blocks merge source classes with the WordPress quote class while
  preserving safe `id`/metadata attrs.
- Unsafe event and style attributes remain filtered.

Evidence:
- `php -l lanes/pandoc/src/WordPressBlockWriter.php`
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  passed: 1 file, 6195 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php
  lanes/pandoc/tests/DocxReaderTest.php lanes/pandoc/tests/OdfReaderTest.php`
  passed: 3 files, 13983 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 42 files, 56873 assertions, 0 failures.

External tools:
- No Pandoc, browser engine, office suite, TeX engine, Node tooling, or
  external validator was invoked.
