# markerPDF xref object-stream attachment header comment current-base slice

Session: `port-dev-markerpdf-object-xref-20260605T082954Z`
Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260605T082954Z`
Base accepted HEAD: `cdefa050c55f815f9b519ea513ff85a5ebf70a83`

## Source Truth

- Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF syntax recovery through pdftext/PDFium before model stages. The no-GPU PHP lane owns the native parser boundary for xref streams, object streams, and FileSpec attachment preflight.
- PDF comments are lexical whitespace. `/Type /ObjStm` header pairs must therefore be parsed as object-number/offset tokens while ignoring `%` comment bytes, and xref-stream type-2 rows with explicit member indexes must stay aligned to the original object-stream header index.
- WordPress attachment import must summarize current compressed FileSpec dictionaries without executing actions, launching Python/models, or exposing embedded payload bytes.

## Behavior

`PdfAttachmentExtractor` now parses object-stream header object-number/offset pairs with token-aware unsigned integer reads instead of a raw digit regex. The parser:

- treats `%` comments as whitespace inside the object-stream header;
- preserves the original header row index for explicit xref-stream type-2 member indexes;
- skips zero object-number rows without shifting later indexes;
- fails closed when the declared `/N` pairs cannot be read cleanly or non-comment trailing header bytes remain.

The focused fixture places fake numeric object-stream header tokens inside a `%` comment before the current compressed FileSpec member. Before the fix, the lightweight attachment preflight lost the current compressed FileSpec and returned `attachment_count=0`. After the fix it reports `comment-header-current.xml`, checksum match state, catalog `/AF` mirror metadata, and excludes the stale direct FileSpec, the comment decoy FileSpec, and both payload byte strings.

## Evidence

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamAttachmentHeaderCommentCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL keeps explicit attachment object-stream indexes aligned across commented header rows
Expected: 1
Actual: 0
1 test files, 1 assertions, 1 failures
```

After patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamAttachmentHeaderCommentCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps explicit attachment object-stream indexes aligned across commented header rows
1 test files, 36 assertions, 0 failures
```

Attachment/object-stream family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamAttachmentHeaderCommentCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamNestedDictionaryOffsetCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 535 assertions, 0 failures
```

Syntax:

```text
php -l lanes/markerpdf/src/PdfAttachmentExtractor.php
php -l lanes/markerpdf/tests/PdfXrefObjectStreamAttachmentHeaderCommentCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-attachment-header-comment-currentbase.php
No syntax errors detected in all changed PHP files.
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-attachment-header-comment-currentbase.php
```

The smoke exits `0` and reports `commented_header_filespec_selected=true`, `explicit_member_index_preserved=true`, `stale_direct_filespec_excluded=true`, `comment_decoy_filespec_excluded=true`, `payload_bytes_omitted=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted object-stream text extraction, type-2 member-index repair, zero-width index recovery, skipped zero object-number text parsing, object-stream member offset rejection, compressed FileSpec attachment extraction, nested-dictionary attachment offset rejection, `/Prev` object-stream metadata repair, xref-stream `/W`/`/Index` indirect operands, or inline image/filter work. The bounded behavior is only the lightweight attachment preflight object-stream header parser when comments contain numeric decoys before an explicit type-2 compressed FileSpec member.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, xref-stream reader, object-stream decoder, stream filter decoder, FileSpec attachment parser, checksum review, and WordPress smoke path. OCR, Surya/Texify/Torch, PDFium rendering, model workers, and external PDF tools remain intentionally out of scope for this markerPDF no-GPU slice.

## Next

Continue with non-overlapping native searchable-PDF parser behavior around fonts/CMaps, xref repair, metadata, annotations/forms, image/filter metadata, page geometry, and supplied-boundary table/equation handoffs.
