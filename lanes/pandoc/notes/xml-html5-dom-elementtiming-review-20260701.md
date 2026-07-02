# XML/HTML5 DOM elementtiming review

`XmlHtmlDom::summarizeHtmlFragment()` now carries metadata-only review records for the HTML `elementtiming` attribute.

The summary preserves the raw value, exposes a trimmed review token only when it is a safe single identifier, records byte lengths, classifies the observed element as text, image, media, visual, resource, or container, and reports any associated `src`, `poster`, `href`, or `data` resource attribute. Empty and unsafe values produce explicit issue codes without invoking browser timing APIs, loading resources, or fetching URLs.

Validation for `plib-km3im`: `php -l` on `XmlHtmlDom.php` and `XmlHtmlDomElementTimingReviewTest.php`; `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomElementTimingReviewTest.php` with 1 file, 43 assertions, 0 failures. Broader post-rebase validation is recorded in `lane-status.json`.
