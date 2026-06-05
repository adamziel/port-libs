# markerpdf stream filter stack attachment predictor current-base

## Scope

Bounded native no-GPU markerPDF slice for EmbeddedFile attachment stream filter stacks. This patch applies supported Flate `/DecodeParms` predictors to attachment payload streams after the declared filter stack is decoded, while keeping unsupported or malformed predictor parameters fail-closed before checksum review.

## Source truth

- PDF Flate stream predictors are native stream-filter `/DecodeParms` behavior; page-content streams in `PdfTextExtractor` already decode TIFF and PNG predictors after Flate/LZW inflation.
- Upstream markerPDF relies on native searchable-PDF extraction before any model/OCR handoff. This slice stays in that parser/converter boundary and does not run OCR, Surya, Texify, Torch, GPU/model workers, Python, or external PDF tools.

## Red-first probes

Before the source edit, a valid EmbeddedFile with `/Filter [ /ASCIIHexDecode /FlateDecode ]` and `/DecodeParms [ null << /Predictor 12 /Columns 40 >> ]` was not countable by `PdfAttachmentExtractor`:

```text
array (
  0 => 0,
  1 => array (),
  2 => 0,
)
```

The same payload through `PdfEmbeddedFileExtractor` produced predictor-encoded bytes and `checksum_matches=false` instead of the decoded attachment content.

## Implemented behavior

- `PdfAttachmentExtractor` now aligns `/DecodeParms` entries to non-null filter stack slots, accepts supported Flate predictor dictionaries (`Predictor` 2 and 10-15 with positive `Columns`, `Colors`, and `BitsPerComponent`), and applies TIFF/PNG predictor reversal after Flate inflation.
- `PdfEmbeddedFileExtractor` now parses direct and indirect `/DecodeParms` dictionaries/arrays for EmbeddedFile stream stacks, applies the same Flate predictor reversal, and rejects unsupported/unapplied non-default parameters.
- Unsupported predictor values such as `Predictor 99` remain fail-closed and do not create attachment review rows.

## Focused evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentStreamFilterPredictorCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS decodes Flate DecodeParms PNG predictors in attachment filter stacks before checksum review
PASS resolves indirect DecodeParms arrays for predictor attachment streams
PASS decodes singleton TIFF predictor DecodeParms for embedded attachment streams
1 test files, 69 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfAttachmentExtractorTest.php lanes/markerpdf/tests/PdfEmbeddedFileExtractorTest.php lanes/markerpdf/tests/PdfAttachmentStreamFilterPredictorCurrentBaseTest.php
Focused test run: 3 selected test files (root lock skipped)
42 PASS cases
3 test files, 944 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-attachment-stream-filter-predictor-currentbase.php
emits flate_predictor_decodeparms_applied=true, invalid_predictor_fail_closed=true, payload_bytes_omitted_from_summary=true, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

## Non-overlap

This slice does not repeat page-content stream filter boundary work such as ASCII85 end markers, null filter alignment, malformed indirect filter names, object-stream filters, or the prior attachment decoded-length/xref object-stream slices. The new behavior is specifically the attachment/EmbeddedFile payload decoders applying supported Flate predictor `/DecodeParms` before checksum and WordPress review metadata.

## Dependency closure

No new support component is needed. The patch reuses native zlib inflation plus local TIFF/PNG predictor reversal logic already present in the lane and keeps all execution inside PHP parser code.

## Next task

Continue with non-overlapping searchable-PDF native parser gaps: font/CMap fidelity, xref repair, metadata, annotations/forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
