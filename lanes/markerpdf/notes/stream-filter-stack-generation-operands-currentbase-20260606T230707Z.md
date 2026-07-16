# Stream Filter Stack Generation Operands Current Base

Slice: `markerpdf-stream-filter-stack-boundary-current-base-20260606T230707Z`  
Base: `ca789a5b6d3e33e7c4378e92f189c08d2e32e040`

## Source Truth

PDF stream dictionaries may use indirect operands for `/Length`, `/Filter`, and
`/DecodeParms`. Indirect references include an object number and generation, so
generation `20 0 R` and generation `20 1 R` are distinct operands even when
their object number is shared. The native parser must not collapse those into a
single object-number slot when resolving a stream filter stack.

## Behavior Added

`PdfTextExtractor` now resolves stream length, filter-name, DecodeParms, and
indirect DecodeParms scalar operands through exact-generation fallback. This
allows a content stream such as:

- `/Filter 20 0 R` -> `/FlateDecode`
- `/DecodeParms 20 1 R` -> `<< /Predictor 12 /Columns 21 1 R >>`
- `/Length 21 0 R` -> encoded stream length
- `/Columns 21 1 R` -> PNG predictor row width

to decode with both the generation-zero filter and the generation-one
predictor dictionary present at the same time, while preserving exact
generation-zero and generation-one scalar operands used by the same stream.

The red-first probe on the accepted base imported only
`Visible After Shared Operand Generations`, proving the original object map
lost one of the same-number/different-generation operands.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
...
PASS resolves stream Filter and DecodeParms operands by exact generation when object numbers overlap

1 test files, 379 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-stream-filter-generation-operands-currentbase.php
filter_generation_zero_selected=true
decodeparms_generation_one_selected=true
length_generation_zero_selected=true
columns_generation_one_selected=true
same_object_number_generations_coexist=true
helper_payload_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

## Non-Overlap

This slice does not repeat the previous inline image `/Decode`
exact-generation operand patch. It targets ordinary stream `/Length`,
`/Filter`, `/DecodeParms`, and DecodeParms scalar operand resolution in
`PdfTextExtractor`, while preserving the existing stream-stack boundary tests
for ASCII85, ASCIIHex, RunLength, LZW, Crypt, parser-comment split references,
duplicate keys, and malformed length/filter operands.

## Dependency Closure

No new support component is required. The patch reuses the existing native PHP
PDF object table, direct-generation object cache, stream filter decoders, and
focused markerPDF test harness. GPU/model/OCR execution and external PDF tools
remain intentionally out of scope for this no-GPU markerPDF lane.

Root harness: not run - isolated micro-slice.
