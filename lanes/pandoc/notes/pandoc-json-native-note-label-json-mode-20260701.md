# Pandoc JSON/native note label sidecar preservation

Slice `plib-nagzk` keeps generated note labels on the native JSON path. A
Markdown-derived document can carry `label`/`noteLabel` metadata for footnote
references even when it has no Pandoc JSON provenance attributes. Textual
native output cannot represent that sidecar, so `NativeWriter` now selects the
Pandoc JSON packet path when a safe note label sidecar is present.

The JSON writer also accepts `noteLabel` as an alias for `label` when producing
`Note` constructors, matching existing Markdown/WordPress note-label handoff.
Invalid labels still regenerate ordinary `Note` constructors without
`noteLabel`.

Validation on 2026-07-01:

- `php -l` for `NativeWriter.php`, `PandocJsonWriter.php`,
  `PandocNativeWriterJsonProvenanceTest.php`, and
  `PandocJsonNativeAstTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocNativeWriterJsonProvenanceTest.php`
  passed with 15 assertions and 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/PandocJsonNativeAstTest.php`
  now passes `preserves markdown note labels through json and native note
  sidecars`; the file remains baseline-red with 6,038 assertions and 9
  unrelated failures.
