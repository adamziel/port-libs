# markerpdf-stream-filter-stack-boundary-current-base-20260608T152458Z

Accepted base: `090466a02f0c71eccc4b93b9164c9203b62ed93c`

Scope: native markerPDF searchable-PDF stream-filter stack boundary behavior. No OCR, Surya, Texify, Torch, GPU/model workers, raster rendering, external PDF tools, PDF action execution, or live services were run.

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through the parser/pdftext boundary before OCR/layout/model stages. At this native PHP boundary, declared stream filters and filter parameters must be honored before bytes become page text.

PDF `/Crypt` in a stream `/Filter` stack is byte-preserving only for the identity crypt filter. When `/DecodeParms << /Name ... >>` points at an indirect object, that helper has to be the selected standalone name; an object body such as `/Identity /PrivateCF` is not a valid identity crypt-filter name operand and must not unlock downstream `/FlateDecode` text.

## Behavior

`PdfTextExtractor` now resolves `/Crypt` DecodeParms `/Name` values through a strict indirect-name path:

- direct `/Name /Identity` remains accepted;
- indirect `/Name 10 0 R` remains accepted when object `10` is a standalone `/Identity` name, including trailing PDF comments;
- malformed indirect helpers with extra top-level operands, such as `/Identity /PrivateCF`, fail closed before Identity pass-through decoding.

## Red-First Probe

Before the source patch, this probe emitted both `Malformed Crypt Name Helper Leak` and `Visible After Crypt Name Helper`:

```text
php -r 'require "tools/bootstrap.php"; $content="BT /F1 12 Tf 72 720 Td (Malformed Crypt Name Helper Leak) Tj ET"; $z=gzcompress($content); $vis="BT /F1 12 Tf 72 700 Td (Visible After Crypt Name Helper) Tj ET"; $pdf="%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R] >>\nendobj\n4 0 obj\n<< /Filter [ /Crypt /FlateDecode ] /DecodeParms [ << /Name 10 0 R >> null ] /Length ".strlen($z)." >>\nstream\n$z\nendstream\nendobj\n5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n6 0 obj\n<< /Length ".strlen($vis)." >>\nstream\n$vis\nendstream\nendobj\n10 0 obj\n/Identity /PrivateCF\nendobj\n%%EOF"; echo (new PortLibs\MarkerPDF\PdfTextExtractor())->extractPlainText($pdf), "\n";'
```

After the source patch, the same probe emits only `Visible After Crypt Name Helper`.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterCryptNameHelperBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects Crypt DecodeParms indirect Name helpers with trailing operands before page text import
PASS accepts standalone indirect Identity Crypt Name helpers with trailing comments

1 test files, 20 assertions, 0 failures
```

Adjacent stream-filter stack and attachment/DecodeParms-name checks:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterDecodeParmsNameBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 3 selected test files (root lock skipped)
...
3 test files, 839 assertions, 0 failures
```

DCT/native-prefix DecodeParms checks:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodePreviewPrefixBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeRunLengthPrefixBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeDecodeParmsOperandBoundaryCurrentBaseTest.php
Focused test run: 3 selected test files (root lock skipped)
...
3 test files, 136 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-stream-filter-crypt-name-helper-currentbase.php
{
    "scenario": "wordpress_pdf_stream_filter_crypt_name_helper_currentbase",
    "malformed_helper_rejected": true,
    "malformed_visible_text_preserved": true,
    "valid_identity_helper_imported": true,
    "valid_visible_text_preserved": true,
    "private_crypt_name_excluded": true,
    "executes_python_or_models": false,
    "executes_external_pdf_tools": false,
    "self_test_passed": true
}
```

## Non-Overlap

This does not repeat accepted ASCII85/ASCIIHex/RunLength/LZW/Flate success-path decoding, stream-filter missing-Length recovery, concatenated Flate metadata boundaries, generic DecodeParms integer fail-closed handling, extra/nested DecodeParms array rejection, duplicate stream dictionary keys, unsupported `/Crypt` filter rejection, direct `/Crypt /Identity` pass-through, DCT/CCITT/JPX/JBIG2 image-filter review-only exclusions, object-stream/xref stream filter repair, PageLabels, annotations, forms, or OCR/model behavior.

The new boundary is specifically malformed indirect `/DecodeParms /Name` helper ownership for `/Crypt` stream-filter stack stages.

## Dependency Closure

No new support component is needed. The implementation reuses the native PHP PDF object scanner, exact-generation object body resolver, stream dictionary parser, filter stack resolver, `/Crypt` identity pass-through guard, Flate decoder, and content-token parser. Full upstream pdftext/PDFium, OCR, Surya/Torch, tabled-pdf, Texify, and runtime server/model parity remain intentionally out of scope under the current no-GPU markerPDF direction.

## Next Task

Continue with non-overlapping native searchable-PDF parser behavior around xref repair, object-stream filter metadata, fonts/CMaps, page geometry, annotations/forms/security preflight, image/filter metadata, or supplied-boundary table/equation handoffs.
