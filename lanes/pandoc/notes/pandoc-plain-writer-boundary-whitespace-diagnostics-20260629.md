# Pandoc Plain Writer Boundary Whitespace Diagnostics

Implemented one bounded native PHP plain-writer diagnostics slice for leading
and trailing break-whitespace margins in rendered source lines.

## Behavior

- `PlainWriter::writeWithDiagnostics()` now reports aggregate and per-block
  counts for source lines with leading or trailing break whitespace.
- Diagnostics also expose the widest leading and trailing break-whitespace
  display widths, using the same native `UnicodeText` display-width accounting
  as the plain writer wrapping path.
- The focused fixture verifies spaces and tabs at line boundaries are visible
  to review diagnostics while the emitted plain text continues to use the
  existing trim-and-wrap behavior.

This slice does not invoke Pandoc, Cabal/Haskell runners, browser renderers,
office suites, TeX/PDF engines, JSON filters, external validators, online
services, live provider tests, or live-service provider tests.

## Verification

- `php -l lanes/pandoc/src/PlainWriter.php`
- `php -l lanes/pandoc/tests/PlainWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PlainWriterTest.php`
  - Result: 1 test file, 251 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: 295 test files, 117,032 assertions, 9,783 failures.
  - The full lane remains baseline-red outside this slice, with visible
    failures in DocBook, LaTeX writer, Markdown surge/round-trip, and other
    unrelated suites.

## Accounting

- `lane-status.json` `phpPass`: `458 -> 459`.
- `lane-status.json` `phpFail`: remains `0`.
- No upstream manifest denominator change is claimed; this is focused native
  plain-writer diagnostic accounting, not a new external Pandoc runner case.
