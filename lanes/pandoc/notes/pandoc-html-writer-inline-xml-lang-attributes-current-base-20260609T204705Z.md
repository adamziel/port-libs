# Pandoc HTML Writer Inline XML Lang Attributes Slice

## Scope

- Extends the bounded native PHP WordPress writer handoff for inline
  Pandoc-style `Attr` metadata.
- Preserves safe `xml:lang` attributes on inline span, code, link, and image
  output, matching the existing block-level HTML writer attribute policy.
- Keeps unsafe event handlers and style attributes filtered.
- Skips non-scalar and empty inline attribute values before serialization.

## Evidence

- `php -l lanes/pandoc/src/WordPressBlockWriter.php`
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - 1 file, 6267 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 files, 57301 assertions, 0 failures.

## Notes

This slice stays under `lanes/pandoc` and does not invoke Pandoc,
Haskell/Cabal runners, browser renderers, external validators, Node tooling,
office suites, TeX/PDF engines, or online services. Direct-format parity
accounting moves by one focused PHP pass while keeping `phpFail` at zero.
