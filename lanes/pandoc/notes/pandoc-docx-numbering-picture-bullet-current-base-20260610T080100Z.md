# pandoc-docx-numbering-picture-bullet-current-base-20260610T080100Z

Slice: `pandoc-docx-numbering-picture-bullet-current-base-20260610T080100Z`

This slice extends the native PHP DOCX reader's numbering relationship handoff
to preserve `w:numPicBullet` image relationship provenance. `DocxReader` now:

- reads numbering-part relationships from `word/_rels/numbering.xml.rels`
- resolves `v:imagedata r:id` targets used by `w:numPicBullet`
- attaches picture-bullet metadata to shared list AST nodes
- exposes declared picture bullets in `metadata.docxNumbering` and
  `importReport.numbering`
- lets existing WordPress list rendering emit `data-docx-numbering-picture-*`
  attributes for reviewer handoff

The regression fixture stores a numbering part with one `w:numPicBullet`, one
image relationship, and one media part. The imported bullet list keeps fallback
Markdown/list rendering while retaining relationship id, target part, content
type, byte count, title, and issue metadata for review.

This stays bounded to DOCX numbering relationship provenance. It does not invoke
Pandoc, Word, LibreOffice, zip/unzip, browser renderers, external validators,
online services, live provider tests, or live-service provider tests.

Verification:

- `php -l lanes/pandoc/src/DocxReader.php`
- `php -l lanes/pandoc/tests/DocxReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - 1 file, 4521 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 43 files, 59162 assertions, 0 failures

Status delta:

- `lane-status.json` `phpPass`: `2947 -> 2948`
- `lane-status.json` suite progress: `850 -> 851`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3121 -> 3122`
- `mappedDocxNumberingPictureBulletCases`: `0 -> 1`
- `docxNumberingPictureBulletAssertions`: `0 -> 33`
