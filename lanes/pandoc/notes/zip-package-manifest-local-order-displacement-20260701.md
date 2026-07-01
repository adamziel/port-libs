# ZIP Package Manifest Local Order Displacement

`ZipPackage::packageManifestPreflight()` now records central-directory versus local-header order displacement for shared ZIP/OPC package handoff:

- per-entry local-header order delta, displacement, relation, and cross-order names;
- package-level relation counts, match count, displacement count, maximum displacement, and displaced-entry summaries;
- deterministic manifest hash coverage for the new displacement payload.

This stays inside the native PHP bounded ZIP reader and preserves direct-format parity accounting for DOCX/EPUB/ODF package ingestion without shelling out to external ZIP tooling.
