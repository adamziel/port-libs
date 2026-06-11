# Pandoc CSL Series Creator Aliases Current Base 20260611T165316Z

## Scope

- Bead: plib-ia2m1, Pandoc citation/bibliography CSL core blocker slice.
- Current base: 6995e705a.
- Change: BibTeX/BibLaTeX extraction now maps `seriescreator` and
  `series-creator` name-field aliases into CSL `series-creator` metadata.
- Handoff behavior: the aliases preserve raw BibTeX provenance, keep `+an`
  name annotations attached to series creator names, avoid leaking those
  annotations into generic field metadata, and render through citation,
  bibliography, and WordPress block output.

## Verification

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed 1 file / 4642 assertions / 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed 44 files / 64021
  assertions / 0 failures.

No Pandoc binary, citeproc, BibTeX, Biber, bibliography manager, Cabal/Haskell
runner, office suite, zip/unzip, browser renderer, external validator, online
service, live provider test, or live-service provider test was executed.
