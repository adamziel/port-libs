# markerpdf-malformed-cmap-filter-boundary-current-base-20260608T083829Z

Accepted base: `e7d50020ec7dcde5a97e73ec313bbb4fa11ac57c`

Scope: native no-GPU markerPDF CMap stream filter boundary handling. This slice is limited to searchable-PDF parser behavior and review metadata; it does not run OCR, Surya, Texify, Torch, raster rendering, Python model code, or external PDF tools.

Behavior implemented:

- `PdfTextExtractor` now records `array_item=true` and zero-based `array_index` on operands reviewed from a top-level PDF array, including CMap stream `/Filter` arrays.
- A malformed scalar item inside a CMap `/Filter` array is rejected before ToUnicode replacement while the following valid decoder name remains visible as a separate positional review operand.
- Focused fixtures cover compressed ToUnicode CMaps with `/Filter [ true /FlateDecode ]` and `/Filter [ 1.5 /FlateDecode ]`; both preserve safe fallback WordPress text, exclude decoded CMap payload text, report `reject_malformed_filter_operands`, and record the bad scalar at `array_index=0` plus `/FlateDecode` at `array_index=1`.

Non-overlap:

- Avoids the accepted scalar whole-value CMap filter boundary (`/Filter true`, `/Filter 1.5`), array tail boundary (`/Filter [ /FlateDecode ] /ASCIIHexDecode`), dictionary/literal array operands, escaped-key filter boundaries, DecodeParms boundaries, and inline Indexed literal palette work.
- This patch covers scalar operands *inside* a top-level CMap `/Filter` array plus the review metadata needed to attribute their position.

Focused verification:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapArrayScalarFilterOperandBoundaryCurrentBaseTest.php`
  - `1 test files, 116 assertions, 0 failures`
- Adjacent family with this additive test included:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMap*Filter*CurrentBaseTest.php`
  - `38 test files, 4250 assertions, 0 failures`
- WordPress smoke:
  - `php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-array-scalar-filter-currentbase.php --self-test`
  - exits 0 with `decoded_cmap_count=0`, bad scalar `array_index=0`, valid decoder `array_index=1`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Status delta:

- Adds 2 focused PHP PASS cases and 116 focused assertions.
- Adds 1 WordPress-relevant smoke scenario.
- `lane-status.json` updated from `phpPass=2995` to `phpPass=2997`, and from `wordpressScenarios=2482` to `wordpressScenarios=2483`.

Dependency closure:

- No new support component is required. This reuses the existing native `pdf-text-dictionary-core` stream dictionary/parser review path.
- Remaining no-GPU exclusions are unchanged: live OCR/model execution, exact upstream model benchmark parity, and external PDF tool execution remain intentionally out of scope.

Next task:

- Continue with non-overlapping native markerPDF parser coverage around CMap/filter decode edges, font encodings, xref repair, image/filter metadata, forms/annotations, page geometry, and supplied-boundary table/equation handoffs.
