# XML/HTML5 DOM Object Param Review

Bead: `plib-awmcx`

Slice: bounded native PHP XML/HTML5 DOM reviewer metadata for `object` child
`param` elements.

Changed `XmlHtmlDom::summarizeHtmlFragment()` so object param handoff now keeps
legacy `params` stable while exposing richer `paramDetails` and aggregate
object-level review fields:

- normalized param names and lowercase duplicate keys;
- missing, invalid, and duplicate param-name diagnostics;
- normalized `valuetype` state for `data`, `ref`, and `object` params;
- invalid `valuetype` diagnostics while preserving deterministic raw HTML;
- `refParams` and `objectReferenceParams` inventories for reviewer queues.

This does not fetch or validate referenced resources. It only exposes native
DOM provenance before WordPress raw block handoff.

Verification:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` (1 file,
  1550 assertions, 0 failures)
- `php tools/run-tests.php lanes/pandoc/tests` (44 files, 72463 assertions,
  0 failures)
