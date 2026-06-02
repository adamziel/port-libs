# markerPDF Stream Filter DecodeParms Fail-Closed Boundary

Slice: `stream-filter-decodeparms-failclosed-boundary-currentbase-20260602T123736Z`

## Source Truth

Upstream markerPDF at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF text extraction through the parser/pdftext boundary before OCR/layout cleanup. At that boundary, declared stream filters and their `/DecodeParms` must be honored before bytes become page text. A content stream with present but malformed or unresolved predictor parameters is not safe to reinterpret as an unparameterized raw stream for WordPress paragraphs.

This slice is also aligned with the PDF stream filter contract already mapped in this lane: `/DecodeParms` entries are filter parameters, and malformed known integer parameters such as `/Predictor`, `/Columns`, `/Colors`, `/BitsPerComponent`, and `/EarlyChange` must fail closed instead of silently falling back to defaults.

## Red-First Boundary

On the current accepted base before the fix, this command leaked filtered bytes as visible text:

```text
php -r 'require "tools/bootstrap.php"; $c="BT /F1 12 Tf 72 720 Td (Malformed Predictor Leak) Tj ET"; $z=gzcompress($c); $pdf="%PDF-1.4\n1 0 obj\n<< /Filter /FlateDecode /DecodeParms << /Predictor /Twelve >> /Length ".strlen($z)." >>\nstream\n".$z."\nendstream\nendobj\n%%EOF"; echo (new PortLibs\MarkerPDF\PdfTextExtractor())->extractPlainText($pdf), "\n";'
Malformed Predictor Leak
```

## Implementation

`PdfTextExtractor::canApplyDecodeParms()` now validates known integer-valued `/DecodeParms` keys when they are present. Absent keys still use accepted defaults, valid direct and indirect predictor values still decode, and existing image-only filter exclusions remain review-only. Present but non-integer, missing-indirect, cyclic/unresolved, or non-positive row-geometry parameters reject the stream before text-token parsing.

The focused test keeps one valid Flate PNG predictor stream recoverable while excluding three unsafe streams:

- `/FlateDecode` with `/Predictor /Twelve`.
- `/FlateDecode` with unresolved `/Columns 99 0 R`.
- `/LZWDecode` with unresolved `/EarlyChange 99 0 R`.

## WordPress Path

`examples/wordpress-pdf-stream-filter-decodeparms-failclosed-boundary.php` emits only:

- `Valid DecodeParms Boundary`
- `Recovered Predictor Rows`
- `Visible After DecodeParms Boundary`

The smoke records `malformed_predictor_excluded=true`, `unresolved_columns_excluded=true`, `unresolved_earlychange_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Verification

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfTextExtractorTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-stream-filter-decodeparms-failclosed-boundary.php` passed.
- `php lanes/markerpdf/examples/wordpress-pdf-stream-filter-decodeparms-failclosed-boundary.php` emitted the expected three Gutenberg paragraphs and all exclusion flags.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php` passed with 1 file / 549 assertions / 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests` passed with 65 files / 3974 assertions / 0 failures.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object parser, stream dictionary reader, `/Filter` and `/DecodeParms` resolver, Flate/LZW/predictor decoders, and content-token parser. Full upstream Python/model/benchmark parity remains dependency-gated by pdftext, pypdfium2, Surya/Torch models, tabled-pdf, Texify, runtime app/server tooling, and live benchmark dependencies.

## Non-Overlap

This does not repeat accepted ASCIIHex/ASCII85/RunLength/LZW/Flate success-path decoding, valid indirect numeric DecodeParms predictor handling, stream filter-chain recovery for missing top-level DecodeParms objects, indirect filter-name arrays, declared-filter unsupported/corrupt error boundaries, image-filter exclusions, inline image DecodeParms validation, or parser stream-owner fallback boundaries. The new behavior is specifically present-but-invalid known `/DecodeParms` integer values on current-base stream decoding.
