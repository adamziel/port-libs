# ZIP Package Source Record Byte Buckets

`ZipPackage::packageManifestPreflight()` now carries source-record byte bucket metadata for shared ZIP/OPC package review.

- Each manifest entry records a `sourceRecordByteBucket` derived from its local record plus central-directory record bytes.
- The package manifest exposes `sourceRecordByteBuckets`, `sourceRecordByteBucketSummaryCount`, and `sourceRecordByteBucketSummaries`.
- Bucket summaries preserve entry counts, file/directory counts, source/local/central byte totals, compressed and uncompressed totals, data descriptor totals, directory roots, extension keys, compression methods, entry names, and largest source-record entry names.

This keeps package handoff size provenance visible before downstream DOCX, ODF/ODT, or EPUB readers select individual entries.
