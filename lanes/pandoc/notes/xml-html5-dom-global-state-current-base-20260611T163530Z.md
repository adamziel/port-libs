# XML/HTML5 DOM Global State Slice

Session: `plib-yu5xv`
Base: `446b499acc9f346b3816ee329ba01075bab773fd`

This slice extends native PHP `XmlHtmlDom` reviewer summaries with bounded HTML global state provenance. Elements that carry `lang`, `xml:lang`, `dir`, `translate`, `hidden`, `inert`, `contenteditable`, `draggable`, or `spellcheck` now expose a `globalState` summary while deterministic fragment serialization remains unchanged.

The summary preserves raw values and normalized valid states for enumerated attributes. Invalid `dir`, `translate`, `draggable`, and related editing-state values stay visible as invalid reviewer provenance without relaxing source parsing or invoking browser/sanitizer behavior.

Verification:
- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` -> `1 test files, 525 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests` -> `44 test files, 63945 assertions, 0 failures`

Accounting:
- `phpPass`: `3069 -> 3070`
- Focused XML/HTML DOM handoff checks: `+1`
