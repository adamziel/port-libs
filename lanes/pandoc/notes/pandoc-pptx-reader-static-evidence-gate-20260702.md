# PPTX Reader Static Evidence Gate

Date: 2026-07-02
Updated: 2026-07-03

## Scope

- Added a static gate for pinned upstream `Tests.Readers.Pptx` at commit
  `4f5226df4faa0d66dd2c089465b13886360ab3c2`.
- The static denominator is one golden comparison: `text extraction` with
  `pptx-reader/basic.pptx` and `pptx-reader/basic.native`.
- Bound that denominator to the checked-in current fixture snapshot at
  `lanes/pandoc/fixtures/upstream-current-pptx-reader`.
- The checked-in snapshot gate now verifies 45 same-stem PPTX/native pairs,
  including `basic.pptx`/`basic.native`, with SHA-256 hashes, byte counts,
  zero unpaired PPTX/native files, and 45/45 local PHP reader to checked-in
  native normalized AST matches.
- The reader evidence gate also validates the checked-in real-Pandoc executable
  snapshot at
  `lanes/pandoc/fixtures/upstream-current-pptx-reader/checked-in.executable-native-ast.json`,
  which records `pandoc 3.10` producing native output matching both local PHP
  reader output and paired checked-in `.native` fixtures for all 45 checked-in
  PPTX fixtures.

## Boundary

- No upstream Haskell, Cabal, or Tasty runner transcript is present or claimed.
- The 44 generated non-`basic` fixture pairs are executable/native parity pins,
  not upstream `Tests.Readers.Pptx` golden comparisons.
- No broader PPTX fixture corpus denominator is closed beyond the 45 checked-in
  current PPTX/native pairs.
- No PPTX writer parity or full PowerPoint feature parity is asserted.

## Verification

- `php -l lanes/pandoc/src/PptxUpstreamReaderEvidence.php`
- `php -l lanes/pandoc/tests/PptxUpstreamReaderEvidenceTest.php`
- `php -l tools/pandoc-pptx-reader-evidence.php`
- `php tools/pandoc-pptx-reader-evidence.php --repo-root=/tmp/port-libs-trunk-audit --upstream-root=missing-upstream-root-for-static-gate --json summary --require-static-current-evidence --require-static-native-mapped-parity --require-static-executable-native-ast-parity --require-runner-not-run --require-runner-plan`
- `php tools/run-tests.php lanes/pandoc/tests/PptxUpstreamReaderEvidenceTest.php lanes/pandoc/tests/PptxNativeAstComparisonHarnessTest.php lanes/pandoc/tests/PptxExecutableNativeAstComparisonHarnessTest.php`
