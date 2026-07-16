# Pandoc HTML Writer Separator Attributes Slice

## Scope

- Extends the bounded native PHP WordPress writer handoff for Pandoc-style
  `Attr` metadata on horizontal-rule/separator output.
- Preserves safe `id`, `class`, `data-*`, `aria-*`, and `xml:lang` attributes on
  top-level WordPress separator blocks.
- Applies the same safe attribute preservation when separators render inside
  nested HTML contexts such as Div blocks.
- Keeps unsafe event handlers and style attributes filtered while retaining the
  WordPress separator classes.

## Evidence

- `php -l lanes/pandoc/src/WordPressBlockWriter.php`
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - 1 file, 6461 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 files, 58568 assertions, 0 failures.

## Notes

This slice stays under `lanes/pandoc` and does not invoke Pandoc,
Haskell/Cabal runners, browser renderers, external validators, Node tooling,
office suites, TeX/PDF engines, or online services. Direct-format parity
accounting moves by one focused PHP pass while keeping `phpFail` at zero.
