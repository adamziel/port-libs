# Pandoc HTML Writer Attributes Slice

## Scope

- Adds a bounded native PHP WordPress writer handoff for Pandoc-style `Attr`
  metadata on heading and code-block HTML output.
- Preserves safe `id`, `class`, `data-*`, `aria-*`, `lang`, `xml:lang`,
  `dir`, and `title` attributes.
- Filters unsafe event handlers and style attributes on this handoff path.

## Evidence

- `php -l lanes/pandoc/src/WordPressBlockWriter.php`
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - 1 file, 6135 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 40 files, 56591 assertions, 0 failures.

## Notes

This slice stays under `lanes/pandoc` and does not run Pandoc, Haskell/Cabal
runners, browsers, external validators, Node tooling, or online services.
