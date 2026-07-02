# ODF manifest path basename buckets

Slice: `plib-aj7ac` ODF/ODT package ingestion.

## Scope

- Added `basenameStem` and `caseFoldBasenameStem` to ODF manifest path shapes.
- Added the same basename-stem fields to decoded ODF package path shapes so URI-encoded manifest paths can be compared with decoded package paths.
- Added compact `OpenDocumentPackage::summarize()['manifestReview']` rollups:
  - `manifestPathBaseNameCounts`
  - `manifestPathBaseNameStemCounts`
- Mirrored those rollups through rich `OdfReader` package provenance, package identity, and document package identity.

## Fixture

`lanes/pandoc/tests/OdfManifestPathBasenameBucketParityTest.php` builds a bounded in-memory ODT package with:

- root manifest entry `/`
- XML document parts
- `Pictures/HERO.PNG?cache=1#cover`
- URI-encoded `Pictures/space%20hero.png` resolving to decoded package part `Pictures/space hero.png`
- nested `Configurations2/accelerator/current.xml`
- directory entry `Notes/`

## Verification

Focused run:

```text
php tools/run-tests.php lanes/pandoc/tests/OdfManifestPathBasenameBucketParityTest.php
```

Result: 1 test file, 22 assertions, 0 failures.

The fixture does not invoke upstream Pandoc, office tooling, external ZIP tools, browser tooling, TeX, Jupyter, or external validators, and it does not expose package payload bytes beyond bounded in-memory fixture parts.
