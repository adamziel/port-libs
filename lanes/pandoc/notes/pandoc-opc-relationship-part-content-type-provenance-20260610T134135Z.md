# OPC Relationship Part Content Type Provenance

Slice: `plib-ro4q` / Pandoc shared ZIP/OPC package core blocker.

## Scope

This slice covers native PHP OPC relationship-part package preflight.

`OpcRelationshipGraph::preflightRelationshipPartsInPackage()` now reports the
content-type resolution provenance for every discovered relationship part:
default extension, exact override, case-equivalent override, and the matching
override/default names. `relationshipPartLoadSummary()` also groups relationship
parts by content-type source before graph construction.

The focused fixture covers:

- root `.rels` loaded through the default `rels` content type;
- a document relationship part loaded through an exact override;
- a case-equivalent relationship part loaded through an override;
- an invalid relationship part override with `application/xml`.

No Pandoc, `zip`, `unzip`, office suite, Cabal/Haskell runner, browser renderer,
external validator, online service, live provider test, or live-service provider
test is used.

## Verification

- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - 1 file, 3765 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 files, 60222 assertions, 0 failures
