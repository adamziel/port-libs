# Pandoc BibLaTeX Series Creator Alias Slice

Bead: `plib-3tfr3`
Base: `origin/main` `2cea4fa785b868a6fa27c96e3ade52a6d7295957`

This slice maps BibLaTeX `seriescreator` and `series-creator` creator fields into CSL `series-creator` name metadata in both BibTeX import paths.

Coverage added:

- Raw BibTeX extraction keeps `series-creator` names plus raw source fields.
- Normalized CSL items expose `seriesCreators`.
- Collection title and collection number stay aligned with the imported series creator.
- CSL citation and bibliography layouts render `series-creator`.
- WordPress bibliography handoff preserves the rendered series creator and collection provenance.

Verification on the rebased branch:

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`: 1 test file, 4692 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`: 44 test files, 64417 assertions, 0 failures

No Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
