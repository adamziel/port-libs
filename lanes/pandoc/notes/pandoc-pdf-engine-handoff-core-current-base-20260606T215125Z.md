# Pandoc PDF Engine Handoff Core Current Base 20260606T215125Z

Lane: `pandoc`
Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260606T215125Z`
Accepted base: `dee21061aaf1fbb0aab4f4e3f945291f29676e20`

## Scope

This slice extends bounded native PHP PDF-output fake-runner handoff to traverse nested catalog `/Names` `/JavaScript` name-tree `/Kids` entries. It does not execute Pandoc, Cabal/Haskell runners, TeX/PDF engines, Typst, browser renderers, roff, JavaScript, external PDF validators, online services, live provider tests, or live-service provider tests.

Non-overlap:
- Existing direct `catalog.Names.JavaScript.*` action extraction remains unchanged.
- This adds nested name-tree child action labels such as `catalog.Names.JavaScript.Kids.9 0 R.ReviewOpen`.
- The slice reuses active-action summarization for JavaScript hashes and `SubmitForm` targets.
- It does not repeat URI base, page display, XMP/PDF-A, output intents, tagging, annotations, signatures, optional content, ZIP/OPC, Office, EPUB, ODT, bibliography, table, math, or charset work.

## Implementation

- Added recursive `collectPdfJavaScriptNameTreeActions()` with depth and visited-reference guards.
- Updated `collectPdfNamedJavaScriptActions()` to traverse JavaScript name-tree nodes instead of only direct `/Names` arrays.
- Added a focused red-first PDF test for nested JavaScript name-tree `/Kids` entries with both indirect JavaScript and inline `SubmitForm` actions.
- Added `examples/wordpress-pdf-javascript-name-tree-handoff.php` as a WordPress review smoke using `--self-test`.

Status movement:
- `benchmarkDenominator.mapped`: `1819` to `1820`.
- PDF engine handoff core cases: `10` to `11`.
- PDF engine handoff core assertions: `95` to `102`.
- Focused `PdfEngineHandoffTest.php`: `666` to `673` assertions.

## Evidence

Baseline:
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- Result: `1 test files, 666 assertions, 0 failures`

Red-first:
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- Result before implementation: `1 test files, 668 assertions, 1 failures`
- Failure reason: nested catalog `/Names` `/JavaScript` `/Kids` actions were not extracted.

Final focused tests:
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
- Result: `1 test files, 673 assertions, 0 failures`

Example smoke:
- `php lanes/pandoc/examples/wordpress-pdf-javascript-name-tree-handoff.php --self-test`
- Result: `pdf javascript name-tree handoff self-test ok`

Syntax checks:
- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php -l lanes/pandoc/examples/wordpress-pdf-javascript-name-tree-handoff.php`
- Result: no syntax errors detected.

JSON validation:
- `php -r '$files=["lanes/pandoc/lane-status.json","lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"]; foreach ($files as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " ok\n"; }'`
- Result: both lane JSON files decoded successfully.

Whitespace check:
- `git diff --check -- lanes/pandoc`
- Result: passed with no output.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed for this slice. It reuses native PHP `PdfEngineHandoff` PDF object parsing, catalog `/Names` dictionary parsing, recursive name-tree traversal, existing active-action summarization, fake-runner diagnostics, and the WordPress PDF JavaScript name-tree smoke example.

Remaining bounded follow-up work: name-tree balancing and `/Limits` validation, page/annotation additional-action policy grouping, compressed object-stream expansion policy, and full renderer parity. Pandoc, Cabal/Haskell runners, TeX/PDF engines, Typst, browser renderers, roff, external PDF validators, JavaScript execution, online services, live provider tests, and live-service provider tests remain out of scope unless explicitly authorized.
