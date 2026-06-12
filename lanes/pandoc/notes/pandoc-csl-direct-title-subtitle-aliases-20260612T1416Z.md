## Pandoc CSL Direct Title Subtitle Aliases

Implemented a bounded native PHP Citation/CSL slice for direct CSL JSON title-family alias composition.

- Composes direct `mainTitle`/`mainSubtitle`, compact `maintitle`/`mainsubtitle`, and hyphenated `main-title`/`main-subtitle` aliases into canonical `mainTitle` metadata.
- Composes direct `volumeTitle`/`volumeSubtitle`, compact `volumetitle`/`volumesubtitle`, and hyphenated `volume-title`/`volume-subtitle` aliases into canonical `volumeTitle` metadata.
- Composes direct `partTitle`/`partSubtitle`, compact `parttitle`/`partsubtitle`, and hyphenated `part-title`/`part-subtitle` aliases into canonical `partTitle` metadata.
- Keeps raw subtitle provenance attached on the normalized item while rendering the composed variables through CSL citation clusters, bibliography entries, and WordPress review output.

Verification:

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> `1 test files, 5221 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests` -> `44 test files, 71716 assertions, 0 failures`

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner, external bibliography manager, browser renderer, external validator, online service, live provider test, or live-service provider test is required for this bounded native normalization path.
