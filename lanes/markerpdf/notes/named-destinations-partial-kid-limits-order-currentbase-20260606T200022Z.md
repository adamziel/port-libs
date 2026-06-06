# markerpdf named destinations partial kid limits order current-base

- Slice: `markerpdf-named-destinations-boundary-current-base-20260606T200022Z`
- Base: `a213d12bcad4e5ead54f882edb566fd2d7e1093c`
- Behavior: native PDF name-tree destination traversal now keeps valid bounded sibling `/Kids` ordered by their `/Limits` even when another valid child dictionary lacks local `/Limits`; malformed/no-limit children still inherit parent bounds and cannot widen the destination set.
- Source truth: PDF name-tree kids advertise sorted key ranges through `/Limits`; this patch preserves the existing fail-closed behavior for invalid child references while preventing one malformed child dictionary from disabling ordering among bounded siblings in both standalone named-destination extraction and document metadata review.
- Focused test evidence:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationPartialKidLimitsOrderBoundaryCurrentBaseTest.php` => 1 test files, 20 assertions, 0 failures.
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestination*Test.php lanes/markerpdf/tests/PdfOutlineNamedDestination*Test.php` => 43 test files, 1370 assertions, 0 failures.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-named-destination-partial-kid-limits-order-currentbase.php` emits ordered destination review metadata, confirms metadata order matches standalone extraction, excludes stale no-limit child labels, and reports no Python/model/OCR/external PDF tool execution.
- Local hygiene:
  - `php -l lanes/markerpdf/src/PdfNamedDestinationExtractor.php && php -l lanes/markerpdf/src/PdfMetadataExtractor.php && php -l lanes/markerpdf/tests/PdfNamedDestinationPartialKidLimitsOrderBoundaryCurrentBaseTest.php && php -l lanes/markerpdf/examples/wordpress-pdf-named-destination-partial-kid-limits-order-currentbase.php` => no syntax errors.
  - `php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "markerpdf JSON valid\n";'` => markerpdf JSON valid.
  - `git diff --check -- lanes/markerpdf` => clean.
- Dependency closure: no new dependency or support component required; reuses the existing native PHP PDF tokenizer, object resolver, name-tree walker, metadata extractor, and text extractor. No Python, CUDA, OCR/model, shell-to-PDF, or external PDF tool execution is needed.
- Root harness: not run - isolated micro-slice.
