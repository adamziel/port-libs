## pandoc-epub3-package-core-current-base-20260606T073521Z

Lane: `pandoc`
Micro-slice: `pandoc-epub3-package-core-current-base-20260606T073521Z`
Accepted base: `10c8faa2bd4e18ec06eb4850c4a30e46d6ded63d`

### Behavior

This slice adds bounded native EPUB3 package handoff for OPF manifest
`media-overlay` bindings. `EpubReader` now attaches a compact
`mediaOverlayReference` report to the OPF manifest item, spine item, XHTML
asset, non-XHTML asset report item, import-report item, and WordPress raw HTML
block attributes. The report preserves the overlay id, resolved SMIL package
part, target part, media type, existence/encryption state, OPF duration
metadata, first text reference, item count, and diagnostics for missing overlay
manifest items or non-SMIL overlay manifest items.

The implementation reuses the existing native SMIL media-overlay parser and
duration handling. It does not add playback, audio fetching, remote resource
fetching, EPUBCheck validation, CSS cascade handling, XHTML-to-AST conversion,
or a Pandoc/Haskell runner path.

### Focused Evidence

Baseline before adding the focused case:

`php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`

Result: `1 test files, 1356 assertions, 0 failures`.

Red-first after adding the focused case:

`php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`

Result: `1 test files, 1357 assertions, 1 failures`; the new EPUB
media-overlay binding case failed because `mediaOverlayReference` was absent.

After implementation:

`php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`

Result: `1 test files, 1382 assertions, 0 failures`.

Example smoke:

`php lanes/pandoc/examples/wordpress-epub3-package-handoff.php --self-test`

Result: `epub3 package handoff self-test ok`.

Focused delta: `+1` PHP PASS case and `+26` net focused assertions for the
EPUB3 package core support row.

### Dependency Closure

No new support component is needed. The slice reuses lane-local native PHP
`EpubReader`, `ZipPackage`, `OpcPackagePath`, DOM/libxml XML parsing, existing
SMIL media-overlay parsing, and the WordPress EPUB3 package handoff example.

The upstream-runner blocker remains unchanged: full Pandoc runner parity still
requires a hydrated Pandoc checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83`
with Cabal project/package files and Haskell Tasty executable builds for
`test-pandoc` and `test-pandoc-lua-engine`.

No Pandoc, Cabal solver/build/test command, Haskell runner, zip/unzip,
ZipArchive, EPUBCheck, browser renderer, online sanitizer, online service,
live provider test, or live-service provider test was executed.

### Non-Overlap

This patch does not repeat accepted EPUB OCF container/rootfile parsing,
metadata.xml sidecar reporting, OPF metadata/manifest/spine parsing, unique-id
checks, page progression, fallback chains, bindings, remote-resource
diagnostics, cover-image provenance, guide/collection handling, package
vocabulary prefixes, nav/NCX parsing, XHTML spine raw-block handoff, encryption
preflight, SMIL parsing, clip timing, media duration extraction, remote SMIL
reference diagnostics, or EPUB CFI parsing. It owns only the package-layer
manifest `media-overlay` binding report and its handoff into existing package
review surfaces.

### Follow-Up

Keep XHTML-to-AST conversion, media extraction/export policy, remote-resource
policy, multiple rendition selection, landmarks/page-list navigation,
encrypted/obfuscated font preflight, EPUBCheck-style validation, CSS cascade
handling, and full upstream Haskell runner dependency closure as separate
bounded slices.
