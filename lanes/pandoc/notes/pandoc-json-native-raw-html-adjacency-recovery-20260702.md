# Pandoc JSON native raw HTML adjacency recovery

Work item: `plib-6w9f0`

## Summary

The stranded raw HTML adjacency bead was recovered on a current `origin/main`
branch. The landed implementation and coverage were already present in
`origin/main` through `4b58b089cf` and `a2c88a6421`; this recovery pass fixed
the focused adjacency test expectation so native-text round trips assert the
current `Format` helper payloads recorded by `NativeReader`.

The previous source branch
`origin/polecat/854/plib-6w9f0@mqc0j3iw` was inspected and did not contain the
current `PandocJsonNativeRawHtmlAdjacencyBoundaryTest.php`; its diff against
`origin/main` was mass-divergent, so no stale branch contents were reused.

## Validation

- `php -l lanes/pandoc/src/MarkdownWriter.php`
- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeRawHtmlAdjacencyBoundaryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeRawHtmlAdjacencyBoundaryTest.php`
  with 1 file, 111 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeRawHtmlAdjacencyBoundaryTest.php lanes/pandoc/tests/PandocJsonRawTexInlineConstructorTest.php lanes/pandoc/tests/JsonReaderFormatConstructorTest.php lanes/pandoc/tests/NativeReaderTextConstructorProvenanceTest.php`
  with 4 files, 329 assertions, 0 failures.
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json porting-summary.json`
- `git diff --check`

The broad current-base lane command
`php tools/run-tests.php lanes/pandoc/tests` remains baseline-red outside this
bead, completing with 534 test files, 142362 assertions, and 8910 failures.

No Pandoc binary, Haskell/Cabal tooling, browser, Node, XML validator, office
suite, zip/unzip command, external validator, or live service was invoked.
