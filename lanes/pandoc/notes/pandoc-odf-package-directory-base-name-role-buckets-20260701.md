# ODF Package Directory Base-Name Role Buckets

`plib-zpzxs` adds deterministic directory base-name role and byte exposure policy lookup maps for ODF package ingestion.

The new maps are carried through compact package inventory, compact package identity, rich package provenance, rich package identity, and document package identity. They preserve existing byte exposure rules while making package review able to resolve entries by directory base name plus role or policy without scanning expanded summary rows.

Focused coverage:

- `lanes/pandoc/tests/OdfPackageDirectoryBaseNameRoleBucketsTest.php`
