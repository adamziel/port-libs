# Pandoc HTML Writer List Attributes Slice

## Scope

- Current main already provides the bounded native PHP WordPress writer handoff
  for Pandoc-style `Attr` metadata on bullet and ordered list containers. This
  slice retains the focused regression coverage for that path on the current
  helper implementation.
- Preserves safe list `id`, classes, `data-*`, `aria-*`, `lang`, `xml:lang`,
  `dir`, and `title` attributes through the existing HTML-writer sanitizer.
- Keeps task-list classes, ordered-list `start` and `type` attributes, and
  existing ODF `data-odf-list-*` review metadata intact.
- Filters unsafe event handlers and generic style attributes.

## Evidence

- Worker red check before implementation on the original base:
  `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - 1 file, 6297 assertions, 1 failure.
- `php -l lanes/pandoc/src/WordPressBlockWriter.php`
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - 1 file, 6424 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 files, 58372 assertions, 0 failures.

## Notes

This slice stays under `lanes/pandoc` and does not run Pandoc, Haskell/Cabal
runners, browser renderers, external validators, Node tooling, office suites,
TeX/PDF engines, or online services. Direct-format parity accounting moves by
one focused PHP pass while keeping `phpFail` at zero: `phpPass` 2903 -> 2904
and `suiteProgress` 806 -> 807 on the current base.
