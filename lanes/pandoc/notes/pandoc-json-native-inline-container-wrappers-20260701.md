# Pandoc JSON/native inline container collection wrappers

Slice: `plib-lanri` JSON/native AST constructor completeness.

This slice preserves child collection wrapper provenance for rebuilt inline
constructors. `PandocJsonReader` now records source inline-list payloads on
`Emph`, `Strong`, `Underline`, `Strikeout`, `Superscript`, `Subscript`,
`SmallCaps`, `Quoted`, `Span`, `Link`, and `Image` nodes, and records source
block-list payloads on `Note` nodes.

`PandocJsonWriter` reuses those recorded child payloads only when regenerated
children still match, so rebuilt parent constructors keep single-wrapped
collections while edited children drop stale sidecars. `NativeWriter` treats
the new provenance attrs as native comparison sidecars.

Validation:

- Red probe before implementation: current `PandocJsonReader` returned `NULL`
  for `inlineListNative` on a wrapped `Emph` payload.
- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/src/PandocJsonWriter.php`
- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonInlineContainerCollectionWrapperTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonInlineContainerCollectionWrapperTest.php`: 1 file, 132 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeCollectionWrapperTest.php lanes/pandoc/tests/PandocJsonStructuralBlockListWrapperTest.php lanes/pandoc/tests/PandocJsonNativeTableCollectionWrapperTest.php`: 3 files, 192 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonSingleWrappedAttrTupleTest.php lanes/pandoc/tests/PandocJsonRawTexInlineConstructorTest.php lanes/pandoc/tests/JsonReaderHelperConstructorCompatibilityTest.php`: 3 files, 97 assertions, 0 failures.
- Post-rebase validation: `git fetch origin main && git rebase origin/main` reported the branch up to date; post-rebase `php -l` checks passed for touched PHP files; `php tools/run-tests.php lanes/pandoc/tests/PandocJsonInlineContainerCollectionWrapperTest.php lanes/pandoc/tests/PandocJsonNativeCollectionWrapperTest.php lanes/pandoc/tests/PandocJsonStructuralBlockListWrapperTest.php lanes/pandoc/tests/PandocJsonNativeTableCollectionWrapperTest.php lanes/pandoc/tests/PandocJsonSingleWrappedAttrTupleTest.php lanes/pandoc/tests/PandocJsonRawTexInlineConstructorTest.php lanes/pandoc/tests/JsonReaderHelperConstructorCompatibilityTest.php` passed with 7 files, 421 assertions, 0 failures; `jq empty` passed for `UPSTREAM_TEST_MANIFEST.json` and `lane-status.json`; `git diff --check origin/main...HEAD -- lanes/pandoc` and conflict-marker scan passed.

No external Pandoc, office suite, TeX/browser engine, Typst, Jupyter, Node,
zip/unzip, validators, citeproc, BibTeX, Biber, or live services were invoked.
Direct-format parity remains active in lane status blocker notes.
