# XML/HTML/JATS/BITS Publication Metadata Review

This slice adds bounded native PHP review metadata for JATS/BITS publication
front matter in `XmlHtmlDom` without claiming direct reader parity.

Covered mapped case families:

- Serial identifiers and serial/book title records.
- Publisher name/location provenance.
- Front-matter permissions, license refs, and duplicate-license diagnostics.
- Article history dates with ISO normalization.
- Self URI, related article, and related object link targets without dereferencing.
- Volume, issue, page range, and elocation metadata.

Counters:

- `phpPass`: `3529 -> 3535`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST upstream.mapped`: `3453 -> 3459`
- `mappedXmlHtmlDomJatsPublicationMetadataCases`: `6`
- `xmlHtmlDomJatsPublicationMetadataAssertions`: `60`

Verification:

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`: 1 file, 4162 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`: 46 files, 83570 assertions, 0 failures
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check -- lanes/pandoc/src/XmlHtmlDom.php lanes/pandoc/tests/XmlHtmlDomTest.php lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`

No Pandoc binary, XML validator, browser renderer, JavaScript engine, online
sanitizer, external validator, online service, live provider test, or
live-service provider test was invoked.
