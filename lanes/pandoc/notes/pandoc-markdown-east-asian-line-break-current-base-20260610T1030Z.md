# Pandoc Markdown east Asian line break current-base slice

Date: 2026-06-10 UTC
Bead: plib-4m96

## Scope

- Added opt-in native PHP Markdown reader support for Pandoc-style east Asian line-break handling through
  `MarkdownReader(['eastAsianLineBreaks' => true])`.
- Softbreaks directly between CJK script characters are suppressed, so source-wrapped Japanese, Chinese,
  or Korean text joins in the AST and WordPress output.
- Latin softbreaks, explicit two-space hardbreaks, and default reader behavior remain unchanged.
- This is a bounded Markdown extension slice only; it does not claim full line-breaking, Unicode segmentation,
  or locale-sensitive wrapping parity.

## Verification

- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed 1 file / 6548 assertions / 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed 44 files / 59421 assertions / 0 failures.

## Accounting

- `lane-status.json` `phpPass`: 2950 -> 2951.
- `lane-status.json` focused checks: 853 -> 854.
- `phpFail` remains 0.

No Pandoc binary, Cabal/Haskell runner, browser renderer, external validator, online service, live provider
test, Node tooling, zip/unzip, office suite, or TeX/PDF engine was invoked.
