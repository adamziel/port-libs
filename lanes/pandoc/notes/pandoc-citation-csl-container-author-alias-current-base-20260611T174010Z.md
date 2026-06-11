# Pandoc CSL Container-Author Alias Provenance

Slice: `plib-tp70h`, 2026-06-11T174010Z.

Base: current `origin/main` at `2cea4fa78`.

Implemented native PHP BibLaTeX/CSL handoff coverage for hyphenated and compact container-author aliases:

- `container-author`
- `book-author`
- `containerauthor`

The parser now maps these into CSL `container-author` creator metadata through the existing bounded name parser. Matching `+an` name annotations stay attached to the creator list instead of being demoted into generic BibLaTeX field annotation metadata.

Verification:

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`: 1 test file, 4685 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`: 44 test files, 64410 assertions, 0 failures

No Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
