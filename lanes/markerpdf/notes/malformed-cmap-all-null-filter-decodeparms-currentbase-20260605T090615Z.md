## Malformed CMap All-Null Filter DecodeParms Boundary

Slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260605T090615Z`

Base accepted HEAD: `a02fc28d14bb45fe4a801d7566e5c298993e318f`

Source truth: native PDF stream semantics only. A `/Filter [ null ]` stack has
no active decoder filters, so a stale or missing `/DecodeParms` operand should
remain review-visible but must not count as an unresolved active decode
dependency for a ToUnicode CMap stream.

Implementation:

- `PdfTextExtractor` now normalizes all-null/no-decoder CMap filter stacks to
  an empty DecodeParms application list for review.
- Unresolved or malformed DecodeParms operands are ignored for active CMap
  unresolved counts when `filters === []`, while their operand metadata remains
  exposed in `decodeparms_operands`.
- The focused fixture proves `decoded_with_current_operands=true`,
  `unresolved_operand_count=0`, and `decodeparms_operand_policy=decodeparms_resolved`
  while preserving the missing `99 0 R` DecodeParms operand as review metadata.

Evidence:

- Red before source fix, after adding the focused case:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php`
  -> `1 test files, 917 assertions, 1 failures`; failure was
  `Expected: 0 Actual: 1` for the unresolved DecodeParms count.
- Green after source fix:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php`
  -> `1 test files, 949 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-filter-boundary-currentbase.php`
  completed and emitted `73` output lines with
  `all_null_filter_decodeparms_slot_ignored=true`,
  `all_null_filter_decodeparms_decoded_cmap_count=1`, and
  `all_null_filter_decodeparms_unresolved_operand_count=0`.

Non-overlap:

- Avoids the accepted classic xref `/Prev` repair, object stream, metadata,
  embedded-file, outline, annotation, image, encryption, and live OCR/model
  surfaces.
- Extends only the existing malformed CMap filter/DecodeParms boundary cluster
  with an all-null filter-array DecodeParms edge.

Dependency closure:

- No new support component is needed. The patch reuses native PHP dictionary,
  stream-filter, CMap, and review helpers already present in markerPDF.
- GPU/model OCR, Surya/Texify/Torch, Streamlit/FastAPI model workers, and
  upstream model benchmark parity remain intentionally out of scope under the
  current no-GPU markerPDF directive.

Root harness: not run - isolated micro-slice.
