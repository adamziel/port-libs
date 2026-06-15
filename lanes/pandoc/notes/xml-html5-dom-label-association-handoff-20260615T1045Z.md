# XML/HTML5 DOM label association handoff

Slice: `pandoc-xml-html5-dom-label-association-handoff`

This adds a bounded native PHP sanitizer handoff for HTML `<label>` control
associations in `Html5DomFragment`.

- Explicit `for` targets are converted to inert `data-pandoc-label-for` and
  bounded control metadata.
- Wrapped descendant controls are summarized without preserving live form
  controls or input values.
- Missing targets, non-label targets, invalid `for` tokens, source-owned
  `data-pandoc-label-*` spoofing, and hidden inputs stay review/diagnostic-only.
- Input `type` metadata is bounded to known HTML input states, defaulting to
  `text` for malformed or unknown values.
- Final rebase base: `6a55215f18`.

Accounting:

- `phpPass`: `3722 -> 3723`
- `phpFail`: `0`
- mapped upstream manifest cases: `3741 -> 3742`
- `mappedXmlHtmlDomLabelAssociationHandoffCases`: `0 -> 1`
- `xmlHtmlDomLabelAssociationHandoffAssertions`: `0 -> 36`

Verification so far:

- `php -l lanes/pandoc/src/Html5DomFragment.php`
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  passed: 1 file, 2640 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 46 files, 88289 assertions, 0 failures.

No Pandoc, Cabal/Haskell runner, browser renderer, external validator, online
service, live provider test, or live-service provider test was invoked.
