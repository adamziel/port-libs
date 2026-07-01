# Pandoc JSON/native single-wrapped Attr tuples

Bead: `plib-lvgny`
Date: 2026-07-01 UTC
Area: Pandoc JSON/native AST constructor completeness

## Behavior

`PandocJsonReader` now preserves source single-wrapped untagged `Attr` tuple
payloads in `attrNative`. The shared AST still exposes normalized `id`,
`classes`, and key-value `attributes`, but `PandocJsonWriter` and `NativeWriter`
can reuse the original wrapped tuple when rebuilding unchanged `Header`, `Link`,
and other Attr-bearing constructors.

If the semantic attributes are edited, writers regenerate the canonical
three-field Attr tuple and drop stale sidecar fields, matching existing tagged
Attr and untagged tuple behavior.

No Pandoc binary, JSON filters, Cabal/Haskell runners, office suites, TeX/PDF
engines, browser renderers, `zip`/`unzip`, external validators, online services,
or live provider tests were invoked.

## Verification

- `php -l lanes/pandoc/src/PandocJsonReader.php`
- `php -l lanes/pandoc/tests/PandocJsonSingleWrappedAttrTupleTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonSingleWrappedAttrTupleTest.php`
  - `1 test files, 26 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/PandocNativeWriterJsonProvenanceTest.php lanes/pandoc/tests/PandocJsonRawTexInlineConstructorTest.php lanes/pandoc/tests/PandocJsonSingleWrappedAttrTupleTest.php`
  - `3 test files, 79 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  - `1 test files, 6020 assertions, 12 failures`
  - The new attr-sidecar-adjacent cases passed; the remaining failures are the
    existing broad JSON/native WordPress/raw/citation baseline failures outside
    this slice.
