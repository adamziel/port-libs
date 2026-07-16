# Pandoc CSL Direct Import Alias Variable Rendering

Slice: `plib-mancn`, Pandoc citation/bibliography CSL core blocker `20260612T041121Z`.

## Scope

Direct CSL JSON items already normalized several import-shaped aliases into
canonical CSL review metadata. This slice closes the corresponding style
variable exposure gap for bounded native PHP rendering:

- `publicationStatus`, `publication-status`, and `pubstate` render and sort as
  `status`.
- `keywordList`, `keyword-list`, and `keywordlist` render through keyword-list
  summary output.
- `categoryList`, `category-list`, and `categorylist` render through
  category-list summary output.
- Camel/compact citation alias variables render from the normalized citation
  alias list and summary.

No Pandoc, citeproc, BibTeX, Biber, bibliography manager, browser renderer,
external validator, online service, live provider test, or live-service
provider test is required for this bounded native CSL handoff.

## Accounting

- Focused case: `renders bounded direct csl json import aliases as csl variables`
- `phpPass`: `3586 -> 3587`
- Mapped cases: `mappedCslDirectImportAliasVariableRenderCases = 10`
- Focused assertions: `cslDirectImportAliasVariableRenderAssertions = 23`

## Verification

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test file, 5722 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 84177 assertions, 0 failures`
