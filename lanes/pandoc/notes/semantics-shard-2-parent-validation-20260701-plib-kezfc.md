# Semantics shard 2 parent validation

Slice: `plib-kezfc`

Target: `integration/pandoc-semantics`

Folded leaf heads:

- `origin/integration/pandoc-semantics-json` at `f8133c798`
- `origin/integration/pandoc-semantics-csl` at `e1b034ea6`
- `origin/integration/pandoc-semantics-xml` at `4c1b3fdbf`

Merge result:

- `git merge-base --is-ancestor origin/integration/pandoc-semantics-json HEAD`
  returned `0`.
- `git merge-base --is-ancestor origin/integration/pandoc-semantics-csl HEAD`
  returned `0`.
- `git merge-base --is-ancestor origin/integration/pandoc-semantics-xml HEAD`
  returned `0`.

Net tree delta against the parent is the JSON/native textual `ShortCaption`
helper slice: `NativeReader` accepts `Caption (Just (ShortCaption ...))`
forms for figures and tables, preserves shared short and long caption metadata,
and keeps `PandocJsonWriter` handoff on the canonical `Just`/`ShortCaption`
helper shape.

The CSL and XML leaf content was already present in the parent tree; their
merge commits record ancestry so the leaf heads are folded into the integration
branch without replaying older lane-status snapshots.

Validation run on the folded branch:

- `php -l lanes/pandoc/src/NativeReader.php`
- `php -l lanes/pandoc/tests/NativeReaderTest.php`
- JSON parse for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check -- lanes/pandoc`
- `rg -n '^(<<<<<<<|=======|>>>>>>>)($| )' lanes/pandoc`
- `php tools/run-tests.php lanes/pandoc/tests/NativeReaderTest.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  passed with `2 test files, 6739 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php lanes/pandoc/tests/XmlHtmlDomBaseUrlTargetReviewTest.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  passed with `3 test files, 7442 assertions, 0 failures`.

The broad Pandoc lane suite was not run for this merge shard; the task requested
focused validation and no waiting on slow full-suite/refinery checks.
