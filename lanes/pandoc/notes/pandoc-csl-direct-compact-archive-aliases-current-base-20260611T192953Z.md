# Pandoc CSL Direct Compact Archive Alias Slice

## Scope

Base: current main `6f71ba75a`.

This slice closes a bounded direct CSL JSON ingestion gap for compact archive and
eprint field spellings. `CitationCslProcessor` now normalizes:

- `archiveprefix`, `archive-prefix`, `eprinttype`, and `eprint-type` as
  `archive`
- `archivecollection` as `archiveCollection`
- `archiveplace`, `eprintclass`, and `eprint-class` as `archivePlace`
- `archivelocation` and `eprint` as `archiveLocation`
- `archivesummary` and `eprintsummary` as `archiveSummary`

The new focused fixture keeps this separate from BibTeX parsing. It uses direct
item arrays with compact eprint keys, compact archive keys, and compact summary
keys, then verifies normalized item metadata, default review bibliography text,
CSL variable rendering for compact archive spellings, and WordPress bibliography
handoff.

## Non-overlap

This does not change BibTeX/BibLaTeX parsing, existing eprint summary derivation,
archive collection variable rendering, DOI/registry identifiers, package readers,
or any external Pandoc/citeproc/BibTeX/Biber behavior. The slice only widens the
direct CSL JSON item ingestion aliases that feed already-supported CSL archive
metadata and renderer variables.

## Verification

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed: 1 test file, 4777 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 test files, 65595 assertions, 0 failures

No Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were run.
