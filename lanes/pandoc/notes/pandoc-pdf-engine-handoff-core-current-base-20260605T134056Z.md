# Pandoc PDF Engine Handoff Core Current Base 20260605T134056Z

## Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260605T134056Z`
- Accepted base: `858af475bf12386a38b3216c0cd932565f7f894a`
- Scope: bounded fake-runner inspection of produced PDF `/StructElem` accessibility metadata.

## Source Truth And Non-Overlap

This slice extends the existing native PDF byte inspection path in `PdfEngineHandoff`.
It does not implement or invoke a TeX, Typst, browser, roff, JavaScript, Pandoc, or
external PDF validation engine. The prior accepted tagged-PDF slice covered catalog
`/MarkInfo` and `/StructTreeRoot` summary metadata only. This slice adds bounded
individual `/Type /StructElem` object summaries for `/S`, `/P`, `/Pg`, `/Alt`,
`/ActualText`, `/Lang`, `/T`, and top-level `/K` child counts, then exposes those
summaries through `fakeRun()` and `fakeRunSequence()`.

## Implementation

- Added `pdfStructureElements` to fake-runner result payloads.
- Added `finalPdfStructureElements` to sequence final-result payloads.
- Added diagnostics for structure-element count, alt text count, actual text count,
  and language count.
- Reused the existing native PDF object/string parsers, including literal, hex,
  UTF-16BE text string, name, and indirect-reference helpers.
- Updated the WordPress PDF engine handoff smoke example to expose structure-element
  summaries in both first-run and final-run fake PDF bytes.
- Updated lane-local status and manifest counters: `phpPass` 925 -> 926,
  manifest mapped 1382 -> 1383, and PDF engine handoff mapped cases 10 -> 11.

## Red-Green Evidence

- Baseline before edits:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed with `1 test files, 418 assertions, 0 failures`.
- Red-first check after adding the focused expectation:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  failed on missing `pdfStructureElements` with `1 test files, 420 assertions, 1 failures`.
- After implementation:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed with `1 test files, 426 assertions, 0 failures`.

## Final Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`:
  no syntax errors.
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`:
  no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`:
  no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`:
  `json ok`.
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`:
  `1 test files, 426 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`:
  `pdf engine handoff self-test ok`.
- `git diff --check -- lanes/pandoc`:
  no output.

## Dependency Closure

No new support component is needed. The slice reuses the existing bounded native PHP
PDF object, dictionary, reference, array, and text-string parsers already present in
`PdfEngineHandoff`. Full structure-tree parent-tree traversal, MCID marked-content
stream parsing, PDF/UA conformance validation, and real renderer parity remain
separate future support rows.

## Exclusions

- No Pandoc command was run.
- No Haskell, Cabal, Stack, or upstream test runner was run.
- No TeX/PDF engine, Typst, browser renderer, roff renderer, external PDF validator,
  JavaScript, online sanitizer, online service, Word, LibreOffice, zip/unzip, tar,
  lz4, BibTeX, Biber, or citeproc command was run.
- Root harness not run: isolated micro-slice.
