# Malformed CMap Post-DecodeParms Filter Boundary

Slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260605T215341Z`

Accepted base: `2327c26a69235d4b32b986d7e360e0be32c213e0`

## Source truth

Upstream `sddai/markerPDF` routes searchable-PDF text extraction through PDF parser dependencies and does not run OCR/model code for searchable text. Under the current no-GPU markerPDF scope, the native PHP port owns stream filter safety before ToUnicode CMap decoding. A malformed CMap stream dictionary with a scalar `/Filter /FlateDecode`, a valid `/DecodeParms << /Predictor 1 >>`, and then an unkeyed decoder name such as `/ASCIIHexDecode` before `/Length` must fail closed instead of treating the extra decoder as an unrelated dictionary key and decoding the CMap payload.

## Behavior

`PdfTextExtractor` now scans value-bearing dictionary keys after direct scalar or indirect `/Filter` operands. If a decoder-looking name appears as an unkeyed top-level token before `/Length`, filter resolution fails and review metadata marks the filter operand with `extra_filter_operand=true`, `extra_filter_name=ASCIIHexDecode`, and `filter_operand_policy=reject_malformed_filter_operands`.

Visible searchable text falls back to the current font bytes, preserving safe WordPress import text and excluding the decoded malformed CMap payload.

## Red-first evidence

Before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapPostDecodeParmsFilterBoundaryCurrentBaseTest.php`

Result: `1 test files, 1 assertions, 1 failures`

Failure excerpt: expected `Post DecodeParms Safe Import`, actual text leaked `Post DecodeParms CMap Leakost DecodePost DecodeParms CMap Leakarms Safe Import`.

## Verification

After the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapPostDecodeParmsFilterBoundaryCurrentBaseTest.php`

Result: `1 test files, 55 assertions, 0 failures`

Adjacent CMap/filter gate:

`php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapPostDecodeParmsFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapReferenceExtraFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapUnknownFilterNameBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterEodBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php`

Result: `6 test files, 1911 assertions, 0 failures`

Syntax and smoke checks:

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` - no syntax errors
- `php -l lanes/markerpdf/tests/PdfParserMalformedCMapPostDecodeParmsFilterBoundaryCurrentBaseTest.php` - no syntax errors
- `php -l lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-post-decodeparms-filter-currentbase.php` - no syntax errors
- `php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-post-decodeparms-filter-currentbase.php` - emitted `safe_import_text_preserved=true`, `cmap_payload_excluded=true`, `post_decodeparms_extra_decoder_rejected=true`, `decodeparms_operand_preserved=true`, `decoded_cmap_count=0`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`
- `jq empty lanes/markerpdf/lane-status.json` - valid JSON
- `git diff --check -- lanes/markerpdf` - no whitespace errors

## Non-overlap

This patch does not repeat the already-covered direct extra filter token, indirect reference extra filter token, unknown unkeyed filter name before `/Length`, dictionary/literal/indirect filter operand, duplicate top-level `/Filter`, malformed DecodeParms operand/parameter, explicit filter EOD, trailing post-`endcmap`, or inline-image filter boundary slices. The new boundary is specifically an extra decoder-name operand hidden after a valid value-bearing `/DecodeParms` key and before `/Length`.

## Dependency closure

No new support component is needed. The patch reuses the native PHP stream dictionary parser and review metadata path. No Python, OCR/model, PDFium, Surya/Texify/Torch, external PDF tools, network, or live-service provider tests are required or run.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior: font encodings and widths, CMap reference/generation edges, stream filter stacks, xref repair, metadata, annotations/forms, page geometry, image/filter metadata, and supplied-boundary table or equation handoffs.
