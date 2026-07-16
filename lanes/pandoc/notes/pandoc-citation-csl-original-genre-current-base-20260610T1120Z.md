# Citation/CSL original-genre handoff slice

## Scope

- Preserve BibTeX/BibLaTeX original source genre/type provenance from `origtype`, `origgenre`, `originaltype`, `original-type`, `originalgenre`, and `original-genre`.
- Normalize that provenance as CitationCslProcessor `originalGenre` metadata.
- Expose the value to CSL styles through `original-genre`, `origtype`, and `origgenre` text variables.
- Carry the value through the default bibliography renderer and WordPress bibliography review output.

## Implementation

- `BibtexCslParser::entryToCslItem()` now emits `original-genre` from bounded original-genre/type field aliases.
- `CitationCslProcessor::normalizeItem()` accepts both CSL-style and internal original-genre aliases.
- Default bibliography output now includes `Original genre: ...` when present.
- CSL variable rendering maps `original-genre`, `origtype`, and `origgenre` to normalized `originalGenre`.

## Verification

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - 1 test file, 4233 assertions, 0 failures after rebasing onto `origin/main`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 59674 assertions, 0 failures after rebasing onto `origin/main`.

## Accounting

- `phpPass` increments from 2954 to 2955 on top of the rebased `origin/main`.
- `phpFail` remains 0.
- The slice stays native PHP and does not invoke Pandoc, citeproc, BibTeX, Biber, bibliography managers, office suites, browser renderers, online services, or live provider tests.
