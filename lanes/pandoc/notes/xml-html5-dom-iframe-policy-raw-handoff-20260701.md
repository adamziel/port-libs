# XML/HTML5 DOM iframe policy raw handoff

Slice: `plib-88bcw` XML/HTML5 DOM core blocker.

`Html5DomFragment` now preserves bounded iframe policy hints when blocked
`iframe` elements are converted to inert reviewer links or `srcdoc` containers
for raw HTML and WordPress handoff:

- `loading="lazy|eager"` becomes `data-pandoc-iframe-loading`;
- `credentialless` becomes `data-pandoc-iframe-credentialless`, with a
  noncanonical boolean marker when the source value is not empty or
  `credentialless`;
- reviewable `csp` directives become `data-pandoc-iframe-csp` after the same
  CSP filtering used for passive meta CSP review.

Invalid loading states and unsafe CSP directives remain diagnostics-only, and
live iframe attributes are still stripped before serialization. This mirrors the
existing `XmlHtmlDom` iframe policy summary surface without fetching frames,
enforcing browser policy, or changing direct reader parity accounting.

Validation:

- `php -l lanes/pandoc/src/Html5DomFragment.php`
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php` -> 1 file, 2,814 assertions, 0 failures

No external Pandoc, browser engine, Node tooling, office suite, TeX/Typst
engine, zip/unzip, external validator, online service, or live provider was
invoked.
