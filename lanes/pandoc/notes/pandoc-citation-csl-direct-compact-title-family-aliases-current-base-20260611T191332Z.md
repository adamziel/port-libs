# Pandoc Citation CSL Direct Compact Title Family Aliases

Bead: `plib-hdzqa`
Base: `16f638244`

## Scope

This slice keeps direct CSL JSON ingestion aligned with the compact BibLaTeX
field aliases already accepted by the bounded parser.

`CitationCslProcessor` now normalizes compact direct-item aliases for the title
family and related publication detail variables:
- `maintitle`, `maintitleaddon`
- `collectiontitle`, `collectiontitleshort`, `collectionnumber`
- `numberofvolumes`, `numberofpages`, `chapternumber`

The normalized fields feed existing CSL style variables such as `main-title`,
`collection-title`, `number-of-pages`, and `chapter-number`, so citation,
bibliography, and WordPress handoff rendering paths stay unchanged.

## Verification

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed: 1 test file, 4749 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 test files, 65447 assertions, 0 failures.

## Accounting

- Adds 1 focused direct CSL JSON compact alias PASS case.
- Adds 18 focused assertions.

No Pandoc, citeproc, JSON filters, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.
