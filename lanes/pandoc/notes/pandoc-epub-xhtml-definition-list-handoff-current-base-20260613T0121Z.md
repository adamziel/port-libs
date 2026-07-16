# EPUB XHTML Definition-List Handoff Current-Base Note

Date: 2026-06-13 UTC
Bead: `plib-ej919`
Base: `6dfacd7bba`
Upstream inventory: `jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83`

## Slice

Bounded native PHP EPUB3 direct package content handoff advanced by one XHTML spine slice. `EpubPackageReader` now maps linear spine XHTML `<dl>/<dt>/<dd>` content into shared `definition_list` AST nodes, preserving source `id`/`class` attributes, term text, inline strong/link content, package-local links, loose multi-block definitions, nested list bodies, and WordPress `<dl>` output.

This closes one direct EPUB3 structural/content handoff gap beyond manifest/spine diagnostics. It does not claim full EPUB3 package reader parity.

## Counters

| Counter | Before | After |
| --- | ---: | ---: |
| `phpPass` | 3,320 | 3,321 |
| `phpFail` | 0 | 0 |
| Mapped upstream cases | 3,279 | 3,280 |
| EPUB local passing evidence | 60 | 61 |

EPUB denominator: 9 static upstream EPUB-related rows in the accepted Pandoc inventory.
EPUB local numerator: 61 native PHP EPUB-focused evidence cases.
EPUB evidence ratio: 61 / 9 = 677.8%.

New slice counters:

| Counter | Value |
| --- | ---: |
| `mappedEpubXhtmlDefinitionListHandoffCases` | 1 |
| `epubXhtmlDefinitionListHandoffAssertions` | 26 |

## Verification

Passed:

| Command | Result |
| --- | --- |
| `php -l lanes/pandoc/src/EpubPackageReader.php` | No syntax errors |
| `php -l lanes/pandoc/tests/EpubPackageReaderTest.php` | No syntax errors |
| `php tools/run-tests.php lanes/pandoc/tests/EpubPackageReaderTest.php` | 1 file, 213 assertions, 0 failures |
| `php tools/run-tests.php lanes/pandoc/tests` | 45 files, 74,513 assertions, 0 failures |

No Pandoc binary, EPUBCheck, zip/unzip, ZipArchive, browser renderer, Node tooling, office suite, online service, live provider test, or external validator was invoked.

## Remaining EPUB Gaps

Verdict: partial, not shippable.

Remaining critical EPUB3 package reader gaps include broader XHTML structural/content coverage, XHTML table/section semantics, nav/NCX label provenance, OPF metadata propagation, package structural diagnostics, and media/resource handling. Continue with bounded direct EPUB3 parity slices and focused native PHP tests plus the full `lanes/pandoc/tests` gate.
