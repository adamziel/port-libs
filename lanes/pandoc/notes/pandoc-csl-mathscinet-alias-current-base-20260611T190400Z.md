# CSL/BibLaTeX MathSciNet Alias Slice

Date: 2026-06-11
Base: current main 30462ed7c

## Scope

This slice normalizes MathSciNet aliases in the native PHP CSL/BibLaTeX handoff without invoking Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser renderers, external validators, online services, live provider tests, or live-service provider tests.

## Behavior

- `BibtexCslParser` maps BibLaTeX `mathscinet` fields into canonical `MRNumber` metadata while preserving raw BibTeX field provenance.
- `CitationCslProcessor` maps direct CSL JSON `mathscinet` keys into normalized `mrNumber` metadata.
- Existing CSL variable rendering for `mathscinet`, `mrnumber`, and `mr-number` now works for both BibLaTeX input and direct item input.
- Bibliography rendering and WordPress handoff keep MathSciNet review identifiers visible.

## Verification

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`: 1 test file, 4758 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`: 44 test files, 65551 assertions, 0 failures.

## Accounting

- `phpPass`: 3104 -> 3105
- `phpFail`: 0
- Added `mappedCslBiblatexMathscinetAliasCases`: 1
- Added `cslBiblatexMathscinetAliasAssertions`: 9
