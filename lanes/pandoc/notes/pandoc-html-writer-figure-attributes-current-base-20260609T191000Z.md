# Pandoc HTML Writer Figure Attributes Slice

## Scope

- Extends the bounded native PHP WordPress writer handoff for Pandoc-style
  `Attr` metadata from headings/code blocks to figure output.
- Preserves safe figure `id`, classes, `data-*`, `aria-*`, `lang`,
  `xml:lang`, `dir`, and `title` attributes while keeping the required
  `wp-block-image` class.
- Keeps the existing `latex-placement` review bridge as
  `data-pandoc-latex-placement`.
- Filters unsafe event handlers and style attributes on this handoff path.

## Evidence

- Red check before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - 1 file, 6190 assertions, 1 failure.
- `php -l lanes/pandoc/src/WordPressBlockWriter.php`
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - 1 file, 6200 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 files, 56946 assertions, 0 failures.

## Notes

This slice stays under `lanes/pandoc` and does not run Pandoc, Haskell/Cabal
runners, browsers, external validators, Node tooling, TeX/PDF engines, or
online services.
