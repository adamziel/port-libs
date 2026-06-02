# metadata-portfolio-pieceinfo-outputintent-currentbase

Session: `port-dev-markerpdf-meta43pdf-20260602T1940Z`
Base accepted HEAD: `ece425cf9d145b9f14931723a9bf8c44e0763cb3`

## Source truth

- Upstream Marker treats conversion output as document text/blocks plus a separate metadata surface. Its README documents PDF conversion to Markdown/JSON/HTML and the rendered result properties including `markdown`, `metadata`, and `images`; JSON pages carry block metadata separately from page HTML/text: https://github.com/datalab-to/marker#output-formats
- PDF portfolio FileSpec `/Metadata`, `/PieceInfo`, `/CI`, `/AFRelationship`, and attachment-local `/OutputIntents` are provenance/review metadata for WordPress import. They should not become document-root PDF/A metadata and should not leak XMP, ICC profile bytes, PieceInfo private stream bytes, or embedded payload bytes into visible page text.

## Non-overlap

This does not repeat the accepted catalog-associated FileSpec provenance, portfolio collection schema field values, raw FileSpec PieceInfo review, raw FileSpec OutputIntent review, document-level XMP/PDF-A extraction, or visible page text filtering clusters. The new behavior is a derived `provenance_review` row on portfolio EmbeddedFiles FileSpec results that composes:

- associated-file relationship role;
- embedded payload checksum/size provenance without content bytes;
- portfolio collection schema, sort, and `/CI` item values;
- FileSpec `/Metadata` stream hash;
- FileSpec `/PieceInfo` application/private dictionaries, private metadata stream hashes, private stream hashes, and nested OutputIntent summaries;
- FileSpec-local `/OutputIntents` profile hashes and identifiers.

The extractor only emits this row when portfolio/FileSpec metadata is present, so ordinary attachments with no portfolio metadata do not get a new broad review field.

## Red-first evidence

Before the implementation, the focused test fixture had no derived portfolio FileSpec provenance row:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataPortfolioPieceInfoOutputIntentCurrentBaseTest.php
Expected: 'portfolio_filespec_provenance'
Actual: NULL
1 test files, 10 assertions, 1 failures
```

## Implementation

- Added `PdfEmbeddedFileExtractor::portfolioFileSpecProvenanceReview()` and local helpers for metadata stream, PieceInfo private dictionary/stream, OutputIntent, profile, collection, field, and payload summaries.
- Kept attachment-local metadata review-only by hashing decoded XMP, ICC profile, and PieceInfo private streams and excluding raw metadata payload content from the review output.
- Added the WordPress smoke `wordpress-pdf-metadata-portfolio-pieceinfo-outputintent-currentbase.php`, which emits visible page text plus review comments and rejects metadata promotion/leakage.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataPortfolioPieceInfoOutputIntentCurrentBaseTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
4 test files, 1825 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-metadata-portfolio-pieceinfo-outputintent-currentbase.php
passed; emitted markerpdf-metadata-portfolio-pieceinfo-outputintent-currentbase and markerpdf:portfolio-filespec-provenance comments

php -l lanes/markerpdf/src/PdfEmbeddedFileExtractor.php
No syntax errors detected

php -l lanes/markerpdf/tests/PdfMetadataPortfolioPieceInfoOutputIntentCurrentBaseTest.php
No syntax errors detected

php -l lanes/markerpdf/examples/wordpress-pdf-metadata-portfolio-pieceinfo-outputintent-currentbase.php
No syntax errors detected

git diff --check -- lanes/markerpdf
passed
```

Root harness: not run - isolated micro-slice.

## Status delta

- Behavior tests: `734 -> 735` pass / `0` fail.
- WordPress scenarios: `734 -> 735`.
- Focused assertion evidence for this handoff: `49` assertions in the new test, and `1825` assertions across the adjacent focused metadata/embedded-file/text family.

## Dependency closure

No new support component is needed. This reuses the lane-native PDF object scanner, dictionary/array value resolver, stream decoder, embedded-file traversal, portfolio collection parser, PieceInfo review parsing, OutputIntent profile hashing, and text extraction leak guards. Full upstream runner parity remains gated by the existing heavy Python/model/PDF runtime dependencies described in `lane-status.json`.

## Follow-up

Next metadata work should stay non-overlapping: portfolio name-tree edge recovery, additional associated-file relationship variants, or catalog/page metadata boundaries that do not promote attachment-local review data to document-root metadata.
