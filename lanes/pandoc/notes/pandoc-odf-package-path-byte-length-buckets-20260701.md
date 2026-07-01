# ODF package path byte-length buckets

2026-07-01

This slice adds metadata-only ODF/ODT package path byte-length provenance to both package handoff paths:

- `OdfReader` rich package provenance and `packageIdentity`
- `OpenDocumentPackage` compact `packageInventory` and `packageIdentity`

Each package entry now records `packagePathByteLength`, `packagePathByteLengthBucket`, `packagePathByteLengthBucketMin`, and `packagePathByteLengthBucketMax`. The package-level summaries group ZIP entry names into these buckets:

- `up-to-8-bytes`
- `9-to-16-bytes`
- `17-to-32-bytes`
- `33-to-64-bytes`
- `over-64-bytes`

The rollup is intentionally metadata-only. It does not shell out to Pandoc, office suites, or external validators, and it does not expose package part bytes beyond existing ODF byte-exposure policy fields.

Focused coverage lives in `lanes/pandoc/tests/OdfPackagePathByteLengthBucketsTest.php`, which exercises all bucket ranges through both compact and rich ODT ingestion.
