# EPUB CSS Image-Set Resource Policy

Slice: `plib-5um9x.10`, EPUB3 CSS resource policy lane.

## Behavior

`EpubPackage` now treats CSS `image-set()` and `-webkit-image-set()` top-level
quoted string candidates as stylesheet package resource references. These
references feed the existing `stylesheetResources()` report and compact
WordPress import packet, using the same manifest, missing package part,
external URL, compression, encryption, and byte exposure policy fields already
used for `@import` and `url()` references.

`url()` entries inside `image-set()` continue to use the existing `url`
relation. MIME strings inside `type("...")` descriptors are not reported as
resource URLs.

## Evidence

- `php -l lanes/pandoc/src/EpubPackage.php`
  - No syntax errors detected.
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
  - No syntax errors detected.
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - Passed with `1 test files, 4224 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php lanes/pandoc/tests/EpubWriterTest.php`
  - Passed with `2 test files, 219 assertions, 0 failures`.

Focused assertion delta from the prior lane status: `+48`.
Lane `phpPass` moves from `431` to `432`.

## Scope

This is package import/export diagnostics only. It does not render CSS, compute
cascade/layout, fetch remote resources, execute JavaScript, authorize DRM,
decrypt protected resources, or cover non-EPUB formats. Remote `image-set()`
targets remain metadata-only diagnostics.

The requested supervisor note path,
`lanes/pandoc/notes/epub3-supervisor-20260625.md`, is not present on current
`origin/main`; the only local-history copy available for review is an EPUB2
scope note. Its CSS/layout parity exclusions are respected here by limiting the
change to static EPUB package resource URL discovery.
