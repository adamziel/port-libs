# ODF/ODT Package Part Raw Extensions

## Slice

ODF/ODT package ingestion now carries metadata-only raw package-part extension rollups through both compact `OpenDocumentPackage` summaries and rich `OdfReader` package provenance.

## Coverage

- Preserves raw extension casing buckets separately from normalized extension buckets.
- Counts extensionless package parts, uppercase raw extensions, and normalized raw extensions.
- Carries entry-name maps, normalized extension counts, role and byte-exposure buckets, manifest media buckets, and largest-part metadata without exposing package bytes.
- Mirrors the rollups through package identity payloads so raw extension casing contributes to deterministic package identity review.

## Validation

- `php -l` for `OdfReader.php`, `OpenDocumentPackage.php`, and `OdfPackagePartRawExtensionInventoryTest.php`
- `php tools/run-tests.php` for the focused raw-extension fixture plus neighboring ODF package extension, area, basename, directory base-name, ZIP name policy, package identity, compact package, and rich reader tests
