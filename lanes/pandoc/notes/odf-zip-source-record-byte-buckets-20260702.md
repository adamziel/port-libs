# ODF ZIP source-record byte buckets

The ODF/ODT package inventory now groups ZIP source-record provenance by total
source-record byte size. Compact `OpenDocumentPackage` summaries and rich
`OdfReader` package provenance both expose the same bucket order, counts, byte
totals, entry names, and per-bucket metadata for local/central record review
without exposing package bytes.
