# Pandoc Plain Writer Source-Line Wrap Diagnostics

Implemented one bounded native PHP plain-writer diagnostics slice for source-line
wrapping samples and aggregate wrapped source-line counts.

## Behavior

- `PlainWriter::writeWithDiagnostics()` now materializes aggregate
  `wrappedSourceLineCount` and `maxWrappedSourceLineDisplayWidth` diagnostics
  from the same bounded source-line metrics path used for wrapped source-line
  samples.
- Each sampled wrapped source line records the block index, source line index,
  source display width, emitted output line count, generated break count, forced
  wrap break count, maximum emitted line width, and a bounded text sample.
- The helper uses the same `preg_split('/\R/u')` source-line boundary and
  `UnicodeText::wrapByDisplayWidth()` wrapping behavior as the existing
  `wrapSplitLineCount` and `generatedWrapBreakCount` counters.

This slice does not invoke Pandoc, JSON filters, Cabal/Haskell runners, browser
renderers, office suites, TeX/PDF engines, external validators, online services,
live provider tests, or live-service provider tests.

## Verification

- `php -l lanes/pandoc/src/PlainWriter.php`
- `php -l lanes/pandoc/tests/PlainWriterTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PlainWriterTest.php`
  - Result after rebase: 1 test file, 235 assertions, 0 failures.

Mapped accounting records one PlainWriter source-line diagnostics case with
seven assertions tied to the bounded source-line sample behavior.
