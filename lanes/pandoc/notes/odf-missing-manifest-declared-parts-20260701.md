# ODF Missing Manifest-Declared Parts - 2026-07-01

- Added `packageProvenance.missingManifestDeclared*` summaries for ODF file-entry parts that are declared in `META-INF/manifest.xml` but absent from the ZIP payload.
- The summary groups absent declarations by package role, byte exposure policy, and media type base without changing physical package-entry `roleCounts`.
- Covered with an in-memory ODT fixture for missing image, script, and font declarations. No external validators or office/Pandoc subprocesses are used.
