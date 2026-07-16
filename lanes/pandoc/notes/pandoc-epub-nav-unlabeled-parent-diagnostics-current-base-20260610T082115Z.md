# pandoc-epub-nav-unlabeled-parent-diagnostics-current-base-20260610T082115Z

Slice: `pandoc-epub-nav-unlabeled-parent-diagnostics-current-base-20260610T082115Z`

This slice extends the existing native PHP EPUB nav item diagnostics coverage.
`EpubReader` now emits a normalized
`nav-item-child-list-without-label` document diagnostic when a navigation list
item has child entries but no direct `a` or `span` label. The diagnostic rolls
up into section and document `unlabeledParentItemCount` fields.

Unlabeled parent items now keep an empty item title instead of inheriting text
from nested child entries. Child entries still keep their own titles and targets,
so review handoff can distinguish the malformed parent from its recoverable
children.

The regression stays inside the existing `reports EPUB nav item diagnostics for
package review` fixture. It does not increase `lane-status.json` `phpPass` or
the upstream manifest mapped-case denominator. It does not invoke Pandoc,
EPUBCheck, browser renderers, zip/unzip, external validators, online services,
live provider tests, or live-service provider tests.

Verification:

- `php -l lanes/pandoc/src/EpubReader.php`
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - 1 file, 3869 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 43 files, 59176 assertions, 0 failures

Status delta:

- `lane-status.json` `phpPass`: unchanged at `2948`
- `lane-status.json` suite progress: unchanged at `851`, with hidden-item and
  unlabeled-parent assertions recorded under the existing EPUB nav item
  diagnostics case
