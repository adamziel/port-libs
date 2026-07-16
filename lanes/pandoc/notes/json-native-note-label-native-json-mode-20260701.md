# JSON/native note-label native JSON mode

`plib-yxrvx` preserves Markdown-derived note labels through `NativeWriter`
when the document has no other JSON/native provenance.

Previously, a shared AST document with only a labeled `note` node could take
the textual native writer path, which has no slot for the Pandoc JSON
`noteLabel` sidecar. `NativeWriter` now treats valid note labels as
JSON/native-only provenance, so labeled `Note` constructors are emitted through
the Pandoc JSON/native path and remain readable by `NativeReader`. Unlabelled
inline notes remain sidecar-free, and invalid generated labels are still
omitted by the existing writer validation.

Validation:

- `php -l lanes/pandoc/src/NativeWriter.php`
- `php -l lanes/pandoc/tests/NativeWriterNoteLabelJsonModeTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/NativeWriterNoteLabelJsonModeTest.php`
  passed with 4 assertions and 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  confirmed `preserves markdown note labels through json and native note
  sidecars` now passes. The full focused file remains baseline-red with 6,038
  assertions and 9 unrelated existing failures.

No Pandoc, TeX, browser, office suite, zip/unzip, Node tooling, Jupyter, or
external validator was invoked.
