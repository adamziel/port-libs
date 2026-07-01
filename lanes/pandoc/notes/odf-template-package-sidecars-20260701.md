# ODF Template Package Sidecars

ODF package ingestion now treats ZIP entries under `Templates/` as `template-package` sidecars in both compact `OpenDocumentPackage` summaries and rich `OdfReader` provenance. Template package parts are metadata-only: their bytes are blocked with `template-package-bytes-blocked`, counted in `templatePackagePartCount`, excluded from media resource handoff, and carried into package identity records.

The coverage includes declared template entries, image-like template preview files, and undeclared Office template documents such as `.potx` files.
