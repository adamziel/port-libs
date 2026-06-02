# markerPDF Metadata Associated-File OutputIntent Encrypt XMP Current Base

Session: `port-dev-markerpdf-meta72-20260602T221239Z`

Micro-slice: `metadata-associated-file-outputintent-encrypt-xmp-currentbase`

Base accepted HEAD: `36d3abb94323edf47dc54936168141773ec380c2`

## Source Truth

Upstream `sddai/markerPDF` at the manifest-pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps visible Markdown extraction and metadata/review artifacts as separate conversion surfaces, with PDF text coming through pdftext/PDFium-style extraction boundaries rather than arbitrary fallback stream scraping.

PDF encryption source truth for this slice is the PDF 1.7 crypt-filter boundary: `/EncryptMetadata false` can leave the document metadata stream available for review, while encrypted strings and streams still require decryption. The encryption dictionary `/EFF` crypt-filter selection controls embedded-file streams independently of default stream/string filters. Reference pages used: `https://www.verypdf.com/document/pdf-format-reference/txtidx0134.htm` and `https://www.verypdf.com/document/pdf-format-reference/txtidx0131.htm`.

## Red First

Command:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataAssociatedFileOutputIntentEncryptXmpCurrentBaseTest.php
```

Accepted-base result after adding the fixture and before the source fix:

```text
FAIL preserves unencrypted root XMP while blocking encrypted associated FileSpec metadata and OutputIntent rows
Expected: 'suppressed_encrypted_associated_file_metadata'
Actual: NULL

1 test files, 9 assertions, 1 failures
```

## Implementation

`PdfMetadataExtractor` now:

- records `associated_files_policy=suppressed_encrypted_associated_file_metadata` when an encrypted catalog exposes `/AF`, `/Collection`, or `/Names /EmbeddedFiles`;
- preserves root catalog `/Metadata` XMP only when the existing `/EncryptMetadata false` policy allows it;
- suppresses encrypted root `/OutputIntents` as before;
- redacts encrypted associated FileSpec strings (`/F`, `/UF`, `/Desc`, `/Lang`), embedded payload hashes, checksum strings, attachment-local `/Metadata` XMP summaries, attachment-local `/OutputIntents`, PieceInfo private metadata, and related-file stream hashes;
- preserves safe review-only object references, `/AFRelationship` names, and an explicit row-level encrypted associated-file policy without decrypting or exposing raw encrypted bytes.

The WordPress smoke emits a Gutenberg paragraph plus review metadata showing that root XMP is retained, text import is blocked, and encrypted attachment filename/payload/XMP/OutputIntent data is omitted.

## Verification

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataAssociatedFileOutputIntentEncryptXmpCurrentBaseTest.php
```

Passed: `1 test files, 53 assertions, 0 failures`.

```sh
php lanes/markerpdf/examples/wordpress-pdf-encrypted-associated-file-metadata-boundary-currentbase.php
```

Passed: emitted `associated_files_policy="suppressed_encrypted_associated_file_metadata"`, blocked text import, preserved title `Encrypted AF Root XMP Title`, and reported attachment payload/XMP/OutputIntent omission.

Additional focused verification:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfMetadataAssociatedFileOutputIntentEncryptXmpCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataExtractorTest.php lanes/markerpdf/tests/PdfMetadataPdfaCatalogAssociatedOutlineCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataPortfolioPieceInfoOutputIntentCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataXmpOutputIntentNameTreeCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataPdfaAssociatedNameTreeCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataAssociatedFileSchemaCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataNameTreePieceInfoOutputIntentCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataPieceInfoAssociatedXmpCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataPortfolioAssociatedPieceInfoChecksumCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentFileSpecAssociatedAFRelationshipChecksumCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefTrailerEncryptPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfParserTrailerEncryptIdPrecedenceCurrentBaseTest.php lanes/markerpdf/tests/PdfSecurityPreflightTest.php lanes/markerpdf/tests/PdfSecurityPublicKeyPermissionCurrentBaseTest.php
```

Passed: `15 test files, 1880 assertions, 0 failures`.

```sh
php -l lanes/markerpdf/src/PdfMetadataExtractor.php
php -l lanes/markerpdf/tests/PdfMetadataAssociatedFileOutputIntentEncryptXmpCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-encrypted-associated-file-metadata-boundary-currentbase.php
```

Passed: no syntax errors.

```sh
php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file, " OK\n"; }'
```

Passed: both lane JSON files decoded.

```sh
git diff --check -- lanes/markerpdf
```

Passed: no whitespace errors.

## Status Delta

- Behavior tests move `895 -> 896`.
- WordPress scenarios move `895 -> 896`.
- Mapped markerPDF semantics move `631 -> 632 / 78`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, encryption dictionary review, XMP parser, OutputIntent parser, FileSpec review metadata, embedded-file review rows, stream-filter boundary handling, and security preflight. Full upstream runner parity remains dependency-gated on pdftext, pypdfium2, Surya, tabled-pdf, Texify, Torch/model downloads, Streamlit/FastAPI workflows, benchmark tooling, and live Python/model workers.

## Non-Overlap

This does not repeat direct root XMP extraction, root PDF/A OutputIntent parsing, encrypted metadata source priority for document XMP/Info/OutputIntent, catalog PDF/A associated-file summaries, FileSpec checksum/provenance review, PieceInfo private-stream checksum review, public-key recipient permission review, xref trailer Encrypt precedence, or visible encrypted text blocking. The bounded new behavior is specifically fail-closed encrypted catalog associated-file metadata when root XMP is explicitly unencrypted but FileSpec strings, embedded payload streams, attachment-local XMP, and attachment-local OutputIntent rows remain encrypted.
