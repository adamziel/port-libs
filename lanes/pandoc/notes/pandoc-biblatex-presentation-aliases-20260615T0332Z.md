# Pandoc CSL/BibLaTeX Presentation Entry Aliases

Slice: `pandoc-biblatex-presentation-aliases`

Date: 2026-06-15

Scope:

- Map bounded explicit BibLaTeX presentation entry aliases `@talk`, `@lecture`, and `@presentation` to CSL `speech`.
- Keep `venue` owned by `event-place` for those aliases so presentation venues do not duplicate into `publisher-place`.
- Cover raw BibTeX item extraction, normalized `CitationCslProcessor` items, CSL `type="speech"` conditionals, bibliography rendering, and WordPress block output.

Boundary:

- Native PHP only.
- No Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser renderers, online services, live providers, or external validators.

Accounting:

- `phpPass`: 3640 -> 3641
- `phpFail`: 0
- `mappedBiblatexPresentationAliasCases`: 1
- `biblatexPresentationAliasAssertions`: 36

Verification:

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> 1 file, 5849 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 46 files, 85729 assertions, 0 failures
