# Pandoc HTML Writer Role Attributes Slice

## Scope

- Added bounded native `WordPressBlockWriter` output for safe Pandoc/HTML
  `role` attributes across block, inline, and table handoff paths.
- Kept existing unsafe `on*` event and `style` filtering in place while allowing
  reviewer/a11y role metadata to survive alongside existing `data-*`, `aria-*`,
  language, title, id, and class attributes.
- Stayed within `lanes/pandoc` and did not add Pandoc, Cabal/Haskell runners,
  browser renderers, validators, office tools, archive tools, Node tooling, or
  online services.

## Verification

- `php -l lanes/pandoc/src/WordPressBlockWriter.php`
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - 1 test file, 6313 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 test files, 57664 assertions, 0 failures

## Accounting

- `phpPass` moves from 2857 to 2858 with one focused HTML writer role-attribute
  pass case.
- `phpFail` remains 0.
- The mapped denominator moves from 3063 to 3064; mapped suite progress moves
  from 760 to 761 focused checks.
