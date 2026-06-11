# Pandoc CSL count metadata aliases current-base slice

- Bead: `plib-ufskx`
- Base: `origin/main` `4c7bc38809bb3ea7c285607b4bd8e5006f64a9b8`
- Scope: CSL/BibLaTeX count metadata aliases for `number-of-volumes`, `chapter-number`, and `number-of-pages`.
- Implementation: `CitationCslProcessor` now accepts compact/camel-case direct CSL aliases, and `BibtexCslParser` maps BibLaTeX hyphenated and compact count fields into canonical CSL variables.
- Verification: `php -l` passed for touched PHP files; focused `CitationCslProcessorTest.php` passed 1 test file, 4624 assertions, 0 failures; full `php tools/run-tests.php lanes/pandoc/tests` passed 44 test files, 63913 assertions, 0 failures.
- External tools intentionally not invoked: Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser renderers, external validators, online services, live provider tests, and live-service provider tests.
