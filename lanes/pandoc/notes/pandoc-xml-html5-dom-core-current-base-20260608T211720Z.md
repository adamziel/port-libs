## XML/HTML5 DOM Fieldset Metadata Slice

Micro-slice: `pandoc-xml-html5-dom-core-current-base-20260608T211720Z`
Base accepted HEAD: `68d7c32c04f00c8830ab48c497321c0c06937915`

### Behavior

This slice adds bounded native HTML5 DOM fragment handling for passive
`fieldset` grouping metadata before WordPress raw HTML handoff.

- `disabled`, `name`, and `form` on `<fieldset>` are removed from the live HTML
  attribute surface and preserved as inert `data-pandoc-fieldset-*` reviewer
  metadata.
- Invalid fieldset names/form-owner IDs are rejected with `unsafe-attribute`
  diagnostics.
- The first non-empty `<legend>` text is preserved on the fieldset as
  `data-pandoc-fieldset-label`, and the source legend node is marked with
  `data-pandoc-fieldset-legend="true"`.
- Source-authored reserved `data-pandoc-fieldset-*` attributes remain blocked
  by the existing reserved-attribute guard.

### Focused Evidence

Baseline before adding the red case:

- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- Result: `1 test files, 1480 assertions, 0 failures`

Red-first behavior check after adding the focused case:

- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- Result: `1 test files, 1481 assertions, 1 failures`
- Failure: fieldset `disabled`, `name`, and `form` still serialized as live
  attributes.

Final verification:

- `php -l lanes/pandoc/src/Html5DomFragment.php`
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php -l lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php lanes/pandoc/examples/wordpress-html5-dom-fragment-handoff.php --self-test`
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $f . ": " . json_last_error_msg() . "\n"); exit(1); } echo $f . " ok\n"; }'`
- `git diff --check -- lanes/pandoc`

Final focused test result:

- `1 test files, 1508 assertions, 0 failures`

### Status Delta

- Added `+1` focused PHP PASS case.
- Added `+28` focused assertions to `Html5DomFragmentTest.php`.
- Updated `lane-status.json` `phpPass` to `1862`.
- Updated the manifest mapped denominator to `2289`.
- Updated XML/HTML5 DOM core manifest counters to `9` mapped cases and `152`
  assertions.

### Dependency Closure

No new support component is needed. The slice reuses the native
`Html5DomFragment` parser/serializer path and the existing WordPress raw HTML
handoff. No Pandoc, Cabal/Haskell runner, browser renderer, external XML/HTML
tool, online sanitizer, online service, live provider test, or live-service
provider test was executed.

### Non-Overlap

This does not repeat the accepted XML/HTML5 DOM slices for option/optgroup
labels, input button labels, iframe policy metadata, passive link relations,
image map metadata, figure metadata, SVG resource filtering, hidden/inert
metadata, dialog/details/popover metadata, or source-line diagnostics.
