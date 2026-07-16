# Pandoc CSL Collection Title Aliases Current Base 20260611T162546Z

## Scope

- Bead: plib-z2qki, Pandoc citation/bibliography CSL core blocker slice.
- Current base: 4c7bc388.
- Change: BibTeX/BibLaTeX extraction now maps `collection-title` and
  `collectiontitle` aliases into CSL `collection-title` metadata.
- Handoff behavior: the aliases preserve raw BibTeX provenance and normalize
  into `collectionTitle` alongside existing `collectionNumber` and
  `collectionTitleShort` rendering paths for citations, bibliographies, and
  WordPress blocks.

## Verification

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed 1 file / 4617 assertions / 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed 44 files / 63906
  assertions / 0 failures.

No Pandoc binary, citeproc, bibliography manager, Cabal/Haskell runner, office
suite, zip/unzip, browser renderer, external validator, online service, live
provider test, or live-service provider test was executed.
