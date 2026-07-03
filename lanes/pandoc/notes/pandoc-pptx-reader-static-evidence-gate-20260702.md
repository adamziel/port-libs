# PPTX Reader Static Evidence Gate

Date: 2026-07-02

## Scope

- Added a static gate for pinned upstream `Tests.Readers.Pptx` at commit
  `4f5226df4faa0d66dd2c089465b13886360ab3c2`.
- The static denominator is one golden comparison: `text extraction` with
  `pptx-reader/basic.pptx` and `pptx-reader/basic.native`.
- Bound that denominator to the checked-in current fixture snapshot at
  `lanes/pandoc/fixtures/upstream-current-pptx-reader`.
- The checked-in snapshot gate verifies one same-stem PPTX/native pair plus
  SHA-256 hashes and byte counts for `basic.pptx` and `basic.native`.

## Boundary

- No upstream Haskell, Cabal, or Tasty runner transcript is present or claimed.
- No broader PPTX fixture corpus denominator is closed beyond the pinned
  `basic.pptx`/`basic.native` pair.
- No PPTX writer parity or full PowerPoint feature parity is asserted.

## Verification

- `php -l lanes/pandoc/src/PptxUpstreamReaderEvidence.php`
- `php -l lanes/pandoc/tests/PptxUpstreamReaderEvidenceTest.php`
- `php -l tools/pandoc-pptx-reader-evidence.php`
- `php tools/run-tests.php lanes/pandoc/tests/PptxUpstreamReaderEvidenceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PptxNativeAstComparisonHarnessTest.php`
