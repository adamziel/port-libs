# ODF content package reference role provenance

## Scope

This slice extends native PHP ODF/ODT package ingestion metadata for
`content.xml` package references. `OdfReader` now reports embedded-object role
provenance and media-resource family/precedence details on the
`contentPackageReferences` review rows without exposing package bytes.

## Coverage

- `draw:image` references carry declared/package-path media family information
  and remain byte-exposable media resources when policy allows.
- Undeclared image package parts still resolve through package-path media
  family detection so review rows can distinguish media resources from generic
  package references.
- MathML `draw:object` contained parts and `draw:object-ole` roots carry
  embedded-object package provenance, root/contained flags, root part/path, and
  object type while keeping embedded package bytes blocked.
- Report-level rollups now count embedded-object references, media-resource
  references, package-role precedence hits, and effective media families.

## Accounting

The upstream manifest maps one additional bounded ODF/ODT package-ingestion
case:

- `mappedOdfContentPackageReferenceRoleProvenanceCases`: 1
- `odfContentPackageReferenceRoleProvenanceAssertions`: 44

The mapped denominator increases from 2317 to 2318.

## Validation

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderContentPackageReferenceRoleProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderContentPackageReferenceRoleProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`

No Pandoc, Haskell/Cabal, office suite, TeX engine, browser engine, zip/unzip,
external validator, or external package tool was invoked for this slice.
