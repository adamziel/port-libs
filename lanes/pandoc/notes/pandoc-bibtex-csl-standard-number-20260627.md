# Pandoc BibTeX/CSL Standard Number Handoff

Slice: `plib-882e8`, Citation/CSL core blocker.

Implemented a bounded legacy BibLaTeX handoff for standard entry document numbers:

- `BibtexCslProcessor` now maps `@standard` entries to CSL `type = standard`.
- Non-journal BibLaTeX `number` fields now remain CSL `number` metadata instead of becoming `issue`.
- Journal-like entries, including legacy `@review`, still treat `number` as the journal issue for existing bibliography output.
- Direct bibliography text, citation handoff, styled CSL rendering, and WordPress bibliography output keep the standard number visible without external citeproc.

Validation:

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
- Results: BibtexCslProcessorTest passed with 699 assertions; CitationCslProcessorTest passed with 6018 assertions.

No Pandoc, citeproc, BibTeX, Biber, office suite, TeX/PDF engine, browser, external validator, network lookup, or live provider was invoked.
