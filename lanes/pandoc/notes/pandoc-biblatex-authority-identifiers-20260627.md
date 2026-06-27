# Pandoc BibLaTeX Authority Identifiers

Slice: `plib-rru52` Citation/CSL legacy BibLaTeX authority identifier handoff.

This slice extends the legacy native PHP `BibtexCslProcessor` path so BibLaTeX
authority identifiers are preserved as metadata-only CSL fields:

- `orcid`, `orcidid`, `orcid-id` -> `ORCID`
- `isni` -> `ISNI`
- `viaf` -> `VIAF`
- `ror` -> `ROR`
- `wikidata`, `wikidataid`, `wikidata-id`, `wd` -> `Wikidata`

The focused coverage verifies raw BibLaTeX retention, legacy bibliography text,
CSL style rendering via `authority-identifiers`, `citationHandoff`, and
WordPress bibliography output. Identifier targets are not looked up, read, or
fetched.

Validation:

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  - `1 selected file, 554 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`

Parity accounting:

- `lane-status.json` `phpPass`: `446 -> 447`
- `UPSTREAM_TEST_MANIFEST.json` mapped behavior checks: `2300 -> 2301`
- Added `legacyBiblatexAuthorityIdentifierCases` and
  `mappedLegacyBiblatexAuthorityIdentifierCases`.

No Pandoc, citeproc, BibTeX, Biber, bibliography manager, browser renderer,
identifier resolver, external validator, online service, live provider test, or
live-service provider test was invoked.
