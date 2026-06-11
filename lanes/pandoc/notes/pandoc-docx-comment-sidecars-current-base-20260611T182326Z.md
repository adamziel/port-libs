# DOCX Comment Sidecar Package Metadata

Slice `plib-nfqjy` extends the DOCX/OpenXML package-ingestion handoff for review-only comment sidecars without invoking Pandoc, Word, LibreOffice, office suites, zip/unzip, browser renderers, external validators, online services, live provider tests, or live-service provider tests.

The document relationship source inventory now reports `commentsIds` and `people` parts alongside footnotes, endnotes, comments, and commentsExtended. The handoff preserves relationship ids/types, resolved package targets, raw query/fragment suffixes, content types, root/item namespaces, item counts, durable comment id counts, and people author/provider/user id counts. OPC role preflight also recognizes the `comments-ids` and `people` singleton roles with their expected content types.

Verification on current base `c0cfa42e2`:

- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` passed: 1 test file, 5058 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` passed: 2 test files, 4905 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 44 test files, 65154 assertions, 0 failures.
