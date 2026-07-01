# Pandoc Markdown Definition List Profile Completion - 2026-07-01

Bead: `plib-h8wff`

Scope:
- Completed native PHP Markdown reader handling for existing upstream-mapped
  definition-list and line-block profile fixtures.
- Kept the slice inside `lanes/pandoc` and did not invoke Pandoc, office suites,
  TeX/browser engines, Typst, Jupyter, Node tooling, zip/unzip, external
  validators, or live services.

Implementation:
- Definition-list parsing now treats only `:` and `~` markers indented by at
  most three spaces as native definition markers, leaving four-space markers,
  tab-indented markers, fenced div openers, and tilde fences as non-definition
  source.
- Marker-line definition bodies preserve enough post-marker indentation for
  indented code blocks while still trimming marker padding for paragraph,
  list, blockquote, heading, fence, and line-block bodies.
- Lazy definition continuation stops before the next term-plus-marker run, so
  adjacent terms remain separate items in the same definition list.
- Line blocks are now gated by the `line_blocks` extension/profile switch,
  matching the existing flavor matrix for default Markdown, `commonmark_x`, and
  explicit `+line_blocks` overrides.

Validation:
- Red-first `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderDefinitionListFinalHarvestTest.php`
  showed 1 file, 41 assertions, 2 failures before the parser fix.
- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderDefinitionListBodyFinalHarvestTest.php lanes/pandoc/tests/MarkdownReaderDefinitionListFinalHarvestTest.php lanes/pandoc/tests/MarkdownReaderLineBlockProfileSurgeTest.php lanes/pandoc/tests/MarkdownReaderFlavorProfileSurgeTest.php lanes/pandoc/tests/MarkdownReaderFlavorExtensionProfileCompletionTest.php`
  - 5 files, 2,987 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReader*Test.php`
  - 110 files, 27,451 assertions, 3,735 unrelated baseline failures outside this definition-list/line-block slice.
- `php tools/run-tests.php lanes/pandoc/tests/*.php`
  - 534 files, 143,748 assertions, 8,818 unrelated baseline failures across the existing Pandoc lane.
