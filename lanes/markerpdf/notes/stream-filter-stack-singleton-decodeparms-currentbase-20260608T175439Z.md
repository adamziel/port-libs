# markerpdf-stream-filter-stack-boundary-current-base-20260608T175439Z

Accepted base: `196aeee97e13991dc56717436cd4ee56caa47808`

Scope: native markerPDF searchable-PDF stream-filter stack boundary behavior. No OCR, Surya, Texify, Torch, GPU/model workers, raster rendering, external PDF tools, PDF action execution, or live services were run.

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through the parser/pdftext boundary before OCR/layout/model stages. At this native PHP boundary, stream bytes must not become WordPress paragraphs until the declared filter stack and `/DecodeParms` operands are unambiguous.

PDF stream `/Filter` arrays require filter parameters to be aligned to filter slots, with explicit `null` entries when a filter stage has no parameters. A singleton non-null `/DecodeParms` dictionary is only unambiguous when exactly one real filter can consume parameters. For multi-filter stacks such as `/Filter [ /ASCII85Decode /FlateDecode ]`, `/DecodeParms << /Predictor 1 >>` is not safely aligned and must fail closed before text-token parsing.

## Behavior

`PdfTextExtractor` now counts singleton non-null `/DecodeParms` dictionaries as invalid when more than one non-null filter can carry parameters. This preserves accepted behavior for:

- `/Filter [ null /FlateDecode ] /DecodeParms << ... >>`, where only one real filter consumes the singleton dictionary;
- explicitly aligned multi-filter stacks such as `/DecodeParms [ null null ]`;
- compact arrays that intentionally omit null-filter placeholders while still aligning to real filters.

The red boundary now rejects the ambiguous stream while preserving the aligned stream that follows it.

## Red-First Probe

Before the source patch, this current-base probe returned both `Ambiguous Singleton DecodeParms Leak` and `Visible After Ambiguous DecodeParms`:

```text
php <<'PHP'
<?php
require 'tools/bootstrap.php';
use PortLibs\MarkerPDF\PdfTextExtractor;
$ascii85 = static function (string $bytes): string {
    $encoded = '';
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += 4) {
        $chunk = substr($bytes, $offset, 4);
        $chunkLength = strlen($chunk);
        if ($chunkLength < 4) $chunk = str_pad($chunk, 4, "\0");
        $value = unpack('N', $chunk)[1];
        if ($value === 0 && $chunkLength === 4) { $encoded .= 'z'; continue; }
        $chars = '';
        for ($i = 0; $i < 5; $i++) { $chars = chr(($value % 85) + 33) . $chars; $value = intdiv($value, 85); }
        $encoded .= substr($chars, 0, $chunkLength + 1);
    }
    return $encoded . '~>';
};
$content = 'BT /F1 12 Tf 72 720 Td (Ambiguous Singleton DecodeParms Leak) Tj ET';
$encoded = $ascii85(gzcompress($content));
$visible = 'BT /F1 12 Tf 72 700 Td (Visible After Ambiguous DecodeParms) Tj ET';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Filter [ /ASCII85Decode /FlateDecode ] /DecodeParms << /Predictor 1 >> /Length " . strlen($encoded) . " >>\nstream\n{$encoded}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($visible) . " >>\nstream\n{$visible}\nendstream\nendobj\n"
    . "%%EOF";
var_export((new PdfTextExtractor())->extractTextLines($pdf));
echo "\n";
PHP
```

After the patch, the same boundary excludes `Ambiguous Singleton DecodeParms Leak`; the focused test also proves the aligned `[ null null ]` stack still imports.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterSingletonDecodeParmsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects singleton non-null DecodeParms dictionaries on multi-filter stacks before page text import

1 test files, 11 assertions, 0 failures
```

Adjacent stream-filter stack checks:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterTrailingPayloadBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackOverdeclaredLengthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterSingletonDecodeParmsBoundaryCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
...
4 test files, 472 assertions, 0 failures
```

Cross-path stack checks:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMetadataStreamUnsupportedFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterSingletonDecodeParmsBoundaryCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
...
4 test files, 432 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-stream-filter-singleton-decodeparms-currentbase.php
<!-- markerpdf:stream-filter-singleton-decodeparms-boundary ... "singleton_decodeparms_rejected":true,"aligned_null_decodeparms_imported":true,"filter_tokens_excluded":true,"executes_python_or_models":false,"executes_external_pdf_tools":false,"self_test_passed":true ... -->
```

## Non-Overlap

This does not repeat accepted DCT/CCITT/JPX/JBIG2 preview-only image filters, DCT native-prefix alias review, stream-filter missing-Length recovery, ASCII85/ASCIIHex/RunLength/LZW success-path decoding, overdeclared filtered stream length recovery, trailing payload rejection, compact DecodeParms arrays after null placeholders, `/Crypt` identity helper ownership, attachment duplicate key rejection, metadata unsupported-terminal filter fail-closed behavior, object-stream/xref-stream filter repair, CMaps/fonts, xref repair, annotations, forms, page geometry, table/equation supplied-boundary work, or OCR/model behavior.

The new boundary is specifically ambiguous singleton non-null `/DecodeParms` dictionaries on multi-filter page content streams.

## Dependency Closure

No new support component is needed. The implementation reuses the native PHP PDF stream dictionary parser, filter stack resolver, DecodeParms operand resolver, Flate/ASCII85 decoders, exact object scanner, and text-token parser. Full upstream pdftext/PDFium, OCR, Surya/Torch, tabled-pdf, Texify, and runtime server/model parity remain intentionally out of scope under the current no-GPU markerPDF direction.

## Next Task

Continue with non-overlapping native searchable-PDF parser behavior around fonts, CMaps, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
