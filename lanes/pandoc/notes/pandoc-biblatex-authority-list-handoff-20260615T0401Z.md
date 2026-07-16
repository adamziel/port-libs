# Pandoc BibLaTeX Authority List Handoff

## Scope

Implemented one bounded CSL/BibLaTeX citation and bibliography support case under
`lanes/pandoc`: multi-value BibLaTeX authority sources now survive as structured
CSL authority-list metadata instead of flattening into a single authority name.

## Behavior

- `BibtexCslParser` now treats `authority`, `court`, `institution`,
  `organization`, and issuing-authority aliases as authority sources that can
  contain BibLaTeX literal lists separated by `and`.
- Multi-value authority sources populate `authority-list` while retaining the
  scalar display value in `authority`.
- `CitationCslProcessor` now prefers structured authority-list aliases when
  normalizing authority names, so CSL `<names variable="authority"/>`,
  `<text variable="authority-list"/>`, and WordPress bibliography handoff see the
  same separated authority values.

## Accounting

- `phpPass`: `3654 -> 3655`
- `phpFail`: remains `0`
- `UPSTREAM_TEST_MANIFEST.json` `upstream.mapped`: `3691 -> 3692`
- Added `mappedBibtexCslAuthorityListCases = 1`
- Added `bibtexCslAuthorityListAssertions = 16`

## Verification

- `php -l lanes/pandoc/src/BibtexCslParser.php`
- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed 1 file, 5906 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed 46 files, 86193
  assertions, 0 failures.
- JSON validation passed for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check` passed for the touched lane files.
- Conflict-marker scan found no matches.

No Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.
