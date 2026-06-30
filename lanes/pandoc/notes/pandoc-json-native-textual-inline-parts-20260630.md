# Pandoc JSON/native textual inline part constructors

Slice: `plib-arz4a`

`NativeWriter` now preserves valid `nativeInlineParts` leaf constructor boundaries
when rendering textual Native output through `blocksOnly`. This covers `Str`,
`Space`, `SoftBreak`, `LineBreak`, and empty `Str` payloads when the provenance
still matches the text. Edited or stale text continues to use the existing
canonical textual Native rendering.

This keeps JSON/native AST provenance from flattening soft/line breaks into
plain spaces when a caller asks for textual Native instead of a JSON packet. No
Pandoc, TeX, browser, office, or external validator process is invoked.

Validation:

- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  includes the new passing regression `preserves native text part constructors
  in textual native output`; the full focused file remains baseline-red with 11
  unrelated existing failures.
