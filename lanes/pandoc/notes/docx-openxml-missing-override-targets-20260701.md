# DOCX Missing Override Targets - 2026-07-01

- Added `contentTypesPart.missingOverrideTarget*` and matching `packageProvenance.summary.contentTypeMissingOverrideTarget*` fields for OPC override declarations whose package parts are absent.
- The summary buckets declared-but-absent override targets by inferred package role, content type base, content type parameters, directory/top-level path, and extension without altering physical `parts` or `roleCounts`.
- Covered with an in-memory DOCX fixture. No external validators, office/Pandoc subprocesses, or ZIP shell tools are used.
