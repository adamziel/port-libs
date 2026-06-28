# PDF viewer preference enforce gaps

Slice: `plib-n0cto` on 2026-06-28.

`PdfEngineHandoff` now parses `/ViewerPreferences` from top-level dictionary
entries before summarizing policy, so names inside `/Enforce [...]` arrays no
longer masquerade as sibling viewer preference keys. The produced-PDF policy
also records non-empty `unresolvedEnforcedPreferences` and emits
`unresolved-enforced-viewer-preference` diagnostics when enforced names do not
resolve to parsed UI or print preferences in the same dictionary.

Validation:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`:
  1 file, 3468 assertions, 0 failures.

No TeX, Typst, browser, office, converter, or external validator was invoked.
