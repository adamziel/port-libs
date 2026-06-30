# Citation/CSL Standalone BibLaTeX Entry Option Fields

Slice: `plib-8k0yp`

This slice folds recognized standalone BibLaTeX entry-option fields into the CSL
`biblatex-options` review metadata produced by the native PHP BibTeX/BibLaTeX
paths.

Coverage:

- `BibtexCslParser` now merges `options={...}` with standalone entry option
  fields such as `skipbib`, `dataonly`, `sortlocale`, `labeldateparts`,
  `useauthor`, `useeditor`, `useprefix`, `skiplab`, `uniquelist`, and
  `uniquename`.
- Standalone fields replace same-named `options={...}` entries using canonical
  BibLaTeX option names, preserving stable review ordering for downstream CSL
  summaries.
- Direct bibliography item import now treats truthy standalone `dataonly` and
  `data-only` fields as support records and omits them from emitted item lists.
- `BibtexCslProcessor` mirrors the same standalone option-field merge for the
  legacy handoff path so `CitationCslProcessor::fromItems()` receives the same
  CSL review metadata.

Accounting:

- Adds two focused mapped Citation/CSL behavior checks: one direct
  `CitationCslProcessor::bibtexItems()` case and one legacy
  `BibtexCslProcessor` handoff case.
- Direct-format parity denominator is unchanged: Pandoc format-token support
  remains governed by the existing 51 input and 75 output format registry, with
  unsupported direct formats still tracked separately.

Validation:

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with 6,028 assertions and 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  passed with 647 assertions and 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` was attempted as the full lane
  gate and remains baseline-red: 303 test files, 118,738 assertions, and 9,634
  failures. The new Citation/CSL cases passed in that run; visible failures are
  outside this slice in existing Markdown/template/HTML/YAML/unicode reference
  coverage.
