# Citation/CSL BibLaTeX hyphen title alias slice

## Scope

- Accept CSL-style and hyphenated BibLaTeX title metadata aliases in the native BibTeX/CSL handoff.
- Cover short title, title addendum, container title/addendum, main title/addendum, volume title, part title, issue title/addendum, and event title/addendum/type aliases.
- Preserve the existing native CSL rendering path for citation, bibliography, and WordPress review output.

## Implementation

- `BibtexCslParser::entryToCslItem()` now reads hyphenated field names such as `short-title`, `title-addon`, `book-title`, `book-title-addon`, `main-title`, `volume-title`, `part-title`, `issue-title`, `event-title`, and `event-type`.
- Crossref container title inheritance now recognizes hyphenated parent title, subtitle, and title-addon aliases before mapping them into child container metadata.
- Focused coverage exercises raw CSL item fields, normalized `CitationCslProcessor` fields, CSL style text variables, and WordPress bibliography output.

## Verification

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - 1 test file, 4397 assertions, 0 failures after rebasing onto current `origin/main`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 60986 assertions, 0 failures after rebasing onto current `origin/main`.

## Accounting

- `phpPass` increments from 2995 to 2996.
- `phpFail` remains 0.
- The slice stays native PHP and does not invoke Pandoc, citeproc, BibTeX, Biber, bibliography managers, office suites, zip/unzip, browser renderers, online services, or live provider tests.
