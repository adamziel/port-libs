# XML/HTML5 DOM Script/Style Side-Effect Provenance

Slice: `plib-wb1ea`

Base: `9c821d42a1800c1547f75c1e9ca0cde2757390df` (`origin/main` at slice start)

The XML/HTML5 DOM handoff now summarizes inert script and style side-effect
provenance for native PHP reviewer packets. Script summaries include external
versus inline resource classification, module type, source URL, async/defer/
nomodule state, crossorigin, integrity, referrer policy, fetch priority,
blocking tokens, nonce values, inline byte counts, and bounded inline previews.
Style summaries include media, title, disabled state, blocking tokens, nonce,
inline byte counts, bounded previews, and inert review policy.

No script or style is executed or fetched; the slice only exposes metadata from
the existing protected raw-text DOM parse and raw HTML WordPress handoff.

Verification on the starting base:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  passed 1 test file, 850 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed 44 test files, 66788 assertions, 0 failures.

Lane status movement: one XML/HTML5 DOM script/style side-effect PASS case
with 44 focused assertions; `phpPass` moves from 3134 to 3135 while `phpFail`
remains 0.
