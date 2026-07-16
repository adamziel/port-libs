# XML/HTML5 DOM image loading raw provenance

Slice: `xml-html5-dom-image-loading-raw-provenance-20260701`

`XmlHtmlDom` now carries raw image loading-policy attributes alongside the
normalized image review states:

- `imageLoadingRaw`
- `imageDecodingRaw`
- `imageFetchPriorityRaw`
- `imageCrossoriginRaw`
- `imageReferrerPolicyRaw`

Invalid image loading-policy attributes also expose structured
`imageLoadingIssues` while retaining the existing `imageLoadingIssueCodes`
surface. Serialization and WordPress raw HTML handoff remain unchanged.

This is bounded to direct XML/HTML5 DOM reviewer metadata for `<img>` elements.
It does not repeat sanitizer conversion of image policy attributes, srcset/base
URL resource review, image-map metadata, link/script loading policy metadata, or
browser loading/decoding behavior.

Verification:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomImageLoadingRawProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomImageLoadingRawProvenanceTest.php lanes/pandoc/tests/XmlHtmlDomSrcsetResourceReviewTest.php lanes/pandoc/tests/XmlHtmlDomMediaLoadingPolicyReviewTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
