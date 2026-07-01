# XML/HTML5 DOM base helper source-line provenance

Slice: `plib-o70b4` XML/HTML5 DOM core blocker.

`Html5DomFragment` now attaches source-line provenance to the remaining
`base` helper diagnostics emitted outside element-local normalizers:

- normalized control-separated `base href` values;
- unsafe absolute `base href` values;
- unresolved relative `base href` values when no trusted document base exists;
- invalid `base target` metadata values.

The change is metadata-only. It does not relax URL policy, expose live
`base` elements, fetch external resources, invoke a browser, or shell out to
Pandoc/external validators. Sanitized raw HTML and WordPress handoff continue
to strip live `base` tags while diagnostics identify the original source line.

Focused validation:

- `php -l lanes/pandoc/src/Html5DomFragment.php`
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - Result: 1 file, 2792 assertions, 0 failures.
