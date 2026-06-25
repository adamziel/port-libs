# pandoc WordPress Scenario

Document conversion kernel for Data Liberation imports and block-oriented output.

Latest scenario:
`tests/EpubReaderTest.php` now exercises EPUB3 nav TOC list-structure
validation. `EpubReader` reports `toc` nav links that are not contained in
list item entries, matching the list-item based TOC import behavior while
preserving valid ordered-list entries. The regression covers a `nav.xhtml`
with a dangling direct TOC anchor plus a valid `ol`/`li`/`a` entry; read-back
reports `invalid-nav-toc-link-parent` with id/href/path/navId/type/linkHref/
label context, avoids missing toc, missing toc entry, and missing
link-fragment cascades, imports only the valid listed TOC entry, and still
imports readable spine content. This gives WordPress/Data Liberation imports
stricter EPUB3 nav TOC structure review signals without losing body import or
recoverable navigation provenance. This remains lane-local native PHP without
invoking upstream Pandoc, live fetching, shelling out to converters, EPUBCheck,
full fixture corpus parity, arbitrary multi-rendition package graph validation
beyond covered generated alternate package-link/container sidecar paths, DRM
decryption, XML signature cryptographic validation, broader TeX/MathML parser
parity, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` now exercises EPUB3 nav document XHTML root and
namespace validation. `EpubReader` reports nav resources whose document root is
not an XHTML `html` element, whose `html` root uses the wrong namespace, and
whose `nav` elements use a non-XHTML namespace while preserving local-name
recovery for readable navigation. The regression covers a manifest nav item
whose `nav.xhtml` uses a foreign `book` root and foreign-namespace `nav` local
names but still links to a valid spine resource; read-back reports
`invalid-nav-document-root` and `invalid-nav-element-namespace` with id/href/
path/element/namespace/expected context, avoids missing toc, missing toc entry,
and missing link-resource cascades, preserves the recovered TOC entry, and
still imports readable spine content. This gives WordPress/Data Liberation
imports stricter EPUB3 nav document namespace review signals without losing
body import or recoverable navigation provenance. This remains lane-local
native PHP without invoking upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, arbitrary multi-rendition
package graph validation beyond covered generated alternate package-link/
container sidecar paths, DRM decryption, XML signature cryptographic
validation, broader TeX/MathML parser parity, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` now exercises NCX navigation child-order and
content cardinality validation for EPUB3 packages that still include NCX
compatibility navigation. `EpubReader` reports out-of-order direct children in
NCX `navMap`, `pageList`, `navList`, `navPoint`, `pageTarget`, and
`navTarget` elements, plus duplicate direct `content` children on NCX
navigation entries while preserving recoverable metadata and navigation. The
regression covers reordered but otherwise allowed NCX children and duplicate
`content` children in TOC, page-list, and nav-list contexts; read-back reports
`invalid-ncx-navigation-child-order` and `duplicate-ncx-content` with path/
element/childElement/position/id/type/text/contentSrc/previous context, avoids
unexpected-child, missing-label, empty-label, missing-content, and spine-target
cascades, preserves the valid EPUB3 nav TOC plus recovered NCX uid/title/TOC/
page-list/nav-list entries, and still imports readable spine content. This
gives WordPress/Data Liberation imports stricter NCX navigation content-model
review signals without losing body import or recoverable navigation
provenance. This remains lane-local native PHP without invoking upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, arbitrary multi-rendition package graph validation beyond
covered generated alternate package-link/container sidecar paths, DRM
decryption, XML signature cryptographic validation, broader TeX/MathML parser
parity, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` now exercises NCX content-model validation for
EPUB3 packages that still include NCX compatibility navigation. `EpubReader`
reports unexpected correctly namespaced NCX child elements under `ncx`, `head`,
`navMap`, `navPoint`, `pageList`, `pageTarget`, `navList`, and `navTarget`
while preserving recoverable metadata and navigation. The regression covers
unexpected NCX children in root, metadata, TOC, page-list, and nav-list
contexts; read-back reports `invalid-ncx-child-element` with path/element/
childElement/id/type/text context, avoids uid/label/content/spine cascades,
preserves the valid EPUB3 nav TOC plus recovered NCX uid/title/TOC/page-list/
nav-list entries, and still imports readable spine content. This gives
WordPress/Data Liberation imports stricter NCX content-model review signals
without losing body import or recoverable navigation provenance. This remains
lane-local native PHP without invoking upstream Pandoc, live fetching, shelling
out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graph validation beyond covered generated alternate
package-link/container sidecar paths, DRM decryption, XML signature
cryptographic validation, broader TeX/MathML parser parity, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` now exercises NCX ID validation for EPUB3 packages
that still include NCX compatibility navigation. `EpubReader` reports
malformed NCX `id` attributes and duplicate valid NCX `id` attributes across
correctly namespaced NCX documents while preserving recoverable metadata and
navigation. The regression covers malformed IDs on `docAuthor`, `navPoint`,
and `navList`, plus a `pageTarget` that duplicates a valid `navPoint` ID;
read-back reports `invalid-ncx-id` and `duplicate-ncx-id` with path/element/
parent/id/type/text and previous element/type/text context, avoids
uid/title/label/content/spine cascades, preserves the valid EPUB3 nav TOC plus
recovered NCX uid/title/author/TOC/page-list/nav-list entries, and still
imports readable spine content. This gives WordPress/Data Liberation imports
stricter NCX ID review signals without losing body import or recoverable
navigation provenance. This remains lane-local native PHP without invoking
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, arbitrary multi-rendition package graph validation
beyond covered generated alternate package-link/container sidecar paths, DRM
decryption, XML signature cryptographic validation, broader TeX/MathML parser
parity, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` now exercises NCX root attribute validation for
EPUB3 packages that still include NCX compatibility navigation. `EpubReader`
reports missing NCX root `version`, invalid NCX root `version`, and invalid
`xml:lang`/`lang` values on correctly namespaced NCX roots while preserving
recoverable NCX metadata and navigation. The regression covers one NCX
resource missing `version="2005-1"` with invalid root `xml:lang` and a second
NCX resource declaring legacy `version="2004-2"` with invalid root `lang`;
read-back reports `missing-ncx-version`, `invalid-ncx-version`, and
`invalid-ncx-root-language` with path/element/version/expectedVersion/
attribute/language context, avoids uid/title/label/content/spine cascades,
preserves the valid EPUB3 nav TOC plus recovered NCX uid/title/navigation
entries from both NCX resources, and still imports readable spine content.
Valid NCX reader fixtures now declare `version="2005-1"`, matching writer
output and keeping expected-valid fixtures out of the new diagnostic path.
This gives WordPress/Data Liberation imports stricter NCX root attribute
review signals without losing body import or recoverable navigation
provenance. This remains lane-local native PHP without invoking upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, arbitrary multi-rendition package graph validation beyond
covered generated alternate package-link/container sidecar paths, DRM
decryption, XML signature cryptographic validation, broader TeX/MathML parser
parity, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` now exercises NCX label language validation for
EPUB3 packages that still include NCX compatibility navigation. `EpubReader`
reports invalid `xml:lang`/`lang` values on NCX document labels, navigation
entry labels, and `pageList`/`navList` container labels while preserving
recovered labels and local targets. The regression covers invalid language
tags on `docTitle`, `docAuthor` text, `navPoint` `navLabel`, `pageTarget`
label text, `navTarget` `navLabel`, `pageList` container `navLabel`, and
`navList` container label text; read-back reports
`invalid-ncx-doc-title-language`, `invalid-ncx-doc-author-language`,
`invalid-ncx-nav-label-language`, and
`invalid-ncx-container-nav-label-language` with path/element/id/navType/type/
text/childElement/attribute/language context, avoids missing-label,
empty-label, missing-content, and spine-target cascades, preserves the valid
EPUB3 nav TOC plus recovered NCX uid/title/author/page-list/nav-list text and
targets, and omits invalid language tags from normalized NCX metadata. This
gives WordPress/Data Liberation imports stricter NCX label language review
signals without losing body import or recoverable navigation provenance. This
remains lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graph validation beyond covered generated alternate
package-link/container sidecar paths, DRM decryption, XML signature
cryptographic validation, broader TeX/MathML parser parity, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` now exercises NCX document metadata namespace
validation for EPUB3 packages that still include NCX compatibility navigation.
`EpubReader` reports wrong-namespace NCX `head` `meta`, `docTitle` text,
`docAuthor` text, and `pageList`/`navList` container label children while
preserving local-name recovery for import provenance. The regression covers a
valid NCX root and correctly namespaced root containers whose metadata and
container labels use DAISY NCX local names from a foreign namespace; read-back
reports `invalid-ncx-head-meta-namespace`,
`invalid-ncx-doc-title-text-namespace`,
`invalid-ncx-doc-author-text-namespace`,
`invalid-ncx-container-nav-label-namespace`, and
`invalid-ncx-container-nav-label-text-namespace` with path/element/id/type/
text/name/content/childElement/namespace context, avoids parent namespace,
missing uid, empty title, missing/empty label, missing content, and
spine-target cascades, preserves the valid EPUB3 nav TOC plus recovered NCX
uid/title/author/page-list/nav-list metadata, and still imports readable spine
content. This gives WordPress/Data Liberation imports stricter NCX document
metadata namespace review signals without losing body import or recoverable
navigation provenance. This remains lane-local native PHP without invoking
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, arbitrary multi-rendition package graph validation
beyond covered generated alternate package-link/container sidecar paths, DRM
decryption, XML signature cryptographic validation, broader TeX/MathML parser
parity, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` now exercises NCX navigation child namespace
validation for EPUB3 packages that still include NCX compatibility navigation.
`EpubReader` reports wrong-namespace `navLabel`, label `text`, and `content`
children under correctly namespaced NCX navigation entries while preserving
local-name recovery for import provenance. The regression covers valid NCX
root/navigation parents and three malformed children: a wrong-namespace
`navLabel` on a `navPoint`, a wrong-namespace `text` child inside a
`pageTarget` `navLabel`, and a wrong-namespace `content` child inside a
`navTarget`; read-back reports `invalid-ncx-nav-label-namespace`,
`invalid-ncx-nav-label-text-namespace`, and `invalid-ncx-content-namespace`
with path/navType/id/text/childElement/namespace context, avoids parent
namespace, missing-label, empty-label, missing-content, and spine-target
cascades, preserves the valid EPUB3 nav TOC plus recovered NCX
TOC/page-list/nav-list entries, and still imports readable spine content.
This gives WordPress/Data Liberation imports stricter NCX navigation child
namespace review signals without losing body import or recoverable navigation
provenance. This remains lane-local native PHP without invoking upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, arbitrary multi-rendition package graph validation beyond
covered generated alternate package-link/container sidecar paths, DRM
decryption, XML signature cryptographic validation, broader TeX/MathML parser
parity, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` now exercises NCX namespace lookalike validation
for EPUB3 packages that still include NCX compatibility navigation.
`EpubReader` reports NCX root elements, direct root child containers, and
navigation elements that use NCX local names from the wrong XML namespace
while still preserving local-name recovery for import provenance. The
regression covers a valid EPUB3 `nav.xhtml` TOC, a valid NCX root containing
wrong-namespace `head`/`pageList`/`navPoint` lookalikes, and a second NCX
resource whose root local name is `ncx` but whose root namespace is wrong
while its children use the DAISY NCX namespace; read-back reports
`invalid-ncx-root-namespace`, `invalid-ncx-child-namespace`, and
`invalid-ncx-navigation-namespace` with path/element/namespace/expectedNamespace
and available id/text context, avoids uid/label/content/spine cascades,
preserves imported NCX uid/title/page-list metadata from recovered local-name
content, preserves the valid EPUB3 nav TOC plus recovered NCX navigation
entries, and still imports readable spine content. This gives WordPress/Data
Liberation imports stricter NCX namespace review signals without losing body
import or recoverable navigation provenance. This remains lane-local native
PHP without invoking upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, arbitrary multi-rendition
package graph validation beyond covered generated alternate
package-link/container sidecar paths, DRM decryption, XML signature
cryptographic validation, broader TeX/MathML parser parity, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` now exercises NCX root child order and duplicate
container validation for EPUB3 packages that still include NCX compatibility
navigation. `EpubReader` reports duplicate NCX `head`, `docTitle`, `navMap`,
and `pageList` singleton containers, plus direct NCX root children that
violate the expected `head`, `docTitle`, `docAuthor`, `navMap`, `pageList`,
`navList` content-model order. The regression covers valid readable spine
content, a valid EPUB3 `nav.xhtml` TOC, a valid first NCX
`head`/`navMap`/`pageList`, late `docTitle`/`docAuthor` children, and
duplicate `head`/`docTitle`/`navMap`/`pageList` containers; read-back reports
`duplicate-ncx-head`, `duplicate-ncx-doc-title`, `duplicate-ncx-nav-map`,
`duplicate-ncx-page-list`, and `invalid-ncx-child-order` with
path/element/position/previous-position context, avoids missing/empty
container, playOrder, and spine-target cascades, preserves imported NCX
uid/title/author/page-list metadata, preserves both the EPUB3 nav TOC and
first valid NCX navMap entry, and still imports readable spine content. This
gives WordPress/Data Liberation imports stricter NCX root content-model review
signals without losing body import or valid navigation provenance. This
remains lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graph validation beyond covered generated alternate
package-link/container sidecar paths, DRM decryption, XML signature
cryptographic validation, broader TeX/MathML parser parity, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` now exercises empty NCX navigation-container
validation for EPUB3 packages that still include NCX compatibility
navigation. `EpubReader` reports empty `navMap`, `pageList`, and `navList`
containers when those elements are present without their required direct
navigation entry children. The regression covers a valid EPUB3 `nav.xhtml`
TOC, an empty NCX `navMap`, an empty labeled `pageList`, and an empty labeled
`navList` carrying id/class metadata; read-back reports
`empty-ncx-nav-map`, `empty-ncx-page-list`, and `empty-ncx-nav-list` with
path/element and available id/type/text context, avoids missing `navMap` and
entry-level label/content/spine cascades, preserves the valid EPUB3 nav TOC
entry, preserves labeled empty nav-list metadata, does not invent NCX
page-list entries, and still imports readable spine content. This gives
WordPress/Data Liberation imports stricter NCX navigation-container review
signals without losing body import or inventing missing navigation entries.
This remains lane-local native PHP without invoking upstream Pandoc, live
fetching, shelling out to converters, EPUBCheck, full fixture corpus parity,
arbitrary multi-rendition package graph validation beyond covered generated
alternate package-link/container sidecar paths, DRM decryption, XML signature
cryptographic validation, broader TeX/MathML parser parity, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` now exercises NCX head numeric metadata validation
for EPUB3 packages that still include NCX compatibility navigation.
`EpubReader` reports invalid `dtb:depth`, `dtb:totalPageCount`, and
`dtb:maxPageNumber` content values when those NCX head records are present,
instead of silently dropping them from normalized metadata. The regression
covers valid NCX uid/docTitle/navMap data with `dtb:depth="0"`,
`dtb:totalPageCount="-1"`, and `dtb:maxPageNumber="many"`; read-back reports
`invalid-ncx-depth`, `invalid-ncx-total-page-count`, and
`invalid-ncx-max-page-number` with path/name/value context, avoids
uid/label/spine cascades for otherwise valid NCX data, preserves raw head
records, does not synthesize normalized invalid numeric metadata, and still
imports readable spine content. This gives WordPress/Data Liberation imports
stricter NCX head metadata review signals without losing raw provenance or
body import. This remains lane-local native PHP without invoking upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, arbitrary multi-rendition package graph validation beyond
covered generated alternate package-link/container sidecar paths, DRM
decryption, XML signature cryptographic validation, broader TeX/MathML parser
parity, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` now exercises NCX document-structure validation
for EPUB3 packages that still include NCX compatibility navigation.
`EpubReader` reports NCX documents with the wrong root element, missing
`head`, missing `dtb:uid` metadata, missing `docTitle`, empty `docTitle`
text, and missing `navMap` before entry-level NCX diagnostics run. The
regression covers a valid EPUB3 `nav.xhtml`, a structurally incomplete NCX
resource, and a second NCX resource with a non-`ncx` root; read-back reports
`missing-ncx-uid`, `empty-ncx-doc-title`, `missing-ncx-nav-map`, and
`invalid-ncx-root` with path/root context, avoids entry-level
label/content/spine cascades, preserves the valid EPUB3 nav TOC entry, does
not synthesize missing NCX uid/docTitle metadata, and still imports readable
spine content. This gives WordPress/Data Liberation imports stricter NCX
document-level review signals without losing body import or inventing NCX
metadata. This remains lane-local native PHP without invoking upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, arbitrary multi-rendition package graph validation beyond
covered generated alternate package-link/container sidecar paths, DRM
decryption, XML signature cryptographic validation, broader TeX/MathML parser
parity, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` now exercises NCX navigation label validation for
EPUB3 packages that still include NCX compatibility navigation.
`EpubReader` reports NCX navigation elements missing a direct `navLabel`
child and `navLabel` elements that do not contain usable text, covering
`navPoint`, `pageTarget`, and `navTarget` entries without rewriting imported
navigation metadata. The regression covers a valid NCX `navPoint`, a
`navPoint` missing `navLabel`, a typed `pageTarget` with empty label text,
and a `navTarget` missing `navLabel`; read-back reports
`missing-ncx-nav-label` and `empty-ncx-nav-label` with toc/page-list/nav-list
context, avoids content-src and spine-target cascades for valid local
targets, does not import unlabeled NCX entries into TOC/page-list/nav-list
metadata, and still imports readable spine content. This gives
WordPress/Data Liberation imports stricter NCX navigation review signals
without rewriting or inventing labels. This remains lane-local native PHP
without invoking upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, arbitrary multi-rendition package
graph validation beyond covered generated alternate package-link/container
sidecar paths, DRM decryption, XML signature cryptographic validation,
broader TeX/MathML parser parity, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` now exercises NCX `pageTarget value`
validation for EPUB3 packages that still include NCX compatibility
navigation. `EpubReader` reports missing or blank NCX `pageTarget value`
attributes while preserving imported NCX page-list entries exactly as
authored. The regression covers a valid typed pageTarget with `value="1"`
and a typed pageTarget missing `value`; read-back reports
`missing-ncx-page-target-value` with page-list context/type/text, avoids
missing-type and spine-target cascades for valid local targets, preserves the
authored value on the valid entry, does not synthesize a value for the
invalid entry, and still imports readable spine content. This gives
WordPress/Data Liberation imports stricter NCX page-list review signals
without rewriting or inventing pageTarget value data. This remains lane-local
native PHP without invoking upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, arbitrary multi-rendition
package graph validation beyond covered generated alternate
package-link/container sidecar paths, DRM decryption, XML signature
cryptographic validation, broader TeX/MathML parser parity, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` now exercises NCX `pageTarget type`
validation for EPUB3 packages that still include NCX compatibility
navigation. `EpubReader` reports missing NCX `pageTarget type` attributes
and invalid values outside `front`, `normal`, or `special`, while preserving
the imported NCX page-list entries exactly as authored. Existing NCX
path/order fixtures now declare valid `type="normal"` where their target is
not pageTarget type validation. The regression covers a valid `front`
pageTarget, a missing type, and an invalid `appendix` type; read-back reports
targeted `missing-ncx-page-target-type` and `invalid-ncx-page-target-type`
diagnostics, carries page-list context/value/text, avoids spine-target
cascades for valid local targets, and still imports readable spine content.
This gives WordPress/Data Liberation imports stricter NCX page-list review
signals without rewriting or inventing pageTarget type data. This remains
lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graph validation beyond covered generated alternate
package-link/container sidecar paths, DRM decryption, XML signature
cryptographic validation, broader TeX/MathML parser parity, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` now exercises package `unique-identifier` scope
validation for EPUB3 packages with collection-scoped identifiers.
`EpubReader` requires `unique-identifier` to resolve to a direct package
metadata `dc:identifier`, rather than accepting a same-id element elsewhere
in the primary package tree. The regression imports an EPUB whose package
metadata has a valid identifier while `unique-identifier` points at a
collection-scoped `dc:identifier`; read-back reports
`invalid-unique-identifier-target` with `not-package-metadata`, preserves the
collection content-model diagnostic, does not misreport the id as missing,
and still imports readable spine content. This gives WordPress/Data
Liberation imports stricter package identity diagnostics without losing body
content from imperfect EPUBs. This remains lane-local native PHP without
invoking upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, arbitrary multi-rendition package
graph validation beyond covered generated alternate package-link/container
sidecar paths, DRM decryption, XML signature cryptographic validation,
broader TeX/MathML parser parity, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` now exercises generated NCX page-list derivation
from the same pagebreak markers used for generated EPUB3 nav page-lists.
`EpubWriter` feeds generated pagebreak-derived page-list entries into optional
NCX `pageList`/`pageTarget` output when explicit `epubPageListEntries`
metadata is absent, and maps generated EPUB nav `pagebreak` entries to NCX
`type="normal"` page targets. `EpubReader` de-duplicates merged XHTML nav and
NCX page-list entries by page target identity, preserving XHTML `pagebreak`
semantics and NCX `playOrder` metadata. The regressions write primary and
generated alternate EPUB3 packages with pagebreak spans, generated
`nav.xhtml` page-list sections, and generated NCX page lists. Read-back
preserves `epubPageListEntries` with NCX play order and does not report
duplicate page-list entries, missing page-list, non-pagebreak target,
spine-target, fragment, or value-mismatch diagnostics. This lets
WordPress/Data Liberation exports emit source pagebreak spans once and have
both modern EPUB3 nav and optional NCX page navigation generated from the same
spine content. This remains lane-local native PHP without invoking upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, arbitrary multi-rendition package graph validation beyond
covered generated alternate package-link/container sidecar paths, DRM
decryption, XML signature cryptographic validation, broader TeX/MathML parser
parity, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` now exercises generated page-list nav derivation
from pagebreak markers for primary and generated alternate EPUB3 packages.
`EpubWriter` derives page-list entries from generated spine XHTML when
explicit `epubPageListEntries` metadata is absent, reads labels from `title`,
`aria-label`, or visible pagebreak text, falls back to page order for
unlabeled pagebreaks, skips markers without `id`, and keeps explicit page-list
metadata authoritative. The regressions write a primary EPUB3 package and an
`ALT/generated-pages.opf` alternate rootfile whose spine bodies contain
pagebreak spans but no page-list entries. Generated nav documents now include
page-list links to real pagebreak fragments, read-back preserves
`epubPageListEntries`, and neither package reports missing page-list,
non-pagebreak target, spine-target, fragment, or value-mismatch diagnostics.
This lets WordPress/Data Liberation exports preserve source pagebreak spans as
modern EPUB3 page navigation without duplicating page-list metadata by hand.
This remains lane-local native PHP without invoking upstream Pandoc, live
fetching, shelling out to converters, EPUBCheck, full fixture corpus parity,
arbitrary multi-rendition package graph validation beyond covered generated
alternate package-link/container sidecar paths, DRM decryption, XML signature
cryptographic validation, broader TeX/MathML parser parity, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubWriterTest.php` now exercises guide-derived nav landmarks for
generated EPUB3 packages. `EpubWriter` derives landmarks from sanitized OPF
guide references when explicit landmark metadata is absent, maps guide `text`
to landmark `bodymatter`, preserves cover/toc guide types, validates local
resource and fragment targets through packaged chapter/nav/resource paths, and
keeps explicit `epubLandmarkEntries` authoritative. The regression writes a
primary EPUB3 package with `epubGuideReferences` only plus a generated
alternate rootfile with structured guide/cover metadata. Generated primary and
alternate `nav.xhtml` files now include cover/bodymatter/toc landmarks, OPF
guide references remain intact, read-back preserves `epubLandmarkEntries` and
`epubGuideReferences`, and neither package reports missing landmark
link/type/resource/fragment diagnostics. This lets WordPress/Data Liberation
exports created from legacy guide metadata expose semantic landmarks to modern
EPUB3 nav consumers without duplicating landmark metadata. This remains
lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graph validation beyond covered generated alternate
package-link/container sidecar paths, DRM decryption, XML signature
cryptographic validation, broader TeX/MathML parser parity, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubWriterTest.php` now exercises generated raw XHTML scripted/remote
required manifest property inference for both primary and alternate rootfiles.
`EpubWriter` treats raw generated forms and `javascript:` URL hooks as
`scripted`, and reuses the standalone resource remote-reference scanner for
generated raw HTML, including `action`/`formaction`, inline CSS `url()`,
remote link/base/SVG href paths, and base-relative remote references. The
regression writes a primary EPUB3 package plus `ALT/raw-required.opf` with raw
XHTML forms, JavaScript links, and inline remote CSS without explicit
`spineManifestProperties`. Generated primary and alternate OPFs emit
`properties="scripted remote-resources"` on their chapter items, read-back
preserves inferred `manifestProperties`, and neither package reports missing
required properties. This lets WordPress/Data Liberation exports author
generated primary and alternate EPUB3 renditions with interactive raw XHTML
body content without manual manifest property overrides. This remains
lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graph validation beyond covered generated alternate
package-link/container sidecar paths, DRM decryption, XML signature
cryptographic validation, broader TeX/MathML parser parity, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubWriterTest.php` now exercises generated alternate mixed-XHTML
required manifest property inference. `EpubWriter` now detects `epub:switch`
markup in generated spine ASTs and emits the `switch` manifest property
alongside `mathml`, `svg`, `scripted`, and `remote-resources`. The regression
writes `ALT/mixed-required.opf` with generated XHTML containing TeX-to-MathML
output, inline SVG, script content, `epub:switch` markup, and a remote image
without explicit `spineManifestProperties`. Generated OPF output emits
`properties="mathml svg scripted switch remote-resources"` only on the
alternate chapter item, read-back preserves inferred `manifestProperties`, and
neither primary nor alternate diagnostics report missing required properties.
This lets WordPress/Data Liberation exports author generated alternate EPUB3
renditions with mixed rich XHTML feature content without manual manifest
property overrides. This remains lane-local native PHP without invoking
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, arbitrary multi-rendition package graph validation
beyond covered generated alternate package-link/container sidecar paths, DRM
decryption, XML signature cryptographic validation, broader TeX/MathML parser
parity, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` now exercises generated alternate package-link graph
sidecar isolation. The regression writes `ALT/package-links.opf` and
`MOBI/package-links.opf` with separate package metadata links, collection
metadata links, direct collection sidecar links, voicing audio sidecars, mixed
alias shapes, readable XHTML, and package-scoped payloads. Generated OPFs
render only their own link graph, keep JSON/audio package-link sidecars out of
manifests and out of the primary OPF, package payloads land under the correct
alternate directories, and read-back preserves metadata/collection link
summaries and extracted payloads without package-link diagnostics or
cross-package leakage. This lets WordPress/Data Liberation exports author
normal primary EPUB3 packages with multiple generated alternate renditions that
each retain OPF metadata/collection sidecar provenance. This remains lane-local
native PHP without invoking upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, arbitrary multi-rendition
package graph validation beyond covered generated alternate package-link/
container sidecar paths, DRM decryption, XML signature cryptographic
validation, broader TeX/MathML parser parity, or EPUB2-specific output
behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises split fixed-layout alternate
media-overlay aliases. The regression writes `ALT/fixed-overlay.opf` with
package-level fixed-layout rendition metadata, page-progression direction,
per-itemref viewport refinements, two generated XHTML spine pages, two
generated SMIL overlays with default `epub:textref` values, and packaged audio
resources. Generated OPF output keeps `media-overlay` manifest attributes
aligned to both pages, preserves fixed-layout itemref properties and viewport
refinements, packages both SMIL and audio resources, and keeps alternate
overlay resources out of the primary OPF. Read-back preserves rendition
metadata, spine `mediaOverlay` IDs, page spreads, orientations, per-page
viewports, overlay summaries, audio/text targets, payload extraction, and
alternate body text without missing overlay diagnostics. This lets
WordPress/Data Liberation exports author generated alternate fixed-layout
EPUB3 renditions with concise per-page media overlay metadata while retaining
package-level provenance. This remains lane-local native PHP without invoking
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, arbitrary multi-rendition package graph validation
beyond covered rootfile/container link sidecar paths, DRM decryption, XML
signature cryptographic validation, broader TeX/MathML parser parity, or
EPUB2-specific output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises multiple generated alternate
rootfiles in one EPUB3 container graph. The regression writes a primary export
with `ALT/print.opf` and `MOBI/mobile.opf`, each carrying independent package
metadata, nav documents, readable spine XHTML, and CSS resources, plus two OCF
container-link JSON sidecars that refine the matching rootfile IDs. Generated
`META-INF/container.xml` preserves both alternate rootfiles and both sidecar
links, generated OPFs reference only their own package resources, ZIP payloads
land in the correct package directories, and read-back preserves rootfile/link
metadata, sidecar payloads, alternate readable resources, alternate body text,
and resource payload isolation without missing container link, rootfile, or
manifest diagnostics. This lets WordPress/Data Liberation exports author
normal primary EPUB3 packages with multiple generated alternate renditions and
package-scoped resource provenance in one export. This remains lane-local
native PHP without invoking upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, arbitrary multi-rendition
package graph validation beyond covered rootfile/container link sidecar paths,
DRM decryption, XML signature cryptographic validation, broader TeX/MathML
parser parity, or EPUB2-specific output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises primary/alternate direct resource
alias isolation. The regression writes a normal primary EPUB3 export with
direct `resources` and `epubResources` CSS/image payloads, plus a generated
`ALT/resources-alias.opf` alternate package with its own direct CSS/image
aliases and XML `resourcePayloads`. Generated OPFs reference only their own
manifest resources, ZIP payloads land at their package paths, and generated
alternate navigation targets the alternate spine. Read-back keeps primary
payloads out of `epubAlternateRootfilePackages` and alternate payloads out of
the primary payload map without missing manifest diagnostics. This lets
WordPress/Data Liberation exports author normal primary and alternate EPUB3
packages with compact direct resource metadata while retaining package-level
resource provenance. This remains lane-local native PHP without invoking
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, arbitrary multi-rendition package graph validation
beyond covered rootfile/container link sidecar paths, DRM decryption, XML
signature cryptographic validation, broader TeX/MathML parser parity, or
EPUB2-specific output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises primary split-spine media overlay
alias arrays. The regression writes a normal primary EPUB3 export with
`splitLevel=1`, package-level media metadata, compact `mediaOverlays` entries
for `chapter.xhtml` and `chapter-2.xhtml`, and direct `resources`/
`epubResources` metadata payloads for audio. Generated `OEBPS/package.opf`
emits per-page `media-overlay` manifest links, refined media duration and
playback metadata, generated SMIL overlay resources, and packaged audio
items. Generated SMIL sequences now receive a default `epub:textref` from the
overlay content path when callers omit one. Read-back preserves media overlay
summaries, spine `mediaOverlay` IDs, package media metadata, resource payloads,
and body content through the reconstructed document AST without missing media
overlay, sequence textref, duration, text, or audio diagnostics. This lets
WordPress/Data Liberation exports author normal multi-page narrated EPUB3
packages with concise per-spine media overlay metadata. This remains
lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graph validation beyond covered rootfile/container
link sidecar paths, DRM decryption, XML signature cryptographic validation,
broader TeX/MathML parser parity, or EPUB2-specific output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises primary split-spine manifest
attribute alias arrays. The regression writes a normal primary EPUB3 export
with `splitLevel=1`, compact `spineManifestIds`, `spineManifestProperties`,
and `spineManifestAttributes` arrays, plus packaged fallback XHTML and CSS
payloads. Generated `OEBPS/package.opf` emits independent manifest properties
and `fallback`/`fallback-style` attributes for `chapter.xhtml` and
`chapter-2.xhtml`; generated `nav.xhtml` targets those split pages and the
fallback resources are packaged under `OEBPS/text/` and `OEBPS/styles/`.
Read-back preserves manifest properties, fallback IDs, fallback paths/media
types, `fallbackStyle`, and body content through the reconstructed document
AST without missing or invalid fallback diagnostics. This lets
WordPress/Data Liberation exports author normal multi-page EPUB3 packages
with concise per-spine manifest fallback metadata. This remains lane-local
native PHP without invoking upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, arbitrary multi-rendition
package graph validation beyond covered rootfile/container link sidecar paths,
DRM decryption, XML signature cryptographic validation, broader TeX/MathML
parser parity, or EPUB2-specific output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises primary split-spine language,
direction, and viewport alias arrays. The regression writes a normal primary
EPUB3 export with `splitLevel=1` and compact `spineLanguages`,
`spineDirections`, and `viewports` arrays. Generated `chapter.xhtml` and
`chapter-2.xhtml` emit independent `lang`/`xml:lang`/`dir` root attributes and
viewport meta tags, generated `nav.xhtml` targets those split pages, and each
page keeps the other's localization and viewport metadata out. Read-back
preserves per-page language, direction, viewport records, aggregated
`epubViewports`, and body content through the reconstructed document AST. This
lets WordPress/Data Liberation exports author normal multi-page EPUB3 packages
with concise per-spine localization and fixed-layout viewport metadata. This
remains lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graph validation beyond covered rootfile/container
link sidecar paths, DRM decryption, XML signature cryptographic validation,
broader TeX/MathML parser parity, or EPUB2-specific output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises primary split-spine manifest and
itemref alias arrays. The regression writes a normal primary EPUB3 export with
`splitLevel=1` and compact `spineManifestIds`, `spineItemIds`, and
`spineItemProperties` arrays. Generated `OEBPS/package.opf` emits custom
primary XHTML manifest item IDs, custom itemref IDs, and
page-spread/layout/flow itemref properties for `chapter.xhtml` and
`chapter-2.xhtml`; generated `nav.xhtml` targets those split pages. Read-back
preserves idrefs, itemref IDs, href/path provenance, property tokens, derived
rendition metadata, and body content through the reconstructed document AST
without missing or duplicate spine diagnostics. This lets WordPress/Data
Liberation exports author normal multi-page EPUB3 packages with concise spine
manifest/itemref arrays. This remains lane-local native PHP without invoking
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, arbitrary multi-rendition package graph validation
beyond covered rootfile/container link sidecar paths, DRM decryption, XML
signature cryptographic validation, broader TeX/MathML parser parity, or
EPUB2-specific output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises primary split-spine XHTML metadata
alias arrays. The regression writes a normal primary EPUB3 export with
`splitLevel=1` and compact `spineRootAttributes`, `spineBodyAttributes`,
`spineHeadTitles`, `spineHeadMetas`, `spineHeadBases`, `spineHeadLinks`,
`spineHeadStyles`, and `spineHeadScripts` arrays. Generated output emits
independent title/root/body/head metadata for `chapter.xhtml` and
`chapter-2.xhtml` from direct and nested alias shapes, infers
`scripted remote-resources` manifest properties, filters duplicate viewport
head metadata, and keeps `nav.xhtml` targets aligned with the split pages.
Read-back preserves the per-page head titles, root/body attributes, head
metas/bases/links/styles/scripts, and body content through the reconstructed
document AST. This lets WordPress/Data Liberation exports author normal
multi-page EPUB3 packages with concise XHTML metadata arrays. This remains
lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graph validation beyond covered rootfile/container
link sidecar paths, DRM decryption, XML signature cryptographic validation,
broader TeX/MathML parser parity, or EPUB2-specific output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises generated alternate-rootfile
split-spine XHTML metadata alias arrays. The regression writes a structured
`ALT/xhtml-aliases.opf` alternate rendition with two generated XHTML spine
pages and compact `spineRootAttributes`, `spineBodyAttributes`,
`spineHeadTitles`, `spineHeadMetas`, `spineHeadBases`, `spineHeadLinks`,
`spineHeadStyles`, and `spineHeadScripts` arrays. Generated output emits
independent title/root/body/head metadata for each page from direct and nested
alias shapes, filters duplicate viewport head metadata, and keeps conflicting
primary aliases out of the alternate files. Read-back preserves head titles,
root/body attributes, head metas/bases/links/styles/scripts, and alternate
body text. This lets WordPress/Data Liberation exports author generated
multi-page alternate EPUB3 renditions with concise XHTML metadata arrays while
keeping primary package XHTML metadata isolated. This remains lane-local
native PHP without invoking upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, arbitrary multi-rendition
package graph validation beyond covered rootfile/container link sidecar paths,
DRM decryption, XML signature cryptographic validation, broader TeX/MathML
parser parity, or EPUB2-specific output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises generated alternate-rootfile
split-spine manifest and itemref alias arrays. The regression writes a
structured `ALT/itemrefs.opf` alternate rendition with two generated XHTML
spine pages and compact `spineManifestIds`, `spineItemIds`, and
`spineItemProperties` arrays. Generated output emits the requested alternate
manifest item IDs, itemref IDs, page-spread/layout/flow itemref properties,
and nav targets while conflicting primary writer arrays stay out of the
alternate OPF. Read-back preserves idrefs, itemref IDs, href/path provenance,
property tokens, derived rendition metadata, and alternate body text. This
lets WordPress/Data Liberation exports author generated multi-page alternate
EPUB3 renditions with concise spine manifest/itemref arrays while keeping
primary package spine metadata isolated. This remains lane-local native PHP
without invoking upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, arbitrary multi-rendition package graph
validation beyond covered rootfile/container link sidecar paths, DRM
decryption, XML signature cryptographic validation, broader TeX/MathML parser
parity, or EPUB2-specific output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises generated alternate-rootfile
split-spine language, direction, and viewport aliases. The regression writes a
structured `ALT/aliases.opf` alternate rendition with two generated XHTML
spine pages and compact `spineLanguages`, `spineDirections`, and `viewports`
arrays rather than expanding every per-page value into `spineItemRefs`.
Generated output emits independent `lang`/`xml:lang`/`dir` root attributes and
viewport meta tags for each alternate page while conflicting primary writer
options stay out of the alternate XHTML. Read-back preserves per-spine
language, direction, viewport records, aggregated `epubViewports`, and
alternate body text. This lets WordPress/Data Liberation exports generate
localized or fixed-layout multi-page alternate EPUB3 renditions with concise
per-spine metadata. This remains lane-local native PHP without invoking
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, arbitrary multi-rendition package graph validation
beyond covered rootfile/container link sidecar paths, DRM decryption, XML
signature cryptographic validation, broader TeX/MathML parser parity, or
EPUB2-specific output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 multi-rendition
container graph output from structured alternate-rootfile metadata. The
regression writes a primary package, a generated `ALT/generated-print.opf`
alternate rootfile, a `META-INF/metadata/generated-renditions.json` sidecar
payload, and an OCF container link that uses `rel="record alternate"` and
`refines="#generated-print-rendition"` to describe that generated rendition.
Generated output keeps the alternate OPF in `container.xml` rootfiles and
keeps the JSON sidecar under `META-INF` without adding either file to the
primary OPF manifest. Read-back preserves the generated alternate rootfile
ID/properties, container link href/rel/media type/refines/properties, sidecar
payload bytes, alternate package title, readable resource, and body text
without `missing-container-link-resource` or
`missing-container-rootfile-resource` self-diagnostics. This lets
WordPress/Data Liberation exports carry rendition-level container metadata for
generated alternate packages without hand-authoring raw OPF payloads. This
remains lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graph validation beyond covered rootfile/container
link sidecar paths, DRM decryption, XML signature cryptographic validation,
broader TeX/MathML parser parity, or EPUB2-specific output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises generated TeX MathML output through
an alternate EPUB rootfile. The regression writes a primary package with no
math and a generated `ALT/math.opf` alternate rendition whose TeX-only math
nodes render as MathML in `ALT/text/math.xhtml`. The alternate OPF infers
`properties="mathml"` on its XHTML spine item without explicit
`spineManifestProperties`, while the primary OPF does not inherit alternate
math properties. Read-back preserves the alternate package title, readable
resource, and `manifestProperties=["mathml"]`. This lets WordPress/Data
Liberation exports carry semantic math in generated multi-rendition EPUB3
packages, not only in the primary spine. This remains lane-local native PHP
without invoking upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, broader texmath parser parity, full MathML parser parity, full
fixture corpus parity, arbitrary multi-rendition package graph validation
beyond covered rootfile/container-link paths, DRM decryption, XML signature
cryptographic validation, or EPUB2-specific output behavior.

Earlier scenario:
`tests/MarkdownReaderTest.php` and `tests/EpubWriterTest.php` now exercise
generated MathML output from TeX source. `HtmlWriter` with
`writerHTMLMathMethod=mathml` emits semantic MathML for a bounded native TeX
subset covering identifiers, numbers, operators, scripts, grouped rows,
`\frac`, `\sqrt`, `\text`, common Greek commands, and common operator
commands, while preserving the original source in an `application/x-tex`
annotation. `EpubWriter` now marks generated XHTML manifest entries with
`properties="mathml"` when ordinary TeX math nodes render through that path.
The EPUB writer coverage verifies generated inline and display MathML,
confirms the prior legacy span-only math output is not emitted for this path,
and read-backs inline/display math text plus `manifestProperties`. This lets
WordPress/Data Liberation exports carry semantic EPUB3 math for the covered
TeX subset while keeping the source TeX available for review or downstream
regeneration. This remains lane-local native PHP without invoking upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full texmath
parser parity, full MathML parser parity, full fixture corpus parity,
arbitrary multi-rendition package graph validation beyond covered
rootfile/container-link paths, DRM decryption, XML signature cryptographic
validation, or EPUB2-specific output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 multi-rendition
container graph output with a primary package, an alternate `ALT/print.opf`
rootfile, a `META-INF/metadata/renditions.json` sidecar payload, and an OCF
container link that uses `rel="record alternate"` and
`refines="#print-rendition"` to describe the alternate rendition. Generated
output keeps the alternate OPF in `container.xml` rootfiles and keeps the JSON
sidecar under `META-INF` without adding either file to the primary OPF
manifest. Read-back preserves the alternate rootfile ID/properties, container
link href/rel/media type/refines/properties, sidecar payload bytes, and
alternate package summary without `missing-container-link-resource` or
`missing-container-rootfile-resource` self-diagnostics. This lets
WordPress/Data Liberation exports carry rendition-level container metadata
without confusing OCF graph metadata with primary publication resources. This
remains lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graph validation beyond the current
rootfile/container-link sidecar edge, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 OPF Dublin Core
content-model sanitation for alternate rootfile packages. Source metadata for a
generated multi-rendition package may contain empty alternate Dublin Core
title/language/creator records, invalid `dc:language` values, unsupported
Dublin Core element names, duplicate explicit dates, and empty package `meta`
values. `EpubWriter` filters that source metadata before serializing
`ALT/content-model.opf`, preserving the valid identifier, fallback
title/language, first date, and contributor while dropping invalid content.
`EpubReader` also now promotes the full extracted Dublin Core summary keys for
alternate packages, including contributor, subject, type, format, source,
relation, and coverage. Read-back confirms generated alternate packages no
longer self-diagnose `empty-package-metadata-value`,
`invalid-package-metadata-dublin-core-element`,
`invalid-package-metadata-child-element`, `multiple-metadata-date`, or
`invalid-metadata-language` for those source shapes. This lets WordPress/Data
Liberation export metadata-rich EPUB3 multi-rendition packages without noisy
alternate source records becoming OPF metadata content-model errors. This
remains lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graph validation beyond the current container-rootfile
path, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 OPF Dublin Core
content-model sanitation for primary packages. Source metadata may contain
empty Dublin Core title/language/creator records, invalid `dc:language`
values, unsupported Dublin Core element names, duplicate explicit dates, and
empty package `meta` values. `EpubWriter` filters that source metadata before
serializing `OEBPS/package.opf`, preserving the valid identifier, fallback
title/language, first date, and contributor while dropping invalid content.
Read-back confirms generated packages no longer self-diagnose
`empty-package-metadata-value`,
`invalid-package-metadata-dublin-core-element`,
`invalid-package-metadata-child-element`, `multiple-metadata-date`, or
`invalid-metadata-language` for those source shapes. This lets
WordPress/Data Liberation export metadata-rich EPUB3 packages without noisy
source records becoming OPF metadata content-model errors. This remains
lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graph validation beyond the current container-rootfile
path, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 OPF
guide-reference target sanitation for alternate rootfile packages. Source
metadata for a generated multi-rendition package may include alternate guide
references that point at missing resources, missing XML anchors, query-only or
fragment-only hrefs, data/file URLs, absolute paths, protocol-relative URLs,
backslash paths, encoded dot-segment paths, empty fragments, or whitespace
fragments. The shared `EpubWriter` package path now validates those hrefs
against the reader-compatible guide target rules and the generated alternate
package resource/XML payload map before serializing `ALT/guide-sanitized.opf`.
The generated alternate OPF keeps valid local chapter anchors and allowed
remote guide references while dropping invalid target shapes. Read-back
confirms generated alternate packages recover only those valid guide
references and no longer self-diagnose missing/invalid guide-reference target
records. This lets WordPress/Data Liberation export EPUB3 multi-rendition
packages without stale imported alternate guide metadata pointing at resources
or anchors that the alternate package does not contain. This remains
lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graph validation beyond the current container-rootfile
path, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 OPF
guide-reference target sanitation for primary packages. Source metadata may
include imported guide references that point at missing resources, missing XML
anchors, query-only or fragment-only hrefs, data/file URLs, absolute paths,
protocol-relative URLs, backslash paths, encoded dot-segment paths, empty
fragments, or whitespace fragments. `EpubWriter` now validates those hrefs
against the reader-compatible guide target rules and the generated package
resource/XML payload map before serializing `OEBPS/package.opf`. The generated
OPF keeps valid local chapter anchors and allowed remote guide references while
dropping invalid target shapes. Read-back confirms generated packages recover
only those valid guide references and no longer self-diagnose missing/invalid
guide-reference target records. This lets WordPress/Data Liberation export
EPUB3 packages without stale imported guide metadata pointing at resources or
anchors that the generated package does not contain. This remains lane-local
native PHP without invoking upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, arbitrary multi-rendition
package graph validation beyond the current container-rootfile path, DRM
decryption, XML signature cryptographic validation, generic TeX-to-MathML
conversion, or EPUB2-specific output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 OPF package
root/spine ID writer sanitation for alternate rootfile packages. Source
metadata for a generated multi-rendition package may include package-level ID
values that collide with writer-generated package IDs, such as
`packageId=nav` where `nav` is the generated alternate navigation manifest
item, or `spineId=alt-book-id` where `alt-book-id` is the alternate package
identifier anchor. The shared `EpubWriter` package path now has focused
coverage proving `ALT/sanitized-ids.opf` drops those colliding root/spine IDs
while preserving the real `nav` manifest item and `alt-book-id` identifier.
Read-back confirms generated alternate packages no longer self-diagnose
`duplicate-package-id` for that collision shape. This lets WordPress/Data
Liberation export EPUB3 multi-rendition packages without alternate rootfile
package-level IDs colliding with writer-generated OPF IDs. This remains
lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graph validation beyond the current container-rootfile
path, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 OPF package
root/spine ID writer sanitation. Source metadata may include package-level ID
values that collide with writer-generated package IDs, such as
`packageId=nav` where `nav` is the generated navigation manifest item, or
`spineId=book-id` where `book-id` is the required package identifier anchor.
`EpubWriter` now reserves generated manifest IDs, generated itemref IDs, and
the unique identifier ID before emitting package root and spine IDs. It drops
the colliding package root/spine IDs while preserving the real `nav` manifest
item and `book-id` identifier. Read-back confirms generated packages no
longer self-diagnose `duplicate-package-id` for that collision shape. This
lets WordPress/Data Liberation export EPUB3 packages from package-level
metadata without imported root/spine IDs colliding with writer-generated OPF
IDs. This remains lane-local native PHP without invoking upstream Pandoc,
live fetching, shelling out to converters, EPUBCheck, full fixture corpus
parity, arbitrary multi-rendition package graph validation beyond the current
container-rootfile path, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 OPF Dublin Core
reserved package-ID sanitation. Source metadata may include imported Dublin
Core records whose IDs collide with writer-generated package IDs, such as a
subject record with `id="nav"` where `nav` is the generated navigation
manifest item. `EpubWriter` now passes generated manifest IDs into Dublin
Core metadata normalization, keeps the subject value without the colliding ID,
and emits only one `id="nav"` in `OEBPS/package.opf`. Read-back confirms
generated packages no longer self-diagnose `duplicate-package-id` for that
collision shape. This lets WordPress/Data Liberation export EPUB3 packages
from metadata-rich sources without imported Dublin Core record IDs colliding
with writer-generated OPF manifest IDs. This remains lane-local native PHP
without invoking upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, arbitrary multi-rendition package graph
validation beyond the current container-rootfile path, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 OPF Dublin Core
unique-identifier ID sanitation. Source metadata may include imported Dublin
Core records in arbitrary order, including a non-identifier record such as a
creator that tries to reuse the package unique identifier ID `book-id` before
the identifier record appears. `EpubWriter` now reserves that ID for Dublin
Core identifier records, keeps the creator value without the colliding ID,
and emits only one `id="book-id"` in `OEBPS/package.opf`. Read-back confirms
generated packages no longer self-diagnose `duplicate-metadata-id` or
`duplicate-package-id` for that collision shape. This lets WordPress/Data
Liberation export EPUB3 packages from metadata-rich sources without losing the
stable OPF identifier anchor needed by package metadata refinements. This
remains lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graph validation beyond the current container-rootfile
path, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 OPF direct
collection-link standalone sidecar sanitation. Source collection metadata may
include direct links to local non-content payloads such as
`metadata/member.json`. `EpubWriter` now treats those direct collection links
as standalone sidecars, keeping the collection `<link>` in `OEBPS/package.opf`
while omitting a duplicate manifest `<item>` for the same JSON/XML/audio/etc.
payload. EPUB content documents still remain manifest-backed. Read-back
confirms generated packages no longer self-diagnose
`invalid-package-link-manifest-resource`, and that
`epubPackageLinkResources`/`epubResourcePayloads` recover the sidecar path,
relation, collection parentage, collection ID, and JSON payload. This lets
WordPress/Data Liberation export EPUB3 packages with collection sidecar
payloads without treating those payloads as invalid manifest resources. This
remains lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graph validation beyond the current container-rootfile
path, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 OPF package-link
package-document reference sanitation. Source metadata may include package
metadata links, collection metadata links, or direct collection links that use
fragment-only hrefs such as `#book-id` or package-document fragment hrefs such
as `package.opf#book-id` and `./package.opf#series`. `EpubWriter` now omits
those links before serializing `OEBPS/package.opf`, while still preserving
valid sidecar record links, remote metadata records, and collection member
links. Read-back confirms generated packages no longer self-diagnose
`invalid-package-link-package-document-reference`,
`missing-package-link-resource`, or `missing-package-link-fragment`. This lets
WordPress/Data Liberation export EPUB3 packages from metadata-rich sources
without creating package links that treat OPF package elements as standalone
metadata resources. This remains lane-local native PHP without invoking
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, arbitrary multi-rendition package graph validation
beyond the current container-rootfile path, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 OPF package modified
metadata sanitation. Source metadata may include a required generated
`dcterms:modified` timestamp plus additional metadata-property records that
try to add an unrefined duplicate modified timestamp with a valid ID.
`EpubWriter` now omits those unrefined duplicates before serializing
`OEBPS/package.opf`, while still preserving refined `dcterms:modified`
metadata because the reader correctly ignores refined records for package
timestamp cardinality. Read-back confirms generated packages no longer
self-diagnose `multiple-package-modified` or `invalid-package-modified`, and
that refined modified metadata still round-trips. This lets WordPress/Data
Liberation export EPUB3 packages from metadata-rich sources without
accidentally adding a second package-level modified timestamp. This remains
lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graph validation beyond the current container-rootfile
path, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 OPF Dublin Core
`dc:date` cardinality sanitation. Source metadata may contain multiple
list-style dates or multiple explicit `epubDublinCoreMetadata` date records.
`EpubWriter` now preserves the first valid date and omits later dates before
serializing `OEBPS/package.opf`, while leaving other valid Dublin Core records
such as `dc:creator` intact. Read-back confirms generated packages no longer
self-diagnose `multiple-metadata-date` and still recover the selected package
date and author metadata. This lets WordPress/Data Liberation export EPUB3
packages from noisy metadata without producing OPF packages that immediately
fail the reader's date-cardinality check. This remains lane-local native PHP
without invoking upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, arbitrary multi-rendition package graph
validation beyond the current container-rootfile path, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 OPF manifest
property inference for arbitrary resource payloads. Incoming resources may
include remote CSS, remote SMIL overlays, scripted SVG with remote image
links, and auxiliary XHTML carrying MathML, inline SVG, form/scripted content,
`epub:switch`, and remote media. `EpubWriter` now scans resource bytes before
serializing `OEBPS/package.opf` and declares the required `mathml`, `svg`,
`scripted`, `switch`, and `remote-resources` manifest properties. Read-back
confirms the generated package no longer self-diagnoses
`missing-manifest-required-property` while keeping the cover, referenced
resource, image resource, guide, JSON, XML, CSS, SMIL, SVG, and auxiliary
XHTML metadata intact. This lets WordPress/Data Liberation export EPUB3
packages with structured sidecar resources that do not immediately round-trip
as invalid OPF. This remains lane-local native PHP without invoking upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, arbitrary multi-rendition package graph validation beyond the
current container-rootfile path, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 OPF fallback-chain
sanitation for foreign manifest resources whose fallback targets never reach
an EPUB core media type. Incoming metadata may define a valid foreign widget
fallback to XHTML alongside a non-core-only HEIC to AVIF fallback chain.
`EpubWriter` now evaluates the planned manifest graph before serializing OPF
items, preserves the valid foreign-to-core fallback chain, and strips the
non-core-only fallback before writing `OEBPS/package.opf`. Read-back confirms
the generated package no longer self-diagnoses
`missing-manifest-fallback-core-media-type` alongside the existing manifest
fallback, fallback-style, media-overlay, and fallback-cycle self-diagnostic
guards. This lets WordPress/Data Liberation export EPUB3 packages without
keeping invalid fallback chains that only point to other foreign media types.
This remains lane-local native PHP without invoking upstream Pandoc, live
fetching, shelling out to converters, EPUBCheck, full fixture corpus parity,
arbitrary multi-rendition package graph validation beyond the current
container-rootfile path, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 OPF manifest
reference sanitation for `fallback`, `fallback-style`, and `media-overlay`
attributes. Incoming metadata may define valid forward fallbacks alongside
missing fallback targets, non-CSS fallback-style targets, missing overlay
targets, overlay links to non-SMIL resources, and cyclic fallback chains.
`EpubWriter` now plans the emitted manifest before serializing OPF items, so
it preserves valid references and strips invalid package references before
writing `OEBPS/package.opf`. Read-back confirms the generated package no
longer self-diagnoses manifest fallback, fallback-style, media-overlay, or
fallback-cycle errors. This lets WordPress/Data Liberation export EPUB3
packages whose manifest reference attributes do not immediately round-trip as
invalid OPF. This remains lane-local native PHP without invoking upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, arbitrary multi-rendition package graph validation beyond the
current container-rootfile path, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 OPF binding writer
sanitation for both primary packages and generated alternate rootfile
packages. Incoming metadata may define valid custom bindings alongside
duplicate media types, EPUB core media types, malformed media types, invalid
handler IDREFs, missing handler targets, non-XHTML handlers, unscripted
handlers, missing media types, missing handlers, and primary package bindings
that must not leak into alternate OPFs. `EpubWriter` now validates bindings
against the emitted manifest item map and preserves only custom media bindings
whose handler is an emitted scripted XHTML item. Read-back confirms primary
`OEBPS/package.opf` and alternate `ALT/bindings.opf` keep only the valid
binding records without binding-target/media/handler/scripted
self-diagnostics. This lets WordPress/Data Liberation export primary and
alternate EPUB3 renditions with binding records that do not immediately
round-trip as invalid OPF packages. This remains lane-local native PHP without
invoking upstream Pandoc, live fetching, shelling out to converters, EPUBCheck,
full fixture corpus parity, arbitrary multi-rendition package graph validation
beyond the current container-rootfile path, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises generated alternate EPUB3 rootfile
package metadata link sanitation parity. Incoming alternate rendition metadata
may define valid record and voicing metadata links alongside unsafe data URLs,
invalid fragments, invalid or undeclared relation/property tokens, invalid
media types, bad language tags, bad directions, invalid refines values,
voicing links without valid refines targets, and primary package metadata links
that must not leak into the alternate OPF. Generated
`ALT/metadata-links.opf` now preserves valid record and voicing links with
alternate-scoped XML/audio sidecars, inferred media types, declared custom
properties, `refines`, `hreflang`, `xml:lang`, and `dir` attributes while
stripping the invalid records and stale primary metadata links. Read-back under
`epubAlternateRootfilePackages` confirms the alternate package keeps the valid
metadata links without package-link relation/property/href/media/language/
refines self-diagnostics. This lets WordPress/Data Liberation export alternate
EPUB3 renditions with sanitized package metadata links and recover valid record
and voicing links on import for block-oriented processing. This remains
lane-local native PHP without invoking upstream Pandoc, live fetching, shelling
out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graph validation beyond the current container-rootfile
path, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises generated alternate EPUB3 rootfile
package metadata property sanitation parity. Incoming alternate rendition
metadata may define valid custom OPF metadata properties alongside invalid
property names, undeclared property prefixes, duplicate metadata IDs, invalid
metadata IDs, bad `refines` values, invalid schemes, undeclared scheme
prefixes, invalid language tags, invalid directions, and primary package
metadata that must not leak into the alternate OPF. Generated
`ALT/metadata-properties.opf` now preserves valid `custom:ranking` metadata
with `id`, `refines`, `scheme`, `xml:lang`, and `dir` attributes while
stripping the invalid records and stale primary metadata. Read-back under
`epubAlternateRootfilePackages` confirms the alternate package keeps the valid
custom metadata without `invalid-package-meta-property`,
`undeclared-package-meta-property-prefix`, `invalid-package-meta-scheme`,
`undeclared-package-meta-scheme-prefix`, `invalid-metadata-id`,
`duplicate-metadata-id`, `invalid-metadata-refines`,
`invalid-metadata-xml-language`, or `invalid-metadata-dir` self-diagnostics.
This lets WordPress/Data Liberation export alternate EPUB3 renditions with
sanitized package metadata properties and recover valid custom metadata on
import for block-oriented processing. This remains lane-local native PHP
without invoking upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, arbitrary multi-rendition package graph
validation beyond the current container-rootfile path, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises generated alternate EPUB3 rootfile
nav document metadata and specialized-list parity. Incoming alternate
rendition metadata may define its own nav root attributes, nav body
attributes, TOC section attributes/title, landmark section attributes/title,
page-list section attributes/title, TOC entries, landmark entries, page-list
entries, and auxiliary navigation sections. Generated `ALT/nav-meta.xhtml` now
preserves alternate-relative links, alternate nav section headings and
attributes, a pagebreak-backed page-list target in `ALT/text/nav.xhtml`, and
keeps primary navigation metadata out of the alternate nav document while
keeping alternate navigation metadata out of the primary nav document.
Read-back under `epubAlternateRootfilePackages` confirms the alternate package
keeps its TOC, landmark, page-list, auxiliary nav, nav root/body attributes,
nav section attributes/titles, page-list value, and pagebreak target without
page-list target diagnostics or primary navigation metadata leakage. This lets
WordPress/Data Liberation export alternate EPUB3 renditions with scoped
navigation metadata and recover those navigation summaries on import for
block-oriented processing. This remains lane-local native PHP without invoking
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, arbitrary multi-rendition package graph validation
beyond the current container-rootfile path, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises split alternate EPUB3 rootfile XHTML
per-spine metadata parity. Incoming alternate rendition metadata may define
two generated split spine documents with distinct `spineItemRefs` head titles,
viewports, head metas, head links, XHTML root attributes, and XHTML body
attributes. Generated `ALT/text/split.xhtml` and `ALT/text/split-2.xhtml` now
preserve page-specific head/root/body metadata, mark the alternate OPF manifest
items with `remote-resources` when head links require it, keep page-one
metadata out of page two and page-two metadata out of page one, and keep
primary spine XHTML metadata out of the alternate rendition. Read-back under
`epubAlternateRootfilePackages` confirms the split alternate spine keeps
distinct per-item head titles, viewports, head metas, head links, root
attributes, and body attributes without `malformed-spine-xhtml` diagnostics or
primary XHTML metadata leakage. This lets WordPress/Data Liberation export
split alternate EPUB3 renditions with page-specific generated XHTML metadata
and recover those per-spine distinctions on import for block-oriented
processing. This remains lane-local native PHP without invoking upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, arbitrary multi-rendition package graph validation beyond the
current container-rootfile path, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises generated alternate EPUB3 rootfile
XHTML head/root/body metadata parity. Incoming alternate rendition metadata may
define `spineItemRefs` head title, viewport, head metas, head base, head links,
inline head styles, external and inline head scripts, XHTML root attributes,
XHTML body attributes, and sidecar resource payloads. Generated
`ALT/text/xhtml.xhtml` now preserves that metadata, rewrites linked
CSS/script/image paths relative to the alternate chapter, emits `scripted`
manifest properties for alternate spine XHTML, packages alternate sidecar
resources, and keeps primary spine XHTML metadata out of the alternate
rendition. Read-back under `epubAlternateRootfilePackages` confirms the
alternate spine keeps its head title, viewport, head metas, bases, links,
styles, scripts, root attributes, body attributes, and resource payloads
without `malformed-spine-xhtml` diagnostics or primary XHTML metadata leakage.
This lets WordPress/Data Liberation export alternate EPUB3 renditions with rich
generated XHTML head/root/body metadata and recover the same metadata on import
for block-oriented processing. This remains lane-local native PHP without
invoking upstream Pandoc, live fetching, shelling out to converters, EPUBCheck,
full fixture corpus parity, arbitrary multi-rendition package graph validation
beyond the current container-rootfile path, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises generated alternate EPUB3 rootfile
collection sanitation parity. Incoming alternate rendition metadata may contain
duplicate and malformed collection roles, reserved `idpf.org` role IRIs,
duplicate collection IDs, invalid root language/direction attributes, invalid
metadata IDs, duplicate metadata IDs, undeclared metadata property/scheme
prefixes, invalid metadata refines, bad collection link relation/property
tokens, and invalid link language tags. Generated `ALT/collections.opf` now
preserves valid collection roles, nested collection language, first valid
collection and metadata IDs, valid local metadata and link refines,
declared-prefix metadata, valid direct collection member links, and sanitized
link token/language attributes while omitting invalid-only collections,
duplicate IDs, invalid attributes, undeclared-prefix metadata/link tokens, bad
refines, and malformed link language values. Read-back under
`epubAlternateRootfilePackages` confirms the sanitized collections round-trip
without invalid role/language/direction/id, duplicate-id, undeclared-prefix,
bad-refines, missing-rel, or invalid package-link token/language diagnostics.
This lets WordPress/Data Liberation export alternate EPUB3 rendition
collection graphs from noisy structured metadata without causing alternate OPF
collection self-diagnostics or leaking alternate collection IDs into the
primary OPF. This remains lane-local native PHP without invoking upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, arbitrary multi-rendition package graph validation beyond the
current container-rootfile path, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises generated alternate EPUB3 rootfile
package-root and Dublin Core metadata sanitation parity. Incoming alternate
rendition metadata may contain mixed valid and invalid package prefix
declarations, invalid package `xml:lang`, invalid `dc:language`, duplicate
Dublin Core ids, and primary package scalar metadata that must not leak into
the alternate OPF. Generated `ALT/package-root.opf` now preserves the valid
package id, unique identifier, package prefix declarations, fallback
`dc:language`, and first Dublin Core creator id while omitting invalid prefix
declarations, invalid language values, and duplicate DC ids. Read-back under
`epubAlternateRootfilePackages` confirms the alternate package keeps the
sanitized prefix and language metadata, does not round-trip the invalid
`en_US` Dublin Core language payload, and does not self-diagnose
package-prefix, package-language, metadata-language, or duplicate-id errors.
This lets WordPress/Data Liberation export alternate EPUB3 rendition
package-root and Dublin Core metadata from noisy structured metadata without
creating invalid alternate OPF root attributes, invalid DC language records,
or duplicate metadata ids. This remains lane-local native PHP without invoking
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, arbitrary multi-rendition package graph validation
beyond the current container-rootfile path, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises generated alternate EPUB3 rootfile
package and collection link sanitation parity. Incoming alternate rendition
metadata may contain valid local sidecar links, generated alternate XHTML
member links, valid remote links, missing local hrefs, and noisy `value`,
`text`, or child-like payloads on package metadata links, collection metadata
links, and direct collection member links. Generated `ALT/links.opf` now
preserves valid local and remote link attributes while omitting missing local
links and serializing surviving package links as empty `<link/>` elements.
Read-back under `epubAlternateRootfilePackages` confirms the valid links remain
in package and collection summaries, missing IDs and stray inline strings do
not round-trip, sidecar payloads are packaged, and the alternate package does
not self-diagnose `missing-package-link-resource`,
`missing-package-link-fragment`, or `invalid-package-link-content`. This lets
WordPress/Data Liberation export alternate EPUB3 rendition link graphs from
noisy structured metadata without producing stale local references or
package-link content-model violations in alternate OPFs. This remains
lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graph validation beyond the current container-rootfile
path, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 package and
collection link inline-content sanitation. Incoming structured metadata may
carry noisy `value`, `text`, or child-like fields on package metadata links,
collection metadata links, and direct collection member links. Generated
`OEBPS/package.opf` now preserves the intended OPF link attributes while
serializing those package links as empty `<link/>` elements, omitting forbidden
inline text and child payloads. Read-back confirms the links remain in package
and collection summaries, the stray inline content does not round-trip, and
the generated package does not self-diagnose `invalid-package-link-content`.
This lets WordPress/Data Liberation export EPUB3 package/collection link
graphs from noisy structured metadata without producing OPF package links that
violate the EPUB3 package-link content model. This remains lane-local native
PHP without invoking upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, arbitrary multi-rendition package graph
validation beyond the current container-rootfile path, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 package and
collection link missing-local-resource sanitation. Incoming package metadata,
collection metadata, and direct collection links may contain valid local ZIP
resource hrefs, valid remote links, and local hrefs that look well-formed but
do not correspond to any emitted package resource. Generated `OEBPS/package.opf`
now preserves valid local sidecar links, generated XHTML collection member
links, and remote links while omitting missing local package metadata,
collection metadata, and direct collection member hrefs. Read-back confirms
the generated package does not round-trip the missing local links and does not
self-diagnose `missing-package-link-resource` or stale missing-fragment
package-link diagnostics. This lets WordPress/Data Liberation export EPUB3
package/collection link graphs from user-supplied metadata without creating
OPF links to local resources absent from the ZIP. This remains lane-local
native PHP without invoking upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, arbitrary multi-rendition
package graph validation beyond the current container-rootfile path, DRM
decryption, XML signature cryptographic validation, generic TeX-to-MathML
conversion, package-link inline-content diagnostics, or EPUB2-specific output
behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises generated alternate EPUB3 rootfile
package metadata resource-target `refines` sanitation. Incoming alternate
package metadata may contain valid references to alternate sidecar XML records,
generated alternate XHTML chapter fragments, and `ALT/refines.opf#id` package
targets, alongside missing alternate resources, missing XML/XHTML fragments,
and record-link refines that should not be serialized. Generated
`ALT/refines.opf` now preserves valid alternate package-resource refines
attributes and omits invalid or unprovable targets. Read-back confirms the
alternate package summary does not self-diagnose `invalid-metadata-refines`,
`missing-metadata-refines-resource`, `missing-metadata-refines-fragment`,
`missing-metadata-refines-target`, or `invalid-package-link-record-refines`.
This lets WordPress/Data Liberation export alternate EPUB3 renditions with
package metadata refinements to their own sidecar resources and XHTML content
without leaking alternate sidecars into the primary OPF or producing stale
alternate-package diagnostics. This remains lane-local native PHP without
invoking upstream Pandoc, live fetching, shelling out to converters, EPUBCheck,
full fixture corpus parity, arbitrary multi-rendition package graph validation
beyond the current container-rootfile path, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 package-wide
collection ID sanitation. Incoming collection metadata may contain a top-level
duplicate collection ID, a later nested duplicate collection ID, valid metadata
refines to the first emitted collection ID, and metadata refines to IDs that
will be omitted because they are duplicates. Generated `OEBPS/package.opf`
now preserves the first valid collection ID, omits later duplicate collection
IDs across the recursive collection tree, preserves valid metadata refines to
emitted IDs, and omits stale refines to suppressed IDs. Read-back confirms
generated packages do not self-diagnose `duplicate-collection-id`,
`invalid-collection-metadata-refines`, or
`collection-metadata-refines-outside`. This lets WordPress/Data Liberation
export EPUB3 collection graphs from user-supplied metadata without repeated
collection identifiers causing generated OPF to fail the same reader
diagnostics or leaving stale collection metadata refinements behind. This
remains lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graph validation beyond the current container-rootfile
path, alternate-rootfile-specific coverage for this behavior, DRM decryption,
XML signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 collection root role
and language sanitation. Incoming collection metadata may contain duplicate
valid role tokens, malformed role tokens, reserved `idpf.org` custom role IRIs,
valid custom absolute role IRIs, invalid root language tags, valid nested root
language tags, and collections whose role field contains no valid token.
Generated `OEBPS/package.opf` now preserves only valid collection role tokens
and valid root `xml:lang` attributes, skips invalid-only collections, and
round-trips without `invalid-collection-role`, `duplicate-collection-role`,
`invalid-collection-role-idpf-host`, `invalid-collection-xml-language`, or
`missing-collection-role` diagnostics. This lets WordPress/Data Liberation
export EPUB3 collection graphs from user-supplied metadata without raw
collection role strings or root language tags causing generated OPF to fail
the same reader's collection diagnostics. This remains lane-local native PHP
without invoking upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, arbitrary multi-rendition package graph
validation beyond the current container-rootfile path, duplicate collection-id
writer sanitation, alternate-rootfile-specific coverage for this behavior, DRM
decryption, XML signature cryptographic validation, generic TeX-to-MathML
conversion, or EPUB2-specific output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 top-level package
metadata resource-target `refines` sanitation. Incoming package metadata may
contain valid references to sidecar XML records, generated XHTML chapter
fragments, or `package.opf#id` targets, alongside missing resources, missing
XML/XHTML fragments, absolute URLs, and record-link refines that should not be
serialized. Generated `OEBPS/package.opf` now preserves valid package-resource
refines attributes and omits invalid or unprovable targets. Read-back confirms
generated packages do not self-diagnose `invalid-metadata-refines`,
`missing-metadata-refines-resource`, `missing-metadata-refines-fragment`,
`missing-metadata-refines-target`, or `invalid-package-link-record-refines`.
This lets WordPress/Data Liberation export top-level EPUB3 package metadata
refinements that point at packaged content or metadata resources without
producing OPF that the same reader immediately flags as invalid. This remains
lane-local native PHP without invoking upstream Pandoc, live fetching, shelling
out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graph validation beyond the current container-rootfile
path, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, alternate-rootfile resource-target metadata refines
behavior, collection/link content/resource-provenance sanitation, or
EPUB2-specific output behavior.

Earlier scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 collection metadata
`refines` sanitation. Incoming collection metadata may contain valid local
metadata and link refines values, package-level outside targets, bare
non-fragment targets, malformed fragments, or missing collection-local targets.
Generated `OEBPS/package.opf` now keeps only valid `#id` refines attributes
that point inside the emitted containing collection tree and omits malformed,
missing, or outside targets. Read-back confirms generated packages do not
self-diagnose `invalid-collection-metadata-refines` or
`collection-metadata-refines-outside`. This lets WordPress/Data Liberation
export EPUB3 collection metadata refinements from user-supplied collection
structures without producing OPF that the same reader immediately flags as
invalid. This remains lane-local native PHP without invoking upstream Pandoc,
live fetching, shelling out to converters, EPUBCheck, full fixture corpus
parity, arbitrary multi-rendition package graph validation beyond the current
container-rootfile path, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, top-level resource-target
metadata refines writer behavior, collection/link content/resource-provenance
sanitation, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 top-level package
metadata property sanitation. Incoming document metadata may contain malformed
or undeclared package metadata properties and schemes, duplicate or invalid
metadata IDs, invalid `refines` values, invalid language tags, or invalid
direction values. Generated `OEBPS/package.opf` now skips invalid or
undeclared property records, suppresses duplicate IDs, omits invalid optional
attributes, and preserves valid declared custom-prefixed metadata properties.
Read-back confirms generated packages do not self-diagnose package metadata
property, scheme, ID, refines, language, or direction errors. This lets
WordPress/Data Liberation export EPUB3 package metadata properties with
malformed user-supplied metadata without producing OPF that the same reader
immediately flags as invalid. This remains lane-local native PHP without
invoking upstream Pandoc, live fetching, shelling out to converters, EPUBCheck,
full fixture corpus parity, arbitrary multi-rendition package graph validation
beyond the current container-rootfile path, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, full
resource-target metadata refines behavior, collection/link
content/resource-provenance sanitation, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 top-level
metadata-link sanitation. Incoming document metadata may contain unsafe hrefs,
malformed or undeclared relation/property tokens, duplicate tokens, invalid
language tags, invalid explicit media types, invalid `refines` values, or
metadata `voicing` relations without a valid `#id` refines target. Generated
`OEBPS/package.opf` now emits only sanitized top-level metadata links, infers
valid local resource types such as `application/xml` and `audio/mpeg`,
preserves declared custom property tokens, suppresses invalid attributes, and
skips links with no valid relation. Read-back confirms generated packages do
not self-diagnose package-link href, rel/property, language, media-type,
record-refines, or voicing-refines errors. This lets WordPress/Data
Liberation export EPUB3 metadata links with malformed user-supplied metadata
without producing OPF that the same reader immediately flags as invalid. This
remains lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graph validation beyond the current container-rootfile
path, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, full package metadata property sanitation,
collection/link content/resource-provenance sanitation, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 collection link
href/media/refines sanitation. Incoming document metadata may contain unsafe
package-link hrefs, invalid explicit media types, invalid metadata-link
`refines` values, or metadata `voicing` relations without a valid `#id`
refines target. Generated `OEBPS/package.opf` now skips unsafe links, omits
invalid media types while inferring valid local resource types such as
`application/xml` and `audio/mpeg`, suppresses invalid refines attributes, and
preserves valid voicing links. Read-back confirms generated packages do not
self-diagnose package-link href, media-type, record-refines, or
voicing-refines errors. This lets WordPress/Data Liberation export EPUB3
collection links with malformed href/media/refines metadata without producing
OPF that the same reader immediately flags as invalid. This remains lane-local
native PHP without invoking upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, arbitrary multi-rendition
package graph validation beyond the current container-rootfile path, DRM
decryption, XML signature cryptographic validation, generic TeX-to-MathML
conversion, full collection/link content/resource-provenance sanitation,
top-level package metadata-link parity, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 collection link
relation/property token sanitation. Incoming document metadata may contain
malformed relation/property tokens, duplicate tokens, or undeclared-prefixed
tokens, but generated `OEBPS/package.opf` now emits only valid, de-duplicated
tokens such as `rel="contents custom:part"` and
`properties="series custom:featured"`. Links with no valid relation are
skipped. Read-back confirms generated packages do not self-diagnose
`duplicate-package-link-rel`, `invalid-package-link-rel`,
`undeclared-package-link-rel-prefix`, `duplicate-package-link-property`,
`invalid-package-link-property`, `undeclared-package-link-property-prefix`,
or `missing-package-link-rel`. This lets WordPress/Data Liberation export
EPUB3 collection links with malformed, duplicated, or undeclared
user-supplied relation/property tokens without producing OPF that the same
reader immediately flags as invalid. This remains lane-local native PHP
without invoking upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, arbitrary multi-rendition package graph
validation beyond the current container-rootfile path, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, full
collection/link media-type/path/refines/content sanitation, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 collection link
language sanitation. Incoming document metadata may contain invalid direct
collection link language values such as `hreflang="bad lang"` or
`xml:lang="en_US"`, but generated `OEBPS/package.opf` now omits invalid
collection link language attributes while preserving valid tags such as
`hreflang="pl"` and `xml:lang="fr"`. Read-back confirms generated packages do
not self-diagnose `invalid-package-link-hreflang` or
`invalid-package-link-xml-language`. This lets WordPress/Data Liberation
export EPUB3 collection links with malformed user-supplied language metadata
without producing OPF that the same reader immediately flags as invalid. This
remains lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graph validation beyond the current container-rootfile
path, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, full collection/link rel/property/media-type/path
sanitation, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 collection metadata
prefix sanitation. Incoming document metadata may contain undeclared collection
metadata property prefixes such as `missing:position` or scheme prefixes such
as `missingScheme:codelist`, but generated `OEBPS/package.opf` now skips
metadata records whose property prefix is undeclared, omits scheme attributes
whose prefix is undeclared, and preserves valid declared custom-prefixed
metadata such as `custom:ranking` with `custom:codelist`. Read-back confirms
generated packages do not self-diagnose
`undeclared-collection-metadata-property-prefix` or
`undeclared-collection-metadata-scheme-prefix`. This lets WordPress/Data
Liberation export EPUB3 collection metadata with undeclared user-supplied
property/scheme prefixes without producing OPF that the same reader immediately
flags as invalid. This remains lane-local native PHP without invoking upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, arbitrary multi-rendition package graph validation beyond the
current container-rootfile path, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, full collection/link attribute
sanitation, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 collection metadata
property/scheme sanitation. Incoming document metadata may contain invalid
collection metadata properties such as `bad property` or invalid schemes such
as `bad scheme`, but generated `OEBPS/package.opf` now skips malformed
property records, omits malformed scheme attributes, and preserves valid
prefixed values such as `schema:position` with `marc:relators`. Read-back
confirms generated packages do not self-diagnose
`invalid-collection-metadata-property` or
`invalid-collection-metadata-scheme`. This lets WordPress/Data Liberation
export EPUB3 collection metadata with malformed user-supplied property/scheme
values without producing OPF that the same reader immediately flags as
invalid. This remains lane-local native PHP without invoking upstream Pandoc,
live fetching, shelling out to converters, EPUBCheck, full fixture corpus
parity, arbitrary multi-rendition package graph validation beyond the current
container-rootfile path, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, undeclared-prefix sanitation for
collection metadata, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 collection metadata
ID sanitation. Incoming document metadata may contain invalid collection
metadata IDs such as `bad collection id` or duplicate valid IDs such as
`series-subtitle`, but generated `OEBPS/package.opf` now omits invalid IDs and
emits each valid collection metadata ID at most once while preserving the
metadata text. Read-back confirms generated packages do not self-diagnose
`invalid-collection-metadata-id` or `duplicate-collection-metadata-id`. This
lets WordPress/Data Liberation round-trip EPUB3 packages with imported or
user-supplied collection metadata ID issues without exporting OPF that the same
reader immediately flags as invalid. This remains lane-local native PHP
without invoking upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, arbitrary multi-rendition package graph
validation beyond the current container-rootfile path, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 collection metadata
language sanitation. Incoming document metadata may contain invalid collection
metadata language values such as `bad lang`, but generated
`OEBPS/package.opf` now omits invalid collection `<meta>` `xml:lang`
attributes while preserving valid language tags such as `pl`. Read-back
confirms generated packages do not self-diagnose
`invalid-collection-metadata-xml-language`. This lets WordPress/Data
Liberation round-trip EPUB3 packages with imported or user-supplied collection
metadata language issues without exporting OPF that the same reader immediately
flags as invalid. This remains lane-local native PHP without invoking upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, arbitrary multi-rendition package graph validation beyond the
current container-rootfile path, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 Dublin Core metadata
ID sanitation. Incoming document metadata may contain duplicate custom Dublin
Core IDs such as `creator-primary` on both `dc:creator` and `dc:contributor`,
but generated `OEBPS/package.opf` now emits the repeated ID only once while
preserving the later metadata text. Read-back confirms generated packages do
not self-diagnose `duplicate-metadata-id` or package-wide
`duplicate-package-id`. This lets WordPress/Data Liberation round-trip EPUB3
packages with imported or user-supplied Dublin Core metadata ID collisions
without exporting OPF that the same reader immediately flags as invalid. This
remains lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graph validation beyond the current container-rootfile
path, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 Dublin Core metadata
language sanitation. Incoming document metadata may contain invalid
`dc:language` values or Dublin Core `xml:lang` attributes such as `en_US`, but
generated `OEBPS/package.opf` now omits those invalid language fields and lets
the valid package language fallback satisfy required metadata. Read-back
confirms generated packages do not self-diagnose `invalid-metadata-language`
or `invalid-metadata-xml-language`. This lets WordPress/Data Liberation
round-trip EPUB3 packages with imported or user-supplied Dublin Core language
metadata without exporting OPF that the same reader immediately flags as
invalid. This remains lane-local native PHP without invoking upstream Pandoc,
live fetching, shelling out to converters, EPUBCheck, full fixture corpus
parity, arbitrary multi-rendition package graph validation beyond the current
container-rootfile path, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 OPF package-language
sanitation. Incoming document metadata may contain invalid package language
values such as `en_US`, but generated `OEBPS/package.opf` now omits invalid
package-root `xml:lang` instead of preserving it. Read-back confirms generated
packages do not self-diagnose `invalid-package-language`. This lets
WordPress/Data Liberation round-trip EPUB3 packages with imported or
user-supplied package language metadata without exporting OPF that the same
reader immediately flags as invalid. This remains lane-local native PHP
without invoking upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, arbitrary multi-rendition package graph
validation beyond the current container-rootfile path, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 OPF package-prefix
sanitation. Incoming document metadata may contain malformed prefix pairs,
invalid XML prefix names, relative IRIs, reserved-prefix overrides, and
duplicate declarations, but generated `OEBPS/package.opf` now emits only valid
package prefixes. Read-back confirms generated packages do not self-diagnose
`invalid-package-prefix`, `invalid-package-prefix-name`,
`invalid-package-prefix-iri`, `reserved-package-prefix`,
`duplicate-package-prefix`, or `overridden-package-prefix`, while valid
`schema`, `custom`, and `rendition` declarations survive. This lets
WordPress/Data Liberation round-trip EPUB3 packages with imported or
user-supplied prefix metadata without exporting OPF that the same reader
immediately flags as invalid. This remains lane-local native PHP without
invoking upstream Pandoc, live fetching, shelling out to converters, EPUBCheck,
full fixture corpus parity, arbitrary multi-rendition package graph validation
beyond the current container-rootfile path, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 OCF container version
normalization. Incoming document metadata may request stale or foreign
`epubContainerVersion` values such as `2.0`, but generated
`META-INF/container.xml` now always emits the OCF-required `version="1.0"`.
Read-back confirms generated packages do not self-diagnose
`invalid-container-version`. This lets WordPress/Data Liberation round-trip
EPUB3 packages with imported or user-supplied container-version metadata
without exporting an OCF container document that the same reader immediately
flags as invalid. This remains lane-local native PHP without invoking upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, arbitrary multi-rendition package graph validation beyond the
current container-rootfile path, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 OCF rootfile
sanitation. Structured primary and alternate rootfile metadata may include
duplicate IDs, invalid property tokens, bad media types, and malformed payload
paths, but generated `META-INF/container.xml` now emits only valid OPF package
rootfile rows. Valid alternate OPF payloads are preserved outside the primary
OPF manifest, unsafe or malformed rootfile payload paths are skipped before ZIP
packaging, invalid or duplicate IDs are omitted, invalid/duplicate properties
are filtered, and invalid or non-OPF media types are defaulted to
`application/oebps-package+xml`. Read-back confirms generated packages do not
self-diagnose invalid/duplicate rootfile IDs, invalid/duplicate rootfile
properties, malformed full paths, invalid media types, or missing rootfile
resources. This lets WordPress/Data Liberation export EPUB3 multi-rendition
rootfile payloads without producing OCF metadata that the same reader
immediately flags as invalid. This remains lane-local native PHP without
invoking upstream Pandoc, live fetching, shelling out to converters, EPUBCheck,
full fixture corpus parity, arbitrary multi-rendition package graph validation
beyond the current container-rootfile path, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 OCF container-link
sanitation. Structured `epubContainerLinks` may include valid sidecar links
alongside malformed rows, but generated `META-INF/container.xml` now emits only
safe, valid links: invalid href paths and unsafe URL schemes are skipped,
unpackaged local sidecars are skipped, relation/property tokens are filtered,
invalid IDs/refinements are rejected, and invalid optional language, direction,
and media-type attributes are suppressed. Read-back confirms the valid sidecar
payload is preserved outside the OPF manifest and generated packages do not
self-diagnose invalid container-link IDs, refines values, rel/property tokens,
href paths, or missing sidecar resources. This lets WordPress/Data Liberation
export EPUB3 container-level linked metadata sidecars without producing OCF
container metadata that the same reader immediately flags as invalid. This
remains lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graph validation beyond the current container-rootfile
path, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 link refinement
sanitation. Package metadata `record` links, collection metadata
`record alternate` links, and direct collection member links may receive
incoming `refines` metadata, but generated OPF no longer emits the invalid
attributes where EPUB3 forbids them. Read-back confirms those bad refinements
do not round-trip, while valid metadata-link refinements such as `alternate`
and `voicing` remain intact, and generated packages do not self-diagnose
`invalid-package-link-record-refines` or `invalid-collection-link-refines`.
This lets WordPress/Data Liberation export EPUB3 linked metadata and
collection member links without producing OPF that the same reader immediately
flags as invalid. This remains lane-local native PHP without invoking upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, arbitrary multi-rendition package graph validation beyond the
current container-rootfile path, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` now exercises EPUB3 collection metadata validation
for mixed OPF2/EPUB3 `meta` records. The reader reports
`invalid-collection-opf2-meta` for both name-only collection metadata and
mixed `meta property="title-type" name="legacy-title-type"` records, and the
diagnostic carries collection id, property, legacy name, refines target, and
value. This lets WordPress/Data Liberation review flag obsolete collection
metadata syntax without flattening it into valid EPUB3 collection metadata.
This remains lane-local native PHP without invoking upstream Pandoc, live
fetching, shelling out to converters, EPUBCheck, full fixture corpus parity,
arbitrary multi-rendition package graph validation beyond the current
container-rootfile path, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubWriterTest.php` now exercises generated EPUB3 cover metadata without
the OPF2 `<meta name="cover">` shim. Primary and alternate-rootfile package
fixtures prove the cover image remains marked with the manifest
`cover-image` property, guide cover references still round-trip, payloads are
preserved, and generated packages do not self-diagnose
`invalid-package-opf2-meta` on re-ingest. This lets WordPress/Data Liberation
export EPUB3 covers without immediately flagging its own package metadata as
mixed OPF2/EPUB3 syntax, while existing legacy cover metadata imports remain
preserved by the reader. This remains lane-local native PHP without invoking
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, arbitrary multi-rendition package graph validation
beyond the current container-rootfile path, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` now exercises EPUB3 package metadata validation for
mixed OPF2/EPUB3 `meta` records. The reader reports
`invalid-package-opf2-meta` for legacy `meta name="cover"` and for mixed
`meta property="title-type" name="legacy-title-type"` records, while still
preserving the legacy cover fallback metadata needed for import compatibility.
This lets WordPress/Data Liberation review flag obsolete package metadata
syntax without losing useful cover intent. This remains lane-local native PHP
without invoking upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, arbitrary multi-rendition package graph
validation beyond the current container-rootfile path, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` now exercises writer/readback provenance for nested
EPUB collection package-link sidecars. A generated child
`schema:Periodical` collection now contains its own linked JSON metadata
payload, and re-ingest exposes that payload in `epubPackageLinkResources` with
`rel`, `refines`, `hreflang`, language, direction, properties, media type,
`collectionId`, and `parentCollectionId`. This lets WordPress/Data Liberation
export series/issue-style nested collection records and ingest the same EPUB
without flattening child collection metadata into the parent collection. This
remains lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graph validation beyond the current container-rootfile
path, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` now exercises writer/readback provenance for EPUB
package-link sidecars. Generated top-level metadata links and collection
metadata/voicing links are written into OPF, packaged as sidecar payloads, and
then re-read by `EpubReader` as enriched `epubPackageLinkResources` retaining
`rel`, `refines`, `hreflang`, language, direction, properties, media type,
parent context, and collection identity. This lets WordPress/Data Liberation
export an EPUB with linked package metadata, collection records, and voicing
sidecars, then ingest that same EPUB without losing routing semantics or
requiring an OPF reparse. This remains lane-local native PHP without invoking
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, arbitrary multi-rendition package graph validation
beyond the current container-rootfile path, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` now exercises provenance for extracted EPUB package
link resources. Local XML/JSON metadata payloads listed in
`epubPackageLinkResources` retain source OPF link semantics including `rel`,
`refines`, `hreflang`, language, direction, properties, parent element, and
collection ancestry, while payload extraction still returns the same linked
metadata bytes. This lets WordPress/Data Liberation route linked records as
top-level package metadata or collection metadata without reparsing the OPF just
to recover link context. This remains lane-local native PHP without invoking
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, arbitrary multi-rendition package graph validation
beyond the current container-rootfile path, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` now exercises package metadata text-direction
diagnostics and preservation for EPUB imports. Invalid `dir` values on
`dc:title` and unrefined `dcterms:modified` metadata now emit
`invalid-metadata-dir`, while valid creator `xml:lang` and `dir` values are
proven to round-trip into `epubDublinCoreMetadata`. This lets WordPress/Data
Liberation import review surface malformed package metadata direction values
instead of silently dropping them, while retaining useful language/direction
metadata for downstream mapping. This remains lane-local native PHP without
invoking upstream Pandoc, live fetching, shelling out to converters, EPUBCheck,
full fixture corpus parity, arbitrary multi-rendition package graph validation
beyond the current container-rootfile path, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` now exercises empty required-copy metadata
diagnostics for EPUB package imports. The package reader reports blank
duplicate `dc:identifier`, `dc:title`, and `dc:language` records when another
non-empty sibling satisfies the required package metadata, while still keeping
valid fallback identifiers usable and avoiding noisy missing-required errors.
This lets WordPress/Data Liberation import review surface malformed blank
package title, identifier, and language records instead of silently accepting
them. This remains lane-local native PHP without invoking upstream Pandoc, live
fetching, shelling out to converters, EPUBCheck, full fixture corpus parity,
arbitrary multi-rendition package graph validation beyond the current
container-rootfile path, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` now exercises split-spine source provenance for
imported alternate EPUB rootfile bodies. The `ALT/viewports.opf` fixture imports
two linear XHTML spine pages, and the recovered alternate paragraphs now prove
WordPress output retains distinct `data-epub-spine-path`,
`data-epub-spine-idref`, spine index, and body block index attributes for page
one and page two. This lets WordPress/Data Liberation workflows trace
secondary-rendition blocks back to the exact XHTML page in a multi-spine
alternate OPF after import. This remains lane-local native PHP without invoking
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, arbitrary multi-rendition package graph validation
beyond the current container-rootfile path, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` now exercises fallback-chain source provenance for
imported alternate EPUB rootfile bodies. The `ALT/fallback.opf` fixture uses an
opaque widget spine item that falls through SVG to readable XHTML, and the
recovered alternate paragraph now proves WordPress output retains both the
readable fallback XHTML path/idref and the original widget source path/idref as
`data-epub-*` attributes. This lets WordPress/Data Liberation workflows trace
secondary-rendition blocks back through EPUB manifest fallback chains after
import. This remains lane-local native PHP without invoking upstream Pandoc,
live fetching, shelling out to converters, EPUBCheck, full fixture corpus
parity, arbitrary multi-rendition package graph validation beyond the current
container-rootfile path, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` now exercises per-block source provenance for
imported alternate EPUB rootfile bodies. The `ALT/package.opf` and
`MOBILE/package.opf` rootfile matrix fixture proves imported alternate body
children carry `data-epub-rootfile`, `data-epub-spine-path`,
`data-epub-spine-idref`, `data-epub-spine-index`, and
`data-epub-body-block-index`, and WordPress block output preserves those
attributes on alternate body paragraphs. This lets WordPress/Data Liberation
workflows trace secondary-rendition blocks back to the exact OPF rootfile and
spine resource after import. This remains lane-local native PHP without
invoking upstream Pandoc, live fetching, shelling out to converters, EPUBCheck,
full fixture corpus parity, arbitrary multi-rendition package graph validation
beyond the current container-rootfile path, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` now exercises multiple alternate EPUB rootfiles in
one container. The rootfile matrix fixture imports both `ALT/package.opf` and
`MOBILE/package.opf` as ordered `epub-alternate-rootfile` document divs while
preserving both summaries under `epubAlternateRootfilePackages`. Each imported
div carries `data-epub-rootfile`, rootfile id/full-path, rootfile properties,
media type, title, and language provenance, so WordPress/Data Liberation
workflows can distinguish print/mobile/other renditions as block-convertible
content without manually unpacking metadata. This remains lane-local native PHP
without invoking upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, arbitrary multi-rendition package graph
validation beyond the current container-rootfile path, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises native body import for EPUB alternate
rootfiles. A multi-rootfile EPUB now keeps detailed `epubAlternateRootfilePackages`
metadata for `ALT/package.opf` and also appends the readable alternate rendition
as a normal document `div` with the `epub-alternate-rootfile` class and
`data-epub-rootfile`/media/title/language provenance attributes. This lets
WordPress import secondary EPUB renditions as block-convertible content without
requiring callers to manually unpack alternate-body metadata. This remains
lane-local native PHP without invoking upstream Pandoc, live fetching, shelling
out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graph validation beyond the current alternate-rootfile
path, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises EPUB3 primary package scalar metadata
aliases for Data Liberation export/import. Generated primary OPF and NCX
output now honors `packageId`, `packageUniqueIdentifierId`, package
prefix/direction/language, `modified`, page progression, spine/NCX IDs, NCX
path/UID/depth/page counts/title/page-list labels, `dublinCoreMetadata`, and
`pageListEntries`, then reader round-trip exposes canonical `epubPackage*`,
`epubProperties`, `epubSpine*`, `epubTocResources`, and `epubNcx*` metadata.
This lets WordPress exporters place structured package metadata directly on
the document without pre-normalizing every field to epub-prefixed keys. This
remains lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graphs beyond the current alternate-rootfile path, DRM
decryption, XML signature cryptographic validation, generic TeX-to-MathML
conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises EPUB3 primary navigation metadata aliases
for Data Liberation export/import. Primary generated `nav.xhtml` now honors
`navRootAttributes`, `navBodyAttributes`, TOC/landmark/page-list section
aliases, TOC/landmark/page-list entry aliases, and `auxiliaryNavSections`,
then reader round-trip exposes canonical `epubNav*`, `epubToc*`,
`epubLandmark*`, `epubPageList*`, and `epubAuxiliaryNavSections` metadata.
This lets WordPress exporters use the same structured nav metadata names for
primary EPUB packages that generated alternate rootfiles already accept. This
remains lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graphs beyond the current alternate-rootfile path,
direct merge of all alternate bodies into the main document children, DRM
decryption, XML signature cryptographic validation, generic TeX-to-MathML
conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises EPUB3 OPF package-version normalization
for Data Liberation export/import. Generated primary and alternate rootfile
packages now serialize OPF `version="3.0"` even when metadata requests
`packageVersion` 3.3, and reader round-trip metadata for both packages reports
`epubPackageVersion` 3.0 without `unsupported-package-version` diagnostics.
This keeps EPUB3 minor metadata inputs from producing generated packages that
the reader would treat as unsupported. This remains lane-local native PHP
without invoking upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, arbitrary multi-rendition package
graphs beyond the current alternate-rootfile path, direct merge of all
alternate bodies into the main document children, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises generated alternate-rootfile EPUB3
non-linear fallback spine resource round-tripping for Data Liberation
export/import. `ALT/nonlinear.opf` now proves an alternate rendition can keep
an opaque non-linear widget itemref, manifest fallback/fallback-style
attributes, packaged fallback SVG/XHTML/style resources, and non-linear
itemref properties. Reader round-trip metadata exposes
`epubFallbackSpineResources` under `epubAlternateRootfilePackages`, while the
widget payload and fallback body remain excluded from alternate readable body
text. This remains lane-local native PHP without invoking upstream Pandoc,
live fetching, shelling out to converters, EPUBCheck, full fixture corpus
parity, arbitrary multi-rendition package graphs beyond the current
alternate-rootfile path, direct merge of all alternate bodies into the main
document children, DRM decryption, XML signature cryptographic validation,
generic TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises EPUB3 non-linear fallback spine resource
round-tripping for Data Liberation export/import. Generated EPUB3 packages now
keep explicit non-linear itemrefs for opaque foreign resources when the
manifest fallback chain reaches readable XHTML, including packaged fallback
SVG/XHTML/style resources and non-linear itemref properties. Reader round-trip
metadata exposes `epubFallbackSpineResources` while the widget payload and
fallback body remain excluded from imported linear body text. This remains
lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graphs beyond the current alternate-rootfile path,
direct merge of all alternate bodies into the main document children, DRM
decryption, XML signature cryptographic validation, generic TeX-to-MathML
conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises EPUB3 non-linear fallback spine
provenance for Data Liberation EPUB import. Selected packages and alternate
rootfile summaries now expose `epubFallbackSpineResources` when a non-linear
foreign spine item has a readable XHTML fallback, including the source widget
path, fallback XHTML path, fallback idref, and media types. The fallback XHTML
body remains excluded from imported body text. This remains lane-local native
PHP without invoking upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, arbitrary multi-rendition
package graphs beyond the current alternate-rootfile path, direct merge of all
alternate bodies into the main document children, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises EPUB3 readable fallback non-linear
navigation diagnostics for Data Liberation EPUB import. Landmark and page-list
links to XHTML that is the readable fallback of a non-linear foreign spine item
now surface non-linear spine diagnostics with the source widget `idref`, rather
than being treated as loose manifest resources outside the spine. This keeps
review metadata accurate when an auxiliary or backmatter entry is delivered via
fallback XHTML. This remains lane-local native PHP without invoking upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, arbitrary multi-rendition package graphs beyond the current
alternate-rootfile path, direct merge of all alternate bodies into the main
document children, DRM decryption, XML signature cryptographic validation,
generic TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises EPUB3 readable fallback encrypted linear
spine coverage for Data Liberation EPUB import. OCF encryption diagnostics now
treat fallback XHTML as linear spine content when an OPF `itemref` points at a
foreign resource whose fallback chain resolves to that XHTML. This surfaces
`encrypted-linear-spine-resource` warnings for the actual readable body that
will be imported, while preserving `epubEncryptedResources` metadata and the
fallback body import. This remains lane-local native PHP without invoking
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, arbitrary multi-rendition package graphs beyond the
current alternate-rootfile path, direct merge of all alternate bodies into the
main document children, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises EPUB3 readable fallback spine hyperlink
coverage for Data Liberation EPUB import. Primary packages and alternate
rootfile summaries now treat fallback XHTML as spine-covered when an OPF
`itemref` points at a foreign resource whose fallback chain resolves to that
XHTML. This avoids false `missing-spine-hyperlink-target` diagnostics while
still importing the fallback body and tracking rewritten local references. This
remains lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graphs beyond the current alternate-rootfile path,
direct merge of all alternate bodies into the main document children, DRM
decryption, XML signature cryptographic validation, generic TeX-to-MathML
conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises EPUB3 malformed readable spine XHTML
diagnostics for Data Liberation EPUB import. Primary package diagnostics now
identify malformed spine XHTML by `idref` and package path while preserving
best-effort body text for import, and alternate rootfile package diagnostics
stay scoped under `epubAlternateRootfilePackages` with their own `idref` and
path. This lets WordPress import tooling surface broken chapter resources
without collapsing the entire EPUB intake or mixing alternate-package errors
into the primary package. This remains lane-local native PHP without invoking
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, arbitrary multi-rendition package graphs beyond the
current alternate-rootfile path, direct merge of all alternate bodies into the
main document children, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` and `tests/EpubWriterTest.php` exercise EPUB3
fixed-layout page-spread center handling for Data Liberation EPUB import and
export. Source packages now accept `rendition:page-spread-center` without an
invalid-property diagnostic and expose `pageSpread: center` on
`epubSpineItemRefs`. Generated primary and alternate-rootfile EPUB3 packages
emit `rendition:page-spread-center` from `pageSpread: center` metadata and
round-trip it through `epubAlternateRootfilePackages`. This lets WordPress
retain centered fixed-layout pages instead of reducing page placement to only
left/right spreads. This remains lane-local native PHP without invoking
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, arbitrary multi-rendition package graphs beyond the
current alternate-rootfile path, direct merge of all alternate bodies into the
main document children, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises EPUB3 alternate-rootfile body block
source provenance for Data Liberation EPUB import. `ALT/viewports.opf` now
exposes `epubBodyBlockSources` next to `epubBodyAst` and `epubBodyBlocks`,
recording block indexes, paths, idrefs, and spine indexes for the two-page
alternate spine while preserving heading and paragraph AST records, viewport
metadata, text, and WordPress block projections. This lets WordPress import
tooling reconcile alternate rendition content with its original package
resources instead of treating a multi-page alternate body as anonymous merged
blocks. This remains lane-local native PHP without invoking upstream Pandoc,
live fetching, shelling out to converters, EPUBCheck, full fixture corpus
parity, arbitrary multi-rendition package graphs beyond the current
alternate-rootfile path, direct merge of all alternate bodies into the main
document children, DRM decryption, XML signature cryptographic validation,
generic TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises generated EPUB3 alternate-rootfile
structured body AST metadata for Data Liberation EPUB import. `ALT/package.opf`
now exposes `epubBodyAst` and `epubBodyBlocks` for its readable alternate
spine, preserving heading and paragraph node types, attrs, and child text
nodes alongside the existing text and WordPress block summaries. This lets
WordPress import tooling inspect alternate rendition bodies as structured
content instead of reparsing flattened text or rendered block markup. This
remains lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graphs, direct merge of all alternate bodies into the
main document children, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises generated EPUB3 alternate-rootfile
primary-body metadata isolation for Data Liberation EPUB export. Structured
`ALT/reused.opf` output can reuse the primary AST body while keeping its own
package id, unique identifier, prefix, language/direction, `dc:identifier`,
title, manifest, and spine. The primary OPF still keeps its own Dublin Core
records, metadata links/properties, bindings, collections, guide reference,
rendition/media metadata, spine, NCX, and nav attributes. This lets WordPress
import/export tooling author alternate renditions from shared content without
primary package metadata corrupting alternate package identity. This remains
lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graphs, full alternate body import into the main AST,
DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises generated EPUB3 alternate-rootfile
identifier option precedence for Data Liberation EPUB export. Structured
`ALT/print.opf` output keeps `urn:uuid:structured-print-rendition` as its
`dc:identifier` and round-trips that identifier under
`epubAlternateRootfilePackages`, even when the primary writer has a
conflicting `identifier` option. This lets WordPress import/export tooling
author alternate renditions without primary export identifiers corrupting
alternate package identity. This remains lane-local native PHP without
invoking upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, arbitrary multi-rendition package
graphs, full alternate body import into the main AST, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises generated EPUB3 alternate-rootfile
modified timestamp option precedence for Data Liberation EPUB export.
Structured `ALT/resources.opf` output emits a valid `dcterms:modified` value
that is not the primary writer timestamp when the alternate package omits its
own modified metadata, while the primary OPF still keeps the fixed primary
`modified` option. This lets WordPress import/export tooling author alternate
renditions without primary export timestamps corrupting alternate package
metadata. This remains lane-local native PHP without invoking upstream Pandoc,
live fetching, shelling out to converters, EPUBCheck, full fixture corpus
parity, arbitrary multi-rendition package graphs, full alternate body import
into the main AST, DRM decryption, XML signature cryptographic validation,
generic TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises generated EPUB3 alternate-rootfile
resource media-type option precedence for Data Liberation EPUB export.
Structured `ALT/resources.opf` output keeps an untyped no-extension alternate
resource as `application/octet-stream` and leaves its payload untouched, even
when primary writer options declare `resourceMediaTypes` for that same
alternate path as `text/css`. Explicitly typed alternate CSS and image
resources still keep their own media types and rewritten local CSS references.
This lets WordPress import/export tooling author alternate renditions without
primary export options changing alternate package manifest media types or CSS
resource rewriting. This remains lane-local native PHP without invoking
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, arbitrary multi-rendition package graphs, full
alternate body import into the main AST, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises generated EPUB3 alternate-rootfile cover
option precedence for Data Liberation EPUB export. Structured
`ALT/guide-cover.opf` output keeps `alt-cover` as the OPF cover and marks only
`ALT/images/cover.png` as `cover-image`, even when primary writer options
point `coverImage` at a different packaged image inside the alternate
rendition. The conflicting `ALT/images/primary-option-cover.png` resource
remains a normal manifest image and round-trips separately under
`epubAlternateRootfilePackages`. This lets WordPress import/export tooling
author alternate renditions without primary export options corrupting alternate
package cover metadata. This remains lane-local native PHP without invoking
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, arbitrary multi-rendition package graphs, full
alternate body import into the main AST, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises generated EPUB3 alternate-rootfile scalar
option precedence for Data Liberation EPUB export. Structured `ALT/print.opf`
output keeps `version="3.0"`, `book-id`, its own identifier/title/language,
and clean generated XHTML even when primary writer options define conflicting
package version, package id, unique identifier id, package prefix,
direction/language, page progression, spine id, Dublin Core records, package
rendition/media metadata, viewport, and spine direction values. This lets
WordPress import/export tooling author alternate renditions without primary
export options corrupting alternate package identity, layout/media metadata,
or XHTML viewport/direction. This remains lane-local native PHP without
invoking upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, arbitrary multi-rendition package
graphs, full alternate body import into the main AST, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises generated EPUB3 alternate-rootfile NCX
option precedence for Data Liberation EPUB export. Structured `ALT/ncx.opf`
output keeps `alt-toc` and `ALT/toc.ncx`, including its own `navMap`,
`pageTarget`, and `navList` metadata, even when primary writer options define
conflicting `includeNcx`, `ncxPath`, and `ncxId` values that emit
`primary-toc` at `OEBPS/primary-toc.ncx` for the primary OPF. This lets
WordPress import/export tooling author alternate compatibility navigation
without primary NCX export options corrupting alternate-rendition OPF/NCX
structure. This remains lane-local native PHP without invoking upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, arbitrary multi-rendition package graphs, full alternate body
import into the main AST, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises generated EPUB3 alternate-rootfile spine
manifest/id option precedence for Data Liberation EPUB export. Structured
`ALT/split.opf` output keeps generated `chapter-1` / `chapter-2` IDs and its
own manifest properties/fallback attributes even when primary writer options
define conflicting spine manifest IDs, properties, and attributes. Structured
`ALT/fixed.opf` output keeps `alt-page-one-ref` / `alt-page-two-ref` and its
fixed-layout rendition itemref properties even when primary spine item options
are present. This lets WordPress import/export tooling author split and
fixed-layout alternate renditions without primary spine export options
corrupting alternate-rendition OPF spine structure. This remains lane-local
native PHP without invoking upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, arbitrary multi-rendition
package graphs, full alternate body import into the main AST, DRM decryption,
XML signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises generated EPUB3 alternate-rootfile
package structure option precedence for Data Liberation EPUB export.
Structured `ALT/aliases.opf` output keeps its own metadata properties,
metadata links, bindings, collections, package-link sidecars, and linked
payload extraction even when primary writer options define conflicting package
structures. Structured `ALT/overlay-generated.opf` output also keeps its
generated SMIL overlay when primary `mediaOverlays` options are present. This
lets WordPress import/export tooling author multi-rendition EPUBs without
primary export options corrupting alternate-rendition OPF package structures.
This remains lane-local native PHP without invoking upstream Pandoc, live
fetching, shelling out to converters, EPUBCheck, full fixture corpus parity,
arbitrary multi-rendition package graphs, full alternate body import into the
main AST, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises generated EPUB3 alternate-rootfile
modified timestamp support for Data Liberation EPUB export. Structured
`ALT/aliases.opf` output can now set `modified`, `packageModified`, or
`epubModified` metadata so the alternate OPF emits and round-trips its own
`dcterms:modified` package timestamp while the primary OPF keeps the primary
writer timestamp. This lets WordPress import/export tooling author
multi-rendition EPUBs with independent package-modified metadata per
rendition. This remains lane-local native PHP without invoking upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, arbitrary multi-rendition package graphs, full alternate body
import into the main AST, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises generated EPUB3 alternate-rootfile
metadata option precedence for Data Liberation EPUB export. Structured
`ALT/aliases.opf` output now keeps its own package id, unique identifier,
package prefix, direction/language metadata, Dublin Core records,
package-link sidecars, and handler XHTML media type even when primary writer
options specify conflicting package metadata and resource media types. This
lets WordPress import/export tooling author multi-rendition EPUBs without
primary export options corrupting alternate-rendition OPF identity. This
remains lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graphs, full alternate body import into the main AST,
DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises generated EPUB3 alternate-rootfile
structured metadata identity for Data Liberation EPUB export. Structured
`ALT/aliases.opf` output can now set `packageUniqueIdentifierId`, emit matching
`dc:identifier`, `dc:title`, `dc:creator`, and `dc:language` records through
`dublinCoreMetadata`, include OPF `file-as` / `role` attributes and
`title-type` refinements, preserve package-link sidecars, and read the
selected alternate identifier plus Dublin Core metadata back under
`epubAlternateRootfilePackages`. This lets WordPress import/export tooling
author alternate renditions whose OPF identity metadata is independent from
the primary rendition. This remains lane-local native PHP without invoking
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, arbitrary multi-rendition package graphs, full
alternate body import into the main AST, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises generated EPUB3 alternate-rootfile
explicit non-linear spine resource authoring for Data Liberation EPUB export.
Structured `ALT/nonlinear.opf` output can now accept `nonLinearSpineItems`,
package an extra `ALT/text/appendix.xhtml` XHTML resource, write it as a
`linear="no"` itemref with derived `rendition:page-spread-left` metadata, link
it from EPUB landmarks, and read the summary back under
`epubAlternateRootfilePackages` as an `epubNonLinearResources` entry. This lets
WordPress import/export tooling preserve alternate-rendition appendix,
backmatter, or reference XHTML resources without merging their body text into
the linear reading flow. This remains lane-local native PHP without invoking
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, arbitrary multi-rendition package graphs, full
alternate body import into the main AST, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises generated EPUB3 alternate-rootfile CSS
media-type rewriting for Data Liberation EPUB export. Structured
`ALT/resources.opf` output can now declare a no-extension `ALT/styles/theme`
resource as `text/css`, rewrite local package-path image references inside
that CSS payload to the correct relative URL, emit matching OPF manifest media
types, and read those media types back under `epubAlternateRootfilePackages`.
This lets WordPress import/export tooling preserve resource URLs for alternate
renditions even when CSS assets are extensionless or storage-key based. This
remains lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graphs, full alternate body import into the main AST,
DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises generated EPUB3 alternate-rootfile
media-overlay authoring for Data Liberation EPUB export. Structured
`ALT/overlay-generated.opf` output can now accept `mediaOverlays` plus
package-level media metadata, generate the SMIL overlay resource, write
chapter `media-overlay` manifest linkage and packaged audio, and read overlay
summaries back under `epubAlternateRootfilePackages`. This lets WordPress
import/export tooling author narrated alternate renditions without depending
on prebuilt alternate OPF/SMIL payloads. This remains lane-local native PHP
without invoking upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, arbitrary multi-rendition package
graphs, full alternate body import into the main AST, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises generated EPUB3 alternate-rootfile
`spineManifestAttributes` aliases for Data Liberation EPUB export.
Structured `ALT/split.opf` output can now accept unprefixed
`spineManifestAttributes`, write `fallback` and `fallback-style` values onto
generated alternate OPF chapter items, package the referenced fallback
resources, and read fallback metadata back under `epubSpineItemRefs`. This
lets WordPress import/export tooling author multi-chapter alternate
renditions without depending on the internal `epubSpineManifestAttributes`
key. This remains lane-local native PHP without invoking upstream Pandoc,
live fetching, shelling out to converters, EPUBCheck, full fixture corpus
parity, arbitrary multi-rendition package graphs, full alternate body import
into the main AST, DRM decryption, XML signature cryptographic validation,
generic TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises generated EPUB3 alternate-rootfile
`spineManifestProperties` aliases for Data Liberation EPUB export.
Structured `ALT/split.opf` output can now accept unprefixed
`spineManifestProperties`, write those values onto generated alternate OPF
chapter items, and read them back under
`epubSpineItemRefs[*].manifestProperties`. This lets WordPress import/export
tooling author multi-chapter alternate renditions without depending on the
internal `epubSpineManifestProperties` key. This remains lane-local native
PHP without invoking upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, arbitrary multi-rendition
package graphs, full alternate body import into the main AST, DRM decryption,
XML signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises generated EPUB3 remote-head resource
property inference for Data Liberation EPUB export. A generated primary
chapter now receives the OPF `remote-resources` manifest property when its
XHTML head contains a remote `<base>`, remote `<link href>`, remote
`imagesrcset` candidate, or inline CSS `@import`/`url(...)` remote reference.
Structured alternate-rootfile output also marks `ALT/print.opf` chapter
metadata with `remote-resources` when alternate head CSS references a remote
asset, and read-back avoids missing remote-resource diagnostics. This remains
lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graphs, full alternate body import into the main AST,
DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises alternate rootfile OPF metadata links and
linked payloads for Data Liberation EPUB intake. A valid primary package can
carry `ALT/metadata.opf` with Dublin Core creator refinements, package
metadata properties, OPF metadata links, and non-manifested linked XML/JSON
metadata resources. The reader now reports `epubMetadataProperties`,
`epubMetadataLinks`, `epubPackageLinkResources`, extracted payloads, readable
resources, and body text under
`epubAlternateRootfilePackages['ALT/metadata.opf']`. This remains lane-local
native PHP without invoking upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, arbitrary multi-rendition
package graphs, full alternate body import into the main AST, DRM decryption,
XML signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises alternate rootfile table/style conversion
for Data Liberation EPUB intake. A valid primary package can carry
`ALT/tables.opf` with an XHTML spine page containing a styled table,
colgroups, header/cell attributes, rowspans, linked CSS, inline CSS
`@import`/`url(...)` references, and image backgrounds. The reader now reports
readable resources, image resources, referenced CSS/image paths, extracted
payloads, semantic table metadata, body text, and generated WordPress table
markup under `epubAlternateRootfilePackages['ALT/tables.opf']`. This remains
lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graphs, full alternate body import into the main AST,
DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises alternate rootfile footnote conversion
for Data Liberation EPUB intake. A valid primary package can carry
`ALT/notes.opf` with an XHTML spine page containing an
`epub:type="noteref"` link and an aside `epub:type="footnote"`. The reader now
reports readable resources, body text, generated native WordPress footnote
markup, and semantic footnote metadata under
`epubAlternateRootfilePackages['ALT/notes.opf']` while dropping duplicate
aside wrappers and original backlinks from generated block output. This
remains lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, arbitrary
multi-rendition package graphs, full alternate body import into the main AST,
DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises alternate rootfile fixed-layout spine
aliases for Data Liberation EPUB export. A structured alternate rendition can
now use unprefixed `spineItemRefs` plus package-level `rendition*` fields to
generate `ALT/fixed.opf` with page-progression direction, fixed-layout
package metadata, per-spine itemref ids/properties, OPF
`rendition:viewport` refinements, XHTML viewport metas, and reader round-trip
`epubSpineItemRefs`, `epubViewports`, and `epubViewport` under
`epubAlternateRootfilePackages['ALT/fixed.opf']`. This remains lane-local
native PHP without invoking upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, arbitrary multi-rendition
package graphs, full alternate body import into the main AST, DRM decryption,
XML signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises alternate rootfile viewport summaries for
Data Liberation EPUB intake. A valid primary package can carry
`ALT/viewports.opf` with two viewport-bearing XHTML pages, and the reader now
reports `epubViewports` and `epubViewport` under
`epubAlternateRootfilePackages['ALT/viewports.opf']` alongside readable
resources and recovered body text. This lets WordPress import tooling inspect
fixed-layout/mobile viewport metadata for alternate renditions before
preserving or transforming them. This remains lane-local native PHP without
invoking upstream Pandoc, live fetching, shelling out to converters, EPUBCheck,
full fixture corpus parity, arbitrary multi-rendition package graphs, full
alternate body import into the main AST, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises alternate rootfile non-linear and image
resource summaries for Data Liberation EPUB intake. A valid primary package
can carry `ALT/resources.opf`, whose alternate spine has a readable linear
chapter plus a non-linear appendix and image assets. The reader now reports
`epubNonLinearResources`, `epubImageResources`, cover image, referenced body
image, body text, and generated WordPress block markup under
`epubAlternateRootfilePackages['ALT/resources.opf']` while keeping the
non-linear appendix body out of readable body text. This remains lane-local
native PHP without invoking upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, arbitrary multi-rendition
package graphs, full alternate body import into the main AST, DRM decryption,
XML signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises alternate rootfile media overlay
summaries for Data Liberation EPUB intake. A valid primary package can carry
`ALT/overlay.opf`, whose alternate spine item links to
`ALT/overlays/chapter.smil`. The reader now reports package media metadata,
overlay resources, SMIL root/body attributes, sequences, text/audio targets,
pairs, spine/manifest `media-overlay` links, and recovered alternate body text
under `epubAlternateRootfilePackages['ALT/overlay.opf']`, so import tooling
can review narrated alternate renditions before preserving or transforming
them. This remains lane-local native PHP without invoking upstream Pandoc,
live fetching, shelling out to converters, EPUBCheck, full fixture corpus
parity, arbitrary multi-rendition package graphs, full alternate body import
into the main AST, DRM decryption, XML signature cryptographic validation,
generic TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises alternate rootfile fallback spine
resources for Data Liberation EPUB intake. A valid primary package can carry
`ALT/fallback.opf`, whose alternate spine item starts as an
`application/x-demo-widget`, falls through SVG, and resolves to
`ALT/text/fallback.xhtml`. The reader now reports that chain, referenced
assets, recovered body text, and generated WordPress block markup under
`epubAlternateRootfilePackages['ALT/fallback.opf']`, so import tooling can
explain why an alternate rendition was readable even though its declared spine
resource was not directly consumable. This remains lane-local native PHP
without invoking upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, arbitrary multi-rendition package
graphs, full alternate body import into the main AST, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises alternate rootfile rich XHTML spine
metadata for Data Liberation EPUB intake. A valid primary package can carry
`ALT/semantic.opf`, and the reader now reports the alternate spine item's
viewport, language/direction, root/body attributes, head metadata, semantic
section/link records, local iframe resources, body text, and generated
WordPress block markup under
`epubAlternateRootfilePackages['ALT/semantic.opf']`. This lets WordPress
import tooling review, route, preserve, or transform an alternate rendition
with its XHTML structure visible instead of treating it as opaque package
metadata. This remains lane-local native PHP without invoking upstream Pandoc,
live fetching, shelling out to converters, EPUBCheck, full fixture corpus
parity, arbitrary multi-rendition package graphs, full alternate body import
into the main AST, DRM decryption, XML signature cryptographic validation,
generic TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises scoped alternate rootfile diagnostics for
Data Liberation EPUB intake. A valid primary package can now carry a broken
alternate OPF, and the reader reports that alternate package's OPF/nav/NCX
problems under `epubAlternateRootfilePackages['ALT/broken.opf']` with
diagnostic totals instead of leaking alternate paths into the primary
`epubDiagnostics`. The scenario proves a non-linear alternate nav TOC target
and invalid alternate NCX `playOrder` are preserved for reviewer routing, so
WordPress import tooling can flag or quarantine one rendition without
misclassifying the primary EPUB. This remains lane-local native PHP without
invoking upstream Pandoc, live fetching, shelling out to converters, EPUBCheck,
full fixture corpus parity, arbitrary multi-rendition package graphs, full
alternate body import into the main AST, richer XHTML spine conversion, DRM
decryption, XML signature cryptographic validation, generic TeX-to-MathML
conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises alternate rendition NCX compatibility
for Data Liberation EPUB export. Writer metadata can now give a generated
alternate rootfile unprefixed NCX/navigation fields such as `includeNcx`,
`ncxPath`, `ncxId`, `ncxUid`, `tocEntries`, `pageListEntries`, and
`ncxNavLists`. The generated alternate OPF emits a scoped `toc.ncx` manifest
item and spine `toc` pointer without leaking that resource into the primary
OPF. The reader round-trip exposes `epubTocResources`, promoted `epubNcx*`
metadata, `epubTocEntries`, `epubPageListEntries`, and `epubNcxNavLists`
under `epubAlternateRootfilePackages`, so WordPress import/export review can
inspect compatibility navigation before preserving, routing, or transforming
alternate renditions. This remains lane-local native PHP without invoking
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, arbitrary multi-rendition package graphs, full
alternate body import into the main AST, richer XHTML spine conversion, DRM
decryption, XML signature cryptographic validation, generic TeX-to-MathML
conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises alternate rendition guide/cover metadata
for Data Liberation EPUB export. Writer metadata can now give a generated
alternate rootfile unprefixed `coverImage` and `guideReferences` fields, and
the alternate OPF emits the cover-image manifest property, OPF cover metadata,
and legacy guide references while keeping the cover resource scoped to that
rendition. The reader round-trip exposes `epubCoverImage`,
`epubGuideReferences`, and extracted cover payload bytes under
`epubAlternateRootfilePackages`, so WordPress import/export review can inspect
cover/start metadata for alternate renditions before preserving, routing, or
transforming them. This remains lane-local native PHP without invoking
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, arbitrary multi-rendition package graphs, full
alternate body import into the main AST, richer XHTML spine conversion, DRM
decryption, XML signature cryptographic validation, generic TeX-to-MathML
conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises alternate rendition package metadata
aliases and package-link sidecar summaries for Data Liberation EPUB export.
Writer metadata can now give a generated alternate rootfile unprefixed package
authoring fields such as metadata properties, OPF metadata links, bindings,
collections, package attributes, spine ids, and page progression direction.
The generated alternate OPF keeps XML/JSON/audio package-link sidecars scoped
to that rendition, and the reader round-trip exposes bindings, collections,
package-link resources, and extracted sidecar payloads under
`epubAlternateRootfilePackages`. This remains lane-local native PHP without
invoking upstream Pandoc, live fetching, shelling out to converters, EPUBCheck,
full fixture corpus parity, arbitrary multi-rendition package graphs, full
alternate body import into the main AST, richer XHTML spine conversion, DRM
decryption, XML signature cryptographic validation, generic TeX-to-MathML
conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises alternate rendition resource manifest
metadata for Data Liberation EPUB export. Writer metadata can now give a
generated alternate rootfile its own resource payload aliases and manifest
resource metadata, including explicit manifest ids, media types, SVG/scripted
properties, and fallback chains. The reader round-trip exposes those resources
under `epubAlternateRootfilePackages`, so WordPress import/export review can
inspect custom alternate-rendition resources before routing, preserving, or
transforming them. This remains lane-local native PHP without invoking
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, arbitrary multi-rendition package graphs, full
alternate body import into the main AST, richer XHTML spine conversion, DRM
decryption, XML signature cryptographic validation, generic TeX-to-MathML
conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises split structured EPUB alternate rendition
generation for Data Liberation export. Writer metadata can now give an
alternate rootfile its own `splitLevel`, so a generated alternate rendition can
emit multiple linear XHTML spine chapters, ordered OPF itemrefs, and nav
fragment targets while keeping the primary OPF manifest clean. The reader
round-trip exposes both alternate spine resources through
`epubAlternateRootfilePackages`, with combined body text and generated
WordPress block markup for review/routing. This remains lane-local native PHP
without invoking upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, arbitrary multi-rendition package
graphs, full alternate body import into the main AST, richer XHTML spine
conversion, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises structured EPUB alternate rendition
generation for Data Liberation export. Writer metadata can now provide
`epubAlternateRootfiles` / `alternateRootfiles` entries that generate an
alternate OPF package, nav XHTML, readable spine XHTML, and local resources
such as a stylesheet, while keeping the primary OPF manifest clean and
advertising the rendition through `META-INF/container.xml`. The reader
round-trip exposes that generated rendition through
`epubAlternateRootfilePackages` with title/language metadata, manifest and
spine counts, readable body text, WordPress block markup, and referenced local
resources. This remains lane-local native PHP without invoking upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, arbitrary multi-rendition package graphs, full alternate body
import into the main AST, richer XHTML spine conversion, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises EPUB alternate rendition body summaries
for Data Liberation multi-rendition intake. Alternate OPFs referenced from
`META-INF/container.xml` can now expose readable linear spine resources, block
count, normalized body text, generated WordPress block markup, and referenced
local resources under `epubAlternateRootfilePackages` while the selected OPF
remains the source for the primary document body. WordPress import review can
inspect alternate rendition contents before deciding whether to preserve,
route, merge, or transform them. This remains lane-local native PHP without
invoking upstream Pandoc, live fetching, shelling out to converters, EPUBCheck,
full fixture corpus parity, richer XHTML spine conversion, full alternate body
import into the main AST, full structured multi-rendition OPF generation, DRM
decryption, XML signature cryptographic validation, generic TeX-to-MathML
conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises EPUB alternate rootfile package summaries
for Data Liberation multi-rendition intake. Alternate OPFs referenced from
`META-INF/container.xml`, including authored `ALT/print.opf` outputs, now
surface under `epubAlternateRootfilePackages` with title/language metadata,
manifest resource counts, spine counts, and resource summaries while the
selected OPF remains the source for the main document body. WordPress import
review can inspect additional renditions before deciding whether to preserve,
route, or transform them. This remains lane-local native PHP without invoking
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, richer XHTML spine conversion, alternate rendition body
import, full structured multi-rendition OPF generation, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises EPUB alternate rootfile authoring for
Data Liberation EPUB export. Writer-provided `containerRootfilePayloads` can
now add an authored alternate OPF such as `ALT/print.opf`, advertise it in
`META-INF/container.xml` with rendition properties, keep the alternate OPF
bytes in the package, and avoid adding that OPF to the primary manifest.
WordPress export can package an additional rendition payload instead of only
preserving one that came from an imported EPUB. This remains lane-local native
PHP without invoking upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, richer XHTML spine conversion, full
structured multi-rendition OPF generation, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises EPUB OCF container-link payload
preservation for Data Liberation EPUB regeneration. Imported `container.xml`
links to local `META-INF` sidecars such as catalog JSON or ONIX/XML records now
carry their bytes through `epubOcfSidecarPayloads`, regenerate the linked
sidecar file, and keep the sidecar out of the OPF manifest. WordPress import
review can inspect and route those sidecar bytes by container-link metadata,
then export the package without silently dropping the linked record. This
remains lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, richer
XHTML spine conversion, generated multi-rendition authoring beyond preserved
OPF payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises EPUB OCF container-link output media-type
inference for Data Liberation EPUB regeneration. Imported `container.xml`
links to local OCF sidecars can omit `media-type`, and regenerated
`META-INF/container.xml` now derives the attribute from recognized sidecar
hrefs while preserving the linked sidecar payload bytes. WordPress export can
keep OCF metadata sidecars, encryption metadata, rights files, and signature
files intact without dropping useful type metadata on the container link. This
remains lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, richer
XHTML spine conversion, generated multi-rendition authoring beyond preserved
OPF payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises EPUB package-link output media-type
inference for Data Liberation EPUB regeneration. Generated top-level metadata
links and collection metadata record/voicing links now derive OPF
`media-type` from local sidecar hrefs when caller metadata omits the explicit
field, while direct collection member links are not over-annotated unless their
relation requires a media type. WordPress export can keep recovered metadata
sidecars out of `<manifest>`, preserve their bytes, and avoid generating OPF
links that immediately self-diagnose as `missing-package-link-media-type`.
This remains lane-local native PHP without invoking upstream Pandoc, live
fetching, shelling out to converters, EPUBCheck, full fixture corpus parity,
richer XHTML spine conversion, generated multi-rendition authoring beyond
preserved OPF payloads, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises EPUB collection package-link
missing-media extraction fallback for Data Liberation EPUB intake. The reader
still reports invalid package quality when a collection-level linked metadata
record omits `media-type`, but extracted JSON sidecar metadata now keeps
path-derived `application/json` and the payload bytes are preserved through
`epubResourcePayloads`. WordPress review can flag the malformed OPF
collection link while still routing the recovered sidecar by useful semantic
content type during review or regeneration. This remains lane-local native PHP
without invoking upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, richer XHTML spine conversion,
generated multi-rendition authoring beyond preserved OPF payloads, DRM
decryption, XML signature cryptographic validation, generic TeX-to-MathML
conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises EPUB spine/fallback missing-media
metadata fallback for Data Liberation EPUB review and regeneration. The reader
still reports invalid package quality when a manifest fallback item omits
`media-type`, but the spine metadata now carries path-derived
`image/svg+xml` for the fallback item and the terminal XHTML fallback-spine
resource stays usable as `application/xhtml+xml`. WordPress review can flag
the malformed OPF source while routing recovered fallback resources by useful
semantic media type instead of empty metadata. This remains lane-local native
PHP without invoking upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, richer XHTML spine
conversion, generated multi-rendition authoring beyond preserved OPF payloads,
DRM decryption, XML signature cryptographic validation, generic TeX-to-MathML
conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises EPUB manifest missing-media extraction
fallback for Data Liberation EPUB intake and regeneration. The reader still
reports invalid package quality when manifest items omit `media-type`, but
extracted JSON catalog sidecars and generic XML/ONIX sidecars now keep
path-derived `application/json` and `application/xml` metadata. WordPress
review can route preserved sidecar bytes by semantic content type, and
regenerated EPUB output repairs those manifest entries instead of carrying
empty or octet-stream media types forward. This remains lane-local native PHP
without invoking upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, richer XHTML spine conversion,
generated multi-rendition authoring beyond preserved OPF payloads, DRM
decryption, XML signature cryptographic validation, generic TeX-to-MathML
conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises EPUB manifest resource media-type
fallback for Data Liberation EPUB regeneration. Generated packages now infer
`application/json` and `application/xml` for resource payloads such as catalog
sidecars and ONIX/XML records when the import metadata did not provide
explicit media types, instead of manifesting them as
`application/octet-stream`. WordPress review/export can route sidecar assets by
semantic content type while still allowing explicit resource media-type
overrides. This remains lane-local native PHP without invoking upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, richer XHTML spine conversion, generated multi-rendition
authoring beyond preserved OPF payloads, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises EPUB package-link extracted media-type
fallback for Data Liberation EPUB intake. The reader still surfaces missing
OPF `media-type` diagnostics, but when resource extraction is enabled it now
records path-derived media types such as `application/json` for preserved
unmanifested metadata sidecars instead of reducing every missing declaration
to `application/octet-stream`. WordPress review can display and route extracted
metadata sidecars with a useful content type while still showing the package
quality issue. This remains lane-local native PHP without invoking upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, richer XHTML spine conversion, generated multi-rendition
authoring beyond preserved OPF payloads, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises EPUB metadata-link standalone payload
preservation for Data Liberation EPUB regeneration. The writer now keeps
non-content OPF metadata-link payloads, including non-`record` relations such
as alternate JSON records, out of `<manifest>` while still packaging the bytes
and preserving the OPF `<link/>` metadata. WordPress review can retain package
metadata sidecars without regenerating EPUBs that self-diagnose those sidecars
as invalid manifest resources. This remains lane-local native PHP without
invoking upstream Pandoc, live fetching, shelling out to converters, EPUBCheck,
full fixture corpus parity, richer XHTML spine conversion, generated
multi-rendition authoring beyond preserved OPF payloads, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` and `tests/EpubWriterTest.php` exercise EPUB
package-link payload preservation for Data Liberation EPUB intake and
regeneration. The reader can now extract valid unmanifested OPF package-link
resources when resource extraction is enabled, while the writer keeps
standalone `record` and `voicing` payloads out of `<manifest>` and still
packages their bytes. WordPress review can retain external metadata records
and voicing resources attached through OPF links without regenerating EPUBs
that self-diagnose those linked resources as invalid manifest entries. This
remains lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, richer
XHTML spine conversion, generated multi-rendition authoring beyond preserved
OPF payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises EPUB collection metadata link
import/export preservation for Data Liberation EPUB intake and regeneration.
The reader now preserves OPF collection metadata links as structured metadata
records, including href fragments, relation, language, direction, media type,
refines, id, and properties; the writer emits those records back as OPF
`<link/>` entries inside `<collection><metadata>`. WordPress review can keep
linked-record and voicing annotations attached to the collection metadata
instead of flattening them to text or dropping them during EPUB regeneration.
This remains lane-local native PHP without invoking upstream Pandoc, live
fetching, shelling out to converters, EPUBCheck, full fixture corpus parity,
richer XHTML spine conversion, generated multi-rendition authoring beyond
preserved OPF payloads, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises EPUB collection metadata link
relation/refines diagnostics for Data Liberation EPUB intake. The reader now
applies the OPF metadata link `record` / `voicing` relation contract to links
inside collection metadata, so collection metadata `record` links with
`refines` and `voicing` links without `refines` surface collection-aware
diagnostics while readable spine blocks are preserved. WordPress review can
surface the offending link id, href, relation, parent metadata element, and
collection id instead of silently treating these linked-record annotations as
valid. This remains lane-local native PHP without invoking upstream Pandoc,
live fetching, shelling out to converters, EPUBCheck, full fixture corpus
parity, richer XHTML spine conversion, generated multi-rendition authoring
beyond preserved OPF payloads, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises expanded EPUB SVG href resource-element
remote-resource diagnostics for Data Liberation EPUB intake. The reader now
scans SVG resource-bearing href elements such as `cursor`, `linearGradient`,
`pattern`, `mask`, `marker`, `clipPath`, `textPath`, and related legacy SVG
font/template elements during required manifest property checks, while still
excluding hyperlink-only SVG `<a href>` elements. WordPress review can surface
SVG manifest id, href, archive path, media type, and missing `remote-resources`
property when cursor or paint/template definitions point at remote resources.
This remains lane-local native PHP without invoking upstream Pandoc, live
fetching, shelling out to converters, EPUBCheck, full fixture corpus parity,
generated multi-rendition authoring beyond preserved OPF payloads, DRM
decryption, XML signature cryptographic validation, generic TeX-to-MathML
conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises EPUB SVG `font-face-uri` remote-resource
diagnostics for Data Liberation EPUB intake. The reader now scans SVG
`font-face-uri` elements with `href` / `xlink:href` alongside `image`, `use`,
`feImage`, and `script` href-bearing resource elements during required
manifest property checks, so `image/svg+xml` manifest resources report missing
`remote-resources` when SVG font face declarations point at remote font
resources. WordPress review can surface the SVG manifest id, href, archive
path, media type, and missing property while readable spine blocks are
preserved. This remains lane-local native PHP without invoking upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, generated multi-rendition authoring beyond preserved OPF
payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises EPUB SVG script remote-resource
diagnostics for Data Liberation EPUB intake. The reader now scans SVG `script`
elements with `href` / `xlink:href` alongside `image`, `use`, and `feImage`
href-bearing resource elements during required manifest property checks, so
`image/svg+xml` manifest resources report missing `remote-resources` when an
SVG script loads a remote script resource even if the manifest already declares
the separate `scripted` property. WordPress review can surface the SVG manifest
id, href, archive path, media type, and missing property while readable spine
blocks are preserved. This remains lane-local native PHP without invoking
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, generated multi-rendition authoring beyond preserved OPF
payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises EPUB SVG `feImage` remote-resource
diagnostics for Data Liberation EPUB intake. The reader now scans SVG filter
`feImage` elements alongside `image` and `use` elements during required
manifest property checks, so `image/svg+xml` manifest resources report missing
`remote-resources` when filter images point at remote resources through `href`
or `xlink:href`. WordPress review can surface the SVG manifest id, href,
archive path, media type, and missing property while readable spine blocks are
preserved. This remains lane-local native PHP without invoking upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, generated multi-rendition authoring beyond preserved OPF
payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises EPUB CSS unquoted URL remote-resource
diagnostics for Data Liberation EPUB intake. The reader now extracts
unquoted CSS `url(...)` values correctly during required manifest property
scans, so `text/css` manifest resources report missing `remote-resources`
when stylesheets contain unquoted remote URLs such as
`url(https://cdn.example.test/unquoted.png)`. WordPress review can surface the
stylesheet manifest id, href, archive path, media type, and missing property
while readable spine blocks are preserved. This remains lane-local native PHP
without invoking upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, generated multi-rendition authoring
beyond preserved OPF payloads, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises EPUB SMIL textref remote-resource
diagnostics for Data Liberation EPUB intake. The reader now treats
`textref` / `epub:textref` attributes in SMIL media overlay resources as
resource references during required manifest property scans, so
`application/smil+xml` manifest items report missing `remote-resources` when
sequence textrefs point at remote text documents even without a remote audio
`src`. WordPress review can surface the overlay manifest id, href, archive
path, media type, and missing property while readable spine blocks are
preserved. This remains lane-local native PHP without invoking upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, generated multi-rendition authoring beyond preserved OPF
payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises EPUB collection metadata link diagnostics
for Data Liberation EPUB intake. The reader now routes
`<collection><metadata><link>` records through the package-link diagnostics
path, so nested collection metadata links report missing `rel`, missing
required `media-type`, invalid content, URL/resource issues, and related link
attribute problems with `collectionId` context preserved. WordPress review can
surface the nested metadata parent and containing collection while readable
spine blocks are preserved. This remains lane-local native PHP without
invoking upstream Pandoc, live fetching, shelling out to converters, EPUBCheck,
full fixture corpus parity, generated multi-rendition authoring beyond
preserved OPF payloads, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises EPUB collection metadata value diagnostics
for Data Liberation EPUB intake. The reader now records
`empty-collection-metadata-value` when OPF collection-local `meta property`
records are empty after whitespace stripping. WordPress review can surface the
collection id, metadata id, and property name while missing-property and
OPF2-style collection metadata diagnostics stay separate and readable spine
blocks are preserved. This remains lane-local native PHP without invoking
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, generated multi-rendition authoring beyond preserved
OPF payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises EPUB package link content diagnostics for
Data Liberation EPUB intake. The reader now records
`invalid-package-link-content` when OPF package metadata links or collection
links contain non-whitespace text or nested child elements. WordPress review
can surface the link id, href, parent context, normalized text, or offending
child element name while readable spine blocks are preserved. This remains
lane-local native PHP without invoking upstream Pandoc, live fetching, shelling
out to converters, EPUBCheck, full fixture corpus parity, generated
multi-rendition authoring beyond preserved OPF payloads, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises EPUB package metadata value diagnostics
for Data Liberation EPUB intake. The reader now records
`empty-package-metadata-value` when optional Dublin Core metadata or ordinary
OPF `meta` property values are empty after whitespace stripping. WordPress
review can surface the element, id, property/refines, or Dublin Core name while
required fields, `dcterms:modified`, and `media:duration` keep their
specialized diagnostics and readable spine blocks are preserved. This remains
lane-local native PHP without invoking upstream Pandoc, live fetching, shelling
out to converters, EPUBCheck, full fixture corpus parity, generated
multi-rendition authoring beyond preserved OPF payloads, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises EPUB package metadata link
relation/refines diagnostics for Data Liberation EPUB intake. The reader now
records `invalid-package-link-record-refines` when `rel=record` includes
`refines` and `missing-package-link-voicing-refines` when `rel=voicing` omits
`refines`. WordPress review can surface the link id, href, parent context,
relation value, and refines value where applicable while readable spine blocks
are preserved. This remains lane-local native PHP without invoking upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, generated multi-rendition authoring beyond preserved OPF
payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises EPUB package link relation media-type
diagnostics for Data Liberation EPUB intake. The reader now records
`missing-package-link-media-type` when `rel` includes `record` or `voicing`
without `media-type`, including remote record links and collection records.
WordPress review can surface the link id, href, parent context, and relation
value while generic external links without those relation values remain
accepted and readable spine blocks are preserved. This remains lane-local
native PHP without invoking upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, generated multi-rendition
authoring beyond preserved OPF payloads, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises EPUB package link manifest-resource
diagnostics for Data Liberation EPUB intake. The reader now records
`invalid-package-link-manifest-resource` when a standalone linked metadata
record is also listed as an OPF manifest publication resource. WordPress review
can surface the link id, parent context, href, resolved archive path, manifest
id, manifest href, and manifest media type while remote links, unmanifested
local linked records, and spine/content document links remain accepted and
readable spine blocks are preserved. This remains lane-local native PHP
without invoking upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, generated multi-rendition authoring
beyond preserved OPF payloads, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises EPUB package metadata link media-type
diagnostics for Data Liberation EPUB intake. The reader now records
`missing-package-link-media-type` when a package-local metadata link references
an in-container resource without `media-type`. WordPress review can surface the
metadata link id, href, rel/property context, and parent element while remote
metadata links remain allowed without `media-type` and import continues with
readable spine blocks preserved. This remains lane-local native PHP without
invoking upstream Pandoc, live fetching, shelling out to converters, EPUBCheck,
full fixture corpus parity, generated multi-rendition authoring beyond
preserved OPF payloads, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises EPUB package metadata content-model
diagnostics for Data Liberation EPUB intake. The reader now records
`invalid-package-metadata-dublin-core-element` for unsupported `dc:*` metadata
children, `invalid-package-metadata-child-element` for OPF or foreign metadata
children outside the EPUB3 content model, and `multiple-metadata-date` when
more than one `dc:date` is present. WordPress review can surface element
names, ids, values, hrefs, namespaces, and duplicate date payloads while import
continues and readable spine blocks are preserved. This remains lane-local
native PHP without invoking upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, generated multi-rendition
authoring beyond preserved OPF payloads, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises EPUB cover-image property diagnostics for
Data Liberation EPUB intake. The reader now records
`invalid-cover-image-property-media-type` when a manifest item marks a non-image
resource as `cover-image`, and `multiple-cover-image-manifest-items` when more
than one item declares the reserved cover property. WordPress review can surface
the declared ids, hrefs, resolved paths, and media types while cover metadata
selection ignores invalid non-image declarations and selects the first valid
image cover. This remains lane-local native PHP without invoking upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, generated multi-rendition authoring beyond preserved OPF
payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises EPUB page-list value diagnostics for Data
Liberation EPUB intake. The reader now records
`nav-page-list-value-mismatch` warnings when a page-list link value or visible
label disagrees with the referenced target pagebreak value. WordPress review can
surface the nav id, link href, page-list value, target pagebreak value,
fragment, and target element/type while preserving the page-list entry for
editorial inspection. Existing page-list target-not-pagebreak errors,
duplicate-target checks, reading-order checks, missing-label, missing-href, nav
link path/fragment diagnostics, malformed token diagnostics, type prefix
diagnostics, and readable spine import behavior remain separate. This remains
lane-local native PHP without invoking upstream Pandoc, live fetching, shelling
out to converters, EPUBCheck, full fixture corpus parity, generated
multi-rendition authoring beyond preserved OPF payloads, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises EPUB navigation type prefix diagnostics
for Data Liberation EPUB intake. The reader now records
`undeclared-nav-type-prefix`, `undeclared-nav-landmark-link-type-prefix`, and
`undeclared-nav-page-list-link-type-prefix` when nav `epub:type` property
tokens use a non-reserved prefix that is not declared on the navigation
document `prefix` attribute. WordPress review can surface the nav id, link
href/label where applicable, offending value/type, and prefix while declared
and reserved prefixes remain accepted. Existing malformed token diagnostics,
duplicate landmark target checks, page-list duplicate-target, missing-label,
missing-href, reading-order checks, pagebreak target, nav link path/fragment,
and readable spine import behavior remain separate. This remains lane-local
native PHP without invoking upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, generated multi-rendition
authoring beyond preserved OPF payloads, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises EPUB navigation element type diagnostics
for Data Liberation EPUB intake. The reader now records `invalid-nav-type`
when a nav element declares a malformed `epub:type` property token such as
`bad:`. WordPress review can surface the nav id, full type value, and offending
token while the same nav still participates in specialized navigation handling
when another valid token such as `landmarks` is present. Existing landmarks and
page-list link type diagnostics, duplicate landmark target checks, page-list
duplicate-target, missing-label, missing-href, reading-order checks, pagebreak
target, nav link path/fragment, and readable spine import behavior remain
separate. This remains lane-local native PHP without invoking upstream Pandoc,
live fetching, shelling out to converters, EPUBCheck, full fixture corpus
parity, generated multi-rendition authoring beyond preserved OPF payloads, DRM
decryption, XML signature cryptographic validation, generic TeX-to-MathML
conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises specialized EPUB page-list navigation
diagnostics for Data Liberation EPUB intake. The reader now records
`invalid-nav-page-list-link-type` when a page-list link declares a malformed
`epub:type` property token such as `bad:`. WordPress review can surface the
nav id, link href, accessible label, and offending token while still
preserving the page-list entry for editorial inspection. Existing landmarks
type diagnostics, duplicate landmark target checks, page-list duplicate-target,
missing-label, missing-href, reading-order checks, pagebreak target, nav link
path/fragment, and readable spine import behavior remain separate. This
remains lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, generated
multi-rendition authoring beyond preserved OPF payloads, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises specialized EPUB landmarks navigation
diagnostics for Data Liberation EPUB intake. The reader now records
`invalid-nav-landmark-link-type` when a landmarks link declares a malformed
`epub:type` property token such as `bad:`. WordPress review can surface the
nav id, link href, accessible label, and offending token while still
preserving the raw nav entry for editorial inspection. Existing valid landmark
types, duplicate landmark target checks, page-list duplicate-target,
missing-label, missing-href, pagebreak target, nav link path/fragment, and
readable spine import behavior remain separate. This remains lane-local native
PHP without invoking upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, generated multi-rendition
authoring beyond preserved OPF payloads, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises remote XHTML base required-property
diagnostics for Data Liberation EPUB intake. The reader now records
`missing-manifest-required-property` with `property=remote-resources` when an
XHTML package resource has `<base href="https://...">` and package-relative
resource references such as stylesheet links or image sources. WordPress
review can surface the manifest id, href, package path, media type, and
missing property for resources such as `remote-base.xhtml` without treating
ordinary XHTML `<a href>` hyperlinks as package remote-resource errors.
Existing `background`, `imagesrcset`, head-link `href`, SVG href
remote-resource, XHTML/SVG MathML, inline SVG, scripted, switch, CSS, SMIL,
`src`, `srcset`, `poster`, `data`, `action`, and `formaction` checks remain
separate. This remains lane-local native PHP without invoking upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, generated multi-rendition authoring beyond preserved OPF
payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises XHTML `background` remote resource
required-property diagnostics for Data Liberation EPUB intake. The reader now
records `missing-manifest-required-property` with `property=remote-resources`
when an XHTML package resource includes a remote `background="https://..."`
resource attribute. WordPress review can surface the manifest id, href,
package path, media type, and missing property for resources such as
`background-remote.xhtml` without treating ordinary XHTML `<a href>`
hyperlinks as package remote-resource errors. Existing `imagesrcset`,
head-link `href`, SVG href remote-resource, XHTML/SVG MathML, inline SVG,
scripted, switch, CSS, SMIL, `src`, `srcset`, `poster`, `data`, `action`, and
`formaction` checks remain separate. This remains lane-local native PHP
without invoking upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, generated multi-rendition authoring
beyond preserved OPF payloads, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises XHTML `imagesrcset` remote resource
required-property diagnostics for Data Liberation EPUB intake. The reader now
records `missing-manifest-required-property` with `property=remote-resources`
when an XHTML package resource includes a remote candidate in `imagesrcset`,
including head `<link rel="preload" as="image" imagesrcset="https://...">`
resources. WordPress review can surface the manifest id, href, package path,
media type, and missing property for resources such as
`linked-imagesrcset.xhtml` without treating ordinary XHTML `<a href>`
hyperlinks as package remote-resource errors. Existing head-link `href`, SVG
href remote-resource, XHTML/SVG MathML, inline SVG, scripted, switch, CSS,
SMIL, `src`, `srcset`, `poster`, `data`, `action`, and `formaction` checks
remain separate. This remains lane-local native PHP without invoking upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, generated multi-rendition authoring beyond preserved OPF
payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises XHTML head-link remote resource
required-property diagnostics for Data Liberation EPUB intake. The reader now
records `missing-manifest-required-property` with `property=remote-resources`
when an XHTML package resource references a remote stylesheet through a head
`<link href="https://...">` element. WordPress review can surface the manifest
id, href, package path, media type, and missing property for resources such as
`linked-head.xhtml` without treating ordinary XHTML `<a href>` hyperlinks as
package remote-resource errors. Existing SVG href remote-resource,
XHTML/SVG MathML, inline SVG, scripted, switch, CSS, SMIL, `src`, `srcset`,
`poster`, `data`, `action`, and `formaction` checks remain separate. This
remains lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, generated
multi-rendition authoring beyond preserved OPF payloads, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises SVG href-based remote resource
required-property diagnostics for Data Liberation EPUB intake. The reader now
records `missing-manifest-required-property` with `property=remote-resources`
when an SVG package resource embeds a remote image through `image`/`use`
`href` or `xlink:href`. WordPress review can surface the manifest id, href,
package path, media type, and missing property for resources such as
`images/remote.svg` without treating ordinary XHTML hyperlinks as package
remote-resource errors. Existing XHTML/SVG MathML, inline SVG, scripted,
switch, CSS, SMIL, `src`, `srcset`, `poster`, `data`, `action`, and
`formaction` checks remain separate. This remains lane-local native PHP
without invoking upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, generated multi-rendition authoring
beyond preserved OPF payloads, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises NCX `content@src` fragment diagnostics
for Data Liberation EPUB intake. The reader now records
`invalid-ncx-content-src-fragment` for malformed local NCX navigation targets,
including empty fragments like `one.xhtml#` and decoded-whitespace fragments
like `one.xhtml#bad%20target`. WordPress review can surface the NCX path,
element/nav type, id/text, original `contentSrc`, rewritten href, resolved
target path, reason, and decoded fragment when present without collapsing the
issue into a misleading missing-spine-target or non-linear-spine diagnostic.
Invalid NCX paths, remote targets, fragment-only targets, missing spine
targets, non-linear spine targets, and play-order diagnostics remain separate
behavior. This remains lane-local native PHP without invoking upstream Pandoc,
live fetching, shelling out to converters, EPUBCheck, full fixture corpus
parity, generated multi-rendition authoring beyond preserved OPF payloads, DRM
decryption, XML signature cryptographic validation, generic TeX-to-MathML
conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises spine XHTML hyperlink href-fragment
diagnostics for Data Liberation EPUB intake. The reader now records
`invalid-spine-hyperlink-href-fragment` for malformed local `a@href` and
`area@href` fragments, including empty fragments like `appendix.xhtml#` and
decoded-whitespace fragments like `appendix.xhtml#bad%20target`. WordPress
review can surface the source XHTML path, element/attribute, original href,
resolved target path, reason, decoded fragment when present, and link text or
area alt context without collapsing the issue into a misleading unspined
target diagnostic. Missing unspined hyperlink targets, recursive scans from
linked content documents, non-linear spine targets, remote links, and
fragment-only self links remain separate behavior. This remains lane-local
native PHP without invoking upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, generated multi-rendition
authoring beyond preserved OPF payloads, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises SMIL media overlay text-fragment
diagnostics for Data Liberation EPUB intake. The reader now records
`invalid-media-overlay-text-src-fragment` for malformed local `text@src`
fragments and `invalid-media-overlay-seq-textref-fragment` for malformed local
`seq@epub:textref` fragments, including empty fragments like
`../text/chapter.xhtml#` and decoded-whitespace fragments like
`../text/chapter.xhtml#bad%20target`. WordPress review can surface
overlay/content context, SMIL element/id, original reference, resolved path,
reason, and decoded fragment without also showing misleading missing-resource
or missing-fragment follow-up diagnostics. Remote references, path
diagnostics, missing media overlay resources, missing target fragments, audio
clip timing, and target-order checks remain separate branches. This remains
lane-local native PHP without invoking upstream Pandoc, live fetching, shelling
out to converters, EPUBCheck, full fixture corpus parity, generated
multi-rendition authoring beyond preserved OPF payloads, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises EPUB navigation link href fragment
diagnostics for Data Liberation EPUB intake. The reader now records
`invalid-nav-link-href-fragment` when navigation document `a@href` values use
malformed local fragments such as `chapter.xhtml#` or
`chapter.xhtml#bad%20target`. WordPress review can surface the nav context,
link label/value, original href, resolved target path, reason, and decoded
fragment for whitespace cases without also showing misleading missing-resource
or missing-fragment follow-up diagnostics. Remote nav links, path diagnostics,
missing nav link resources, missing nav link target fragments, and page-list
pagebreak target checks remain separate branches. This remains lane-local
native PHP without invoking upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, generated multi-rendition
authoring beyond preserved OPF payloads, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises OPF guide-reference href fragment
diagnostics for Data Liberation EPUB intake. The reader now records
`invalid-guide-reference-href-fragment` when `guide/reference@href` values use
malformed local fragments such as `chapter.xhtml#` or
`chapter.xhtml#bad%20target`. WordPress review can surface the guide type,
title, href, resolved path, reason, and decoded fragment for whitespace cases
without also showing misleading missing-resource or missing-fragment follow-up
diagnostics. Remote guide references, data/file URL checks, path diagnostics,
missing guide resources, and missing guide target fragments remain separate
branches. This remains lane-local native PHP without invoking upstream Pandoc,
live fetching, shelling out to converters, EPUBCheck, full fixture corpus
parity, generated multi-rendition authoring beyond preserved OPF payloads, DRM
decryption, XML signature cryptographic validation, generic TeX-to-MathML
conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises OPF linked-metadata href fragment
diagnostics for Data Liberation EPUB intake. The reader now records
`invalid-package-link-href-fragment` when package metadata or collection
`link@href` values use malformed local fragments such as
`metadata/onix.xml#` or `metadata/onix.xml#bad%20record`. WordPress review can
surface the link id, parent package context, href, reason, and decoded
fragment for whitespace cases without also showing misleading missing-resource
or missing-fragment follow-up diagnostics. Remote links, package-document
reference checks, missing linked resources, and missing linked fragments remain
separate branches. This remains lane-local native PHP without invoking
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, generated multi-rendition authoring beyond preserved
OPF payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded recursive EPUB spine hyperlink
target diagnostics for Data Liberation EPUB intake. The reader now records
`missing-spine-hyperlink-target` when readable XHTML/SVG spine `a` or `area`
navigation, or local content documents reached from that navigation, point at
a manifest resource that is not represented by any OPF spine item. WordPress
review can surface direct and second-hop source XHTML paths, elements,
attributes, original hrefs, resolved target paths, manifest items, media
types, and link text or area alt text. Non-linear spine targets satisfy the
rule, while duplicate links, fragment-only links, external URLs, embedded
`img src` references, and external links inside recursively scanned resources
remain quiet. This remains lane-local native PHP without invoking upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, scripted link
discovery, full fixture corpus parity, generated multi-rendition authoring
beyond preserved OPF payloads, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB spine hyperlink target
diagnostics for Data Liberation EPUB intake. The reader now records
`missing-spine-hyperlink-target` when readable XHTML spine `a` or `area`
navigation points at a local manifest resource that is not represented by any
OPF spine item. WordPress review can surface source XHTML path, element,
attribute, original href, resolved target path, manifest item, media type, and
link text or area alt text. Non-linear spine targets satisfy the rule, while
duplicate links, fragment-only links, external URLs, and embedded `img src`
references remain quiet. This remains lane-local native PHP without invoking
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, scripted
link discovery, recursive traversal into newly diagnosed unspined resources,
full fixture corpus parity, generated multi-rendition authoring beyond
preserved OPF payloads, DRM decryption, XML signature cryptographic validation,
generic TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB XHTML `picture`/`source`
fallback diagnostics for Data Liberation EPUB intake. The reader now keeps
foreign `source srcset` resources quiet when the same `picture` has a Core
Media Type `img` fallback, and records
`missing-manifest-fallback-core-media-type` when a picture lacks that core
fallback. WordPress review can surface `element=source`, `attribute=srcset`,
descriptor/type context, source XHTML path, original source href, resolved
target path, manifest item, and fallback chain; foreign `img` fallbacks
without OPF fallback continue to report independently. This remains lane-local
native PHP without invoking upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, generated multi-rendition
authoring beyond preserved OPF payloads, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB XHTML image fallback
diagnostics for Data Liberation EPUB intake. The reader now records
`missing-manifest-fallback-core-media-type` with `missing-fallback` context
when a readable XHTML `img` references a foreign image resource whose OPF
manifest item has no fallback chain. WordPress review can surface the manifest
item, source XHTML path, element/attribute, original source href, resolved
target path, and fallback chain while existing malformed fallback chains and
valid Core Media Type chains continue to behave independently. This remains
lane-local native PHP without invoking upstream Pandoc, live fetching, shelling
out to converters, EPUBCheck, full fixture corpus parity, generated
multi-rendition authoring beyond preserved OPF payloads, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion,
`picture`/`source` media fallback parity, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB manifest fallback
core-media diagnostics for Data Liberation EPUB intake. The reader now records
`missing-manifest-fallback-core-media-type` when a foreign-resource OPF
manifest fallback chain terminates without reaching an EPUB Core Media Type
resource. WordPress review can surface the source manifest item, terminal
fallback item, fallback chain, and reason while fallback chains that resolve to
Core Media Type resources, such as MP3, remain unflagged. This remains
lane-local native PHP without invoking upstream Pandoc, live fetching, shelling
out to converters, EPUBCheck, full fixture corpus parity, generated
multi-rendition authoring beyond preserved OPF payloads, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB spine root ID diagnostics
for Data Liberation EPUB intake. The reader now records `invalid-spine-id`
when OPF `spine@id` is present but is not an XML NCName. WordPress review can
surface the malformed spine id while the raw `epubSpineId` remains preserved
for intake review, and existing `toc`, page-progression-direction, itemref
linear, and non-linear resource diagnostics continue independently. This
remains lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, generated
multi-rendition authoring beyond preserved OPF payloads, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB linear spine itemref
diagnostics for Data Liberation EPUB intake. The reader now records
`missing-linear-spine-itemref` when OPF spine itemrefs are all non-linear or
otherwise lack a valid `yes`/default linear itemref. Invalid itemref `linear`
values remain visible as `invalid-spine-itemref-linear` and do not satisfy the
EPUB3 spine requirement. WordPress review can surface the spine id plus itemref
and linear counts while non-linear resources remain preserved in
`epubSpineItemRefs` and `epubNonLinearResources` without becoming readable
body blocks. This remains lane-local native PHP without invoking upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, generated multi-rendition authoring beyond preserved OPF
payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` and `tests/EpubWriterTest.php` exercise bounded
EPUB collection content-model diagnostics and output ordering for Data
Liberation EPUB intake/export. The reader now records
`duplicate-collection-metadata` and `invalid-collection-child-order` when OPF
collection branches repeat direct metadata or order metadata/collection/link
children outside the EPUB3 content model. The writer now emits nested
collections before collection links so generated package output follows the
same model. WordPress review can surface the collection id, offending element,
previous member context, and expected order while valid collection metadata,
collection links, nested collections, and readable spine import continue. This
remains lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, generated
multi-rendition authoring beyond preserved OPF payloads, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB collection child element
diagnostics for Data Liberation EPUB intake. The reader now records
`invalid-collection-child-element` and
`invalid-collection-metadata-child-element` when OPF collection branches
contain arbitrary direct children or arbitrary collection metadata children.
WordPress review can surface the collection id plus offending
element/id/href/value context while valid collection metadata, collection
links, nested collections, existing namespace diagnostics, and readable spine
import continue. This remains lane-local native PHP without invoking upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, generated multi-rendition authoring beyond preserved OPF
payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB spine `toc` media-type
diagnostics for Data Liberation EPUB intake. The reader now records
`invalid-spine-toc-media-type` when OPF `spine@toc` points at an existing
non-NCX manifest item. WordPress review can surface the `toc` idref plus the
target href/media-type while valid NCX `toc` import and readable spine import
continue. This remains lane-local native PHP without invoking upstream Pandoc,
live fetching, shelling out to converters, EPUBCheck, full fixture corpus
parity, generated multi-rendition authoring beyond preserved OPF payloads, DRM
decryption, XML signature cryptographic validation, generic TeX-to-MathML
conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB manifest `fallback-style`
media-type diagnostics for Data Liberation EPUB intake. The reader now records
`invalid-manifest-fallback-style-media-type` when an OPF manifest
`fallback-style` target is not a CSS resource. WordPress review can surface the
source item id/href/media-type and the bad fallback-style target
id/href/media-type while readable spine import and valid CSS fallback-style
metadata preservation continue. This remains lane-local native PHP without
invoking upstream Pandoc, live fetching, shelling out to converters, EPUBCheck,
full fixture corpus parity, generated multi-rendition authoring beyond
preserved OPF payloads, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB collection
language/direction diagnostics for Data Liberation EPUB intake. The reader now
records `invalid-collection-dir`, `invalid-collection-xml-language`,
`invalid-collection-metadata-dir`, and
`invalid-collection-metadata-xml-language` when OPF collection and collection
metadata attributes carry malformed direction or XML language values.
WordPress review can surface the exact collection id, metadata
id/property/value, and bad dir/lang context while readable spine import and
valid collection metadata recovery continue. This remains lane-local native
PHP without invoking upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, generated multi-rendition
authoring beyond preserved OPF payloads, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB package link `xml:lang`
diagnostics for Data Liberation EPUB intake. The reader now records
`invalid-package-link-xml-language` when OPF package metadata links or
collection links carry malformed XML language tags, while preserving link
metadata and readable spine import. WordPress review can surface the exact
link id, href, parent branch, and bad language tag without duplicate generic
metadata language reports. This remains lane-local native PHP without invoking
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, generated multi-rendition authoring beyond preserved
OPF payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB package metadata
`xml:lang` diagnostics for Data Liberation EPUB intake. The reader now records
`invalid-metadata-xml-language` when OPF/DC package metadata children carry
malformed XML language tags, including bad language tags on `dc:title` and OPF
`meta` entries. WordPress review can surface the exact metadata element,
id/property/value context, and malformed language tag while title extraction,
modified metadata handling, and readable spine import still proceed. This
remains lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, generated
multi-rendition authoring beyond preserved OPF payloads, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB OCF duplicate container token
diagnostics for Data Liberation EPUB intake. The reader now records
`duplicate-container-rootfile-property`, `duplicate-container-link-rel`, and
`duplicate-container-link-property` when `META-INF/container.xml` rootfile/link
token lists repeat otherwise-valid values. Raw duplicate token lists remain
visible in `epubContainerRootfiles` and `epubContainerLinks`, so WordPress
review can show the original OCF container state while readable OPF import
still proceeds. This remains lane-local native PHP without invoking upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, generated multi-rendition authoring beyond preserved OPF
payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB OPF duplicate collection
role diagnostics for Data Liberation EPUB intake. The reader now records
`duplicate-collection-role` when an OPF collection `role` attribute repeats
the same token. Otherwise-valid repeated role tokens stay separate from
invalid role syntax and reserved `idpf.org` host diagnostics, so WordPress
review can show the exact package-collection issue while readable content and
valid collection metadata/links still import. This remains lane-local native
PHP without invoking upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, generated multi-rendition
authoring beyond preserved OPF payloads, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB OPF duplicate link token
diagnostics for Data Liberation EPUB intake. The reader now records
`duplicate-package-link-rel` for repeated package metadata and collection
`link@rel` tokens, and `duplicate-package-link-property` for repeated package
metadata and collection `link@properties` tokens. Raw duplicate property token
lists remain visible in `epubMetadataLinks` and `epubCollections`, while `rel`
remains visible as the source attribute string, so WordPress review can show
the original OPF link state while readable content still imports. This remains
lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, generated
multi-rendition authoring beyond preserved OPF payloads, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB OPF duplicate property token
diagnostics for Data Liberation EPUB intake. The reader now records
`duplicate-manifest-property` for repeated manifest `item@properties` tokens
and `duplicate-spine-itemref-property` for repeated spine
`itemref@properties` tokens. The raw duplicate token lists remain visible in
`epubManifestResources` and `epubSpineItemRefs`, so WordPress review can show
the original OPF state while the readable document body still imports and valid
rendition metadata continues to recover. This remains lane-local native PHP
without invoking upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, generated multi-rendition authoring
beyond preserved OPF payloads, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB OPF empty selected
unique-identifier diagnostics for Data Liberation EPUB intake. The reader now
records `empty-unique-identifier` when the OPF `unique-identifier` attribute
points at a whitespace-only `dc:identifier`, while leaving
`missing-unique-identifier`, `invalid-unique-identifier`, and
`invalid-unique-identifier-target` to their existing malformed/missing-target
branches. Readable content still imports when another non-empty identifier
satisfies required package metadata, so WordPress review can surface the bad
selected identifier without losing the document body. This remains lane-local
native PHP without invoking upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, generated multi-rendition
authoring beyond preserved OPF payloads, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB OPF package-wide duplicate
XML ID diagnostics for Data Liberation EPUB intake. The reader now records
`duplicate-package-id` when a valid XML `id` value repeats across OPF package
scopes, such as a metadata title ID colliding with a manifest item ID. Local
duplicate diagnostics for metadata children, manifest items, spine itemrefs,
collection IDs, collection links, and collection metadata stay responsible for
same-scope duplicates, so WordPress review can distinguish package-wide
ambiguity from local structural mistakes. Readable content still imports. This
remains lane-local native PHP without invoking upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, generated
multi-rendition authoring beyond preserved OPF payloads, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB OPF package root element
diagnostics for Data Liberation EPUB intake. The reader now records
`invalid-package-root-element` when the package document root has the wrong
local name, including the observed qualified name, observed local name, and
expected `package` root for reviewer/debugger surfacing. This replaces the
previous fatal root-name rejection for recoverable packages, so readable
content still imports when the OPF child branches, manifest, spine, and
resource paths are usable. This remains lane-local native PHP without invoking
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, generated multi-rendition authoring beyond preserved
OPF payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB OPF package root namespace
diagnostics for Data Liberation EPUB intake. The reader now records
`invalid-package-root-namespace` when the package document root is outside the
OPF namespace, including the observed qualified name, observed namespace, and
expected OPF namespace for reviewer/debugger surfacing. Readable package
content still imports when the package children, manifest, spine, and resource
paths are usable. This remains lane-local native PHP without invoking upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, generated multi-rendition authoring beyond preserved OPF
payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB OCF invalid-rootfile
selection diagnostics for Data Liberation EPUB intake. The full EPUB reader
now uses the same container rootfile diagnostic helper for successful import
metadata and early `EpubContainerException` diagnostics. Invalid-only
`rootfiles` branches report `invalid-container-rootfiles` with underlying
rootfile namespace and rootfiles-branch child diagnostics; direct `rootfile`
entries that cannot produce a usable package path report
`missing-container-opf-rootfile` with full-path diagnostics explaining the
selection failure. This remains a rejection path because there is no package
document to load, and it remains lane-local native PHP without invoking
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, generated multi-rendition authoring beyond preserved
OPF payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB OCF required-rootfiles
diagnostics for Data Liberation EPUB intake. The full EPUB reader now throws
`EpubContainerException`, while preserving `InvalidArgumentException`
compatibility, when `META-INF/container.xml` cannot identify an OPF package
document. Missing direct OCF `rootfiles` branches report
`missing-container-rootfiles`; empty primary OCF `rootfiles` branches report
`empty-container-rootfiles`. This remains a rejection path because there is no
package document to load, and it remains lane-local native PHP without
invoking upstream Pandoc, live fetching, shelling out to converters, EPUBCheck,
full fixture corpus parity, generated multi-rendition authoring beyond
preserved OPF payloads, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB OCF branch-child diagnostics
for Data Liberation EPUB intake. The reader now reports
`invalid-container-rootfiles-child-namespace` and
`invalid-container-rootfiles-child-element` for unexpected direct children
inside the primary OCF `rootfiles` branch, plus
`invalid-container-links-child-namespace` and
`invalid-container-links-child-element` for unexpected direct children inside
the primary OCF `links` branch. Rootfile/link namespace lookalikes remain
handled by the existing rootfile/link namespace diagnostics, so the same bad
entry is not double-counted. This remains lane-local native PHP and does not
invoke upstream Pandoc, live fetching, shelling out to converters, EPUBCheck,
full fixture corpus parity, generated multi-rendition authoring beyond
preserved OPF payloads, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB OCF container child
diagnostics for Data Liberation EPUB intake. The reader now reports
`invalid-container-child-namespace` for direct `META-INF/container.xml`
children outside the OCF namespace and `invalid-container-child-element` for
same-namespace direct children whose local names are not `rootfiles` or
`links`. The diagnostics are scoped to valid OCF container roots, so malformed
root fallback behavior remains available for readable package recovery. This
remains lane-local native PHP and does not invoke upstream Pandoc, live
fetching, shelling out to converters, EPUBCheck, full fixture corpus parity,
generated multi-rendition authoring beyond preserved OPF payloads, DRM
decryption, XML signature cryptographic validation, generic TeX-to-MathML
conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB OCF duplicate container
branch diagnostics for Data Liberation EPUB intake. The reader now reports
`duplicate-container-rootfiles` and `duplicate-container-links` when
`META-INF/container.xml` declares repeated top-level `rootfiles` or `links`
branches. Duplicate-branch `rootfile` and `link` records stay out of
normalized `epubContainerRootfiles` and `epubContainerLinks` metadata, so they
cannot become selected OPF packages or trigger duplicate missing-resource
checks. This remains lane-local native PHP and does not invoke upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, generated multi-rendition authoring beyond preserved OPF
payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB OCF container branch-scope
diagnostics for Data Liberation EPUB intake. The reader now accepts valid OCF
`rootfile` and `link` records only from direct top-level
`container/rootfiles` and `container/links` branches. Valid-namespace entries
in nested `rootfiles` or `links` branches now report parent diagnostics with
parent/grandparent context and stay out of normalized `epubContainerRootfiles`
and `epubContainerLinks` metadata. This remains lane-local native PHP and does
not invoke upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, generated multi-rendition authoring
beyond preserved OPF payloads, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB OCF container link ID
diagnostics for Data Liberation EPUB intake. The reader now reports
`invalid-container-link-id` when `META-INF/container.xml` link `id`
attributes are not XML NCNames and `duplicate-container-link-id` when valid
container link IDs repeat within the valid OCF container link list. Malformed
and duplicate-ID links remain preserved in `epubContainerLinks` for review.
This remains lane-local native PHP and does not invoke upstream Pandoc, live
fetching, shelling out to converters, EPUBCheck, full fixture corpus parity,
generated multi-rendition authoring beyond preserved OPF payloads, DRM
decryption, XML signature cryptographic validation, generic TeX-to-MathML
conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB OCF container link namespace
diagnostics for Data Liberation EPUB intake. The reader now reports
`invalid-container-link-namespace` for foreign-namespace
`META-INF/container.xml` link lookalikes and `invalid-container-link-parent`
for link elements outside the OCF `links` branch. Valid OCF container links
remain preserved in `epubContainerLinks`, while foreign or wrong-parent
lookalikes stay out of normalized container link metadata and cannot trigger
missing-resource checks. This remains lane-local native PHP and does not
invoke upstream Pandoc, live fetching, shelling out to converters, EPUBCheck,
full fixture corpus parity, generated multi-rendition authoring beyond
preserved OPF payloads, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB OCF rootfile namespace
diagnostics for Data Liberation EPUB intake. The reader now reports
`invalid-container-rootfile-namespace` for foreign-namespace
`META-INF/container.xml` rootfile lookalikes and
`invalid-container-rootfile-parent` for rootfile elements outside the
`rootfiles` branch. Valid OCF rootfiles remain selectable, while foreign or
wrong-parent lookalikes stay out of `epubContainerRootfiles` and cannot become
selected OPF packages. This remains lane-local native PHP and does not invoke
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, generated multi-rendition authoring beyond preserved
OPF payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB OCF rootfile backslash
diagnostics for Data Liberation EPUB intake. The reader now reports
`invalid-container-rootfile-full-path` with reason `backslash` when
`META-INF/container.xml` rootfile `full-path` values contain literal
backslashes. Those malformed Windows-style rootfile aliases stay visible in
`epubContainerRootfiles` for review, but they are ignored for OPF rootfile
selection and no longer cascade into false
`missing-container-rootfile-resource` diagnostics. This remains lane-local
native PHP and does not invoke upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, generated multi-rendition
authoring beyond preserved OPF payloads, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB OCF rootfile encoded
dot-segment diagnostics for Data Liberation EPUB intake. The reader now
reports `invalid-container-rootfile-full-path` with reason
`encoded-dot-segment` when `META-INF/container.xml` rootfile `full-path`
segments percent-decode to `.` or `..`. Those malformed rootfile entries stay
visible in `epubContainerRootfiles` for review, but they are ignored for OPF
rootfile selection and no longer cascade into false
`missing-container-rootfile-resource` diagnostics. This remains lane-local
native PHP and does not invoke upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, generated multi-rendition
authoring beyond preserved OPF payloads, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB metadata-refines encoded
dot-segment diagnostics for Data Liberation EPUB intake. The reader now
reports `invalid-metadata-refines` with reason `encoded-dot-segment` when OPF
metadata `refines` path segments percent-decode to `.` or `..`, including
both `meta` and `link` refinement elements. These encoded traversal aliases no
longer cascade into false missing-resource, missing-fragment, or
package-document target diagnostics. This remains lane-local native PHP and
does not invoke upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, generated multi-rendition authoring
beyond preserved OPF payloads, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB metadata-refines fragment
diagnostics for Data Liberation EPUB intake. The reader now reports
`missing-metadata-refines-fragment` for OPF metadata `refines` values such as
`chapter.xhtml#missing-section` or `metadata/onix.xml#missing-record` when the
local XHTML/XML resource exists but the fragment ID does not. Valid resource
fragments remain unflagged, missing local files stay in
`missing-metadata-refines-resource`, and package-document ID checks stay in
`missing-metadata-refines-target`. This remains lane-local native PHP and does
not invoke upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, generated multi-rendition authoring
beyond preserved OPF payloads, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB package-link fragment
diagnostics for Data Liberation EPUB intake. The reader now reports
`missing-package-link-fragment` for OPF package metadata and collection
`link@href` values such as `metadata/onix.xml#missing-record` when the local
XML resource exists but the fragment ID does not. Valid local fragments remain
unflagged, remote links remain unflagged, missing local files stay in
`missing-package-link-resource`, and package-document references stay in
`invalid-package-link-package-document-reference`. This remains lane-local
native PHP and does not invoke upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, generated multi-rendition
authoring beyond preserved OPF payloads, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB NCX content `src` path
diagnostics for Data Liberation EPUB intake. The reader now reports
`invalid-ncx-content-src-path` for NCX `navPoint`, `pageTarget`, and
`navTarget` `content src` values that use query-only or fragment-only local
paths, leading-slash absolute paths, protocol-relative URLs, backslashes, or
encoded dot segments. Invalid NCX content source path shapes no longer
cascade into false spine-target diagnostics, and NCX TOC, page-list, and
nav-list metadata preserves the raw invalid references instead of normalizing
them to misleading package paths. Remote absolute NCX targets remain
unflagged. This remains lane-local native PHP and does not invoke upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, generated multi-rendition authoring beyond preserved OPF
payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 `nav.xhtml` link href path
diagnostics for Data Liberation EPUB intake. The reader now reports
`invalid-nav-link-href-path` for navigation anchor `href` values that use
query-only or fragment-only local paths, leading-slash absolute paths,
protocol-relative URLs, backslashes, or encoded dot segments. Invalid
navigation link path shapes no longer cascade into false missing-resource,
missing-fragment, pagebreak, duplicate-target, or spine-order diagnostics.
Remote absolute navigation links remain unflagged. This remains lane-local
native PHP and does not invoke upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, generated multi-rendition
authoring beyond preserved OPF payloads, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 SMIL media-overlay
reference path diagnostics for Data Liberation EPUB intake. The reader now
reports `invalid-media-overlay-seq-textref-path`,
`invalid-media-overlay-text-src-path`, and
`invalid-media-overlay-audio-src-path` for SMIL `seq epub:textref` and
`text`/`audio` `src` values that use query-only local paths, leading-slash
absolute paths, protocol-relative URLs, backslashes, or encoded dot segments.
Invalid media-overlay reference path shapes no longer cascade into false
missing-resource, missing-fragment, or ordering diagnostics, and overlay
metadata preserves the raw invalid references instead of normalizing them to
misleading archive paths. This remains lane-local native PHP and does not
invoke upstream Pandoc, live fetching, shelling out to converters, EPUBCheck,
full fixture corpus parity, generated multi-rendition authoring beyond
preserved OPF payloads, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 OCF sidecar reference URI
path diagnostics for Data Liberation EPUB intake. The reader now reports
`invalid-ocf-encryption-cipher-reference-uri-path` and
`invalid-ocf-signature-reference-uri-path` for encryption `CipherReference`
and XML Signature `Reference` URI values that use query-only local paths,
leading-slash absolute paths, protocol-relative URLs, backslashes, or encoded
dot segments. Invalid sidecar URI path shapes no longer cascade into false
missing-resource diagnostics or invalid encrypted-resource metadata paths.
This remains lane-local native PHP and does not invoke upstream Pandoc, live
fetching, shelling out to converters, EPUBCheck, full fixture corpus parity,
generated multi-rendition authoring beyond preserved OPF payloads, DRM
decryption, XML signature cryptographic validation, generic TeX-to-MathML
conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 OCF container link href
path diagnostics for Data Liberation EPUB intake. The reader now reports
`invalid-container-link-href-path` for `META-INF/container.xml` `link@href`
values that use query-only local paths, leading-slash absolute paths,
protocol-relative URLs, backslashes, or encoded dot segments. Container-link
metadata remains exposed with normalized `epubContainerLinks` hrefs, while
invalid path shapes avoid false `missing-container-link-resource`
diagnostics. This remains lane-local native PHP and does not invoke upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, generated multi-rendition authoring beyond preserved OPF
payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 encoded dot-segment href
path diagnostics for Data Liberation EPUB intake. The reader now reports
`encoded-dot-segment` in `invalid-manifest-href-path`,
`invalid-package-link-href-path`, and `invalid-guide-reference-href-path`
when local OPF manifest, package link, or guide reference `href` path
segments percent-decode exactly to `.` or `..`. These invalid encoded
traversal aliases no longer cascade into false missing-resource,
reserved-resource, or package-document-reference diagnostics, while ordinary
percent-encoded filenames still resolve normally. This remains lane-local
native PHP and does not invoke upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, generated multi-rendition
authoring beyond preserved OPF payloads, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 package link URL/path
diagnostics for Data Liberation EPUB intake. The reader now reports expanded
`invalid-package-link-href-path` reasons when OPF package metadata and
collection `link@href` values use absolute paths, protocol-relative URLs,
backslashes, or query-only local paths. Invalid path shapes no longer cascade
into false `missing-package-link-resource` ZIP diagnostics, while existing
unsafe `data:`/`file:`, package-document reference, and remote-link behavior
remain separate. This remains lane-local native PHP and does not invoke
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, generated multi-rendition authoring beyond preserved
OPF payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 guide reference URL/path
diagnostics for Data Liberation EPUB intake. The reader now reports
`invalid-guide-reference-data-url`, `invalid-guide-reference-file-url`, and
expanded `invalid-guide-reference-href-path` reasons when OPF guide
`reference@href` values use unsafe schemes, absolute paths, protocol-relative
URLs, backslashes, or empty local paths. Malformed guide references remain
visible in `epubGuideReferences`, while invalid URL/path values avoid false
`missing-guide-reference-resource` ZIP diagnostics. This remains lane-local
native PHP and does not invoke upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, generated multi-rendition
authoring beyond preserved OPF payloads, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 guide reference href
empty-path diagnostics for Data Liberation EPUB intake. The reader now reports
`invalid-guide-reference-href-path` with reason `empty-path` when OPF guide
`reference@href` values contain only a query or fragment, such as `?print=1`
or `#cover`, while remote guide references remain exempt from local ZIP
resource diagnostics. This remains lane-local native PHP and does not invoke
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, generated multi-rendition authoring beyond preserved
OPF payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 package link href
empty-path diagnostics for Data Liberation EPUB intake. The reader now reports
`invalid-package-link-href-path` with reason `empty-path` when OPF package
metadata or collection `link@href` values contain only a query, such as
`?rev=1` or `?edition=2`, while fragment-only package links remain covered by
`invalid-package-link-package-document-reference` and query-only package links
avoid false missing-resource diagnostics. This remains lane-local native PHP
and does not invoke upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, generated multi-rendition
authoring beyond preserved OPF payloads, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 manifest href empty-path
diagnostics for Data Liberation EPUB intake. The reader now reports
`invalid-manifest-href-path` with reason `empty-path` when OPF manifest item
`href` values contain only a query or fragment, such as `?rev=1` or
`#package-fragment`, while fragment-only values continue to report
`invalid-manifest-href-fragment` and no longer collapse into duplicate
resource aliases or missing ZIP resource checks. This remains lane-local
native PHP and does not invoke upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, generated multi-rendition
authoring beyond preserved OPF payloads, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 cover/image manifest URL
normalization for Data Liberation EPUB intake. The reader now resolves
percent-encoded OPF manifest cover-image and image resource `href` values
through the package URL path resolver, so `epubCoverImage`,
`epubImageResources`, and guide metadata expose decoded ZIP paths such as
`EPUB/images/cover.svg` instead of raw `images/%63over.svg` aliases. This
remains lane-local native PHP and does not invoke upstream Pandoc, live
fetching, shelling out to converters, EPUBCheck, full fixture corpus parity,
generated multi-rendition authoring beyond preserved OPF payloads, DRM
decryption, XML signature cryptographic validation, generic TeX-to-MathML
conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 metadata `refines`
resource target diagnostics for Data Liberation EPUB intake. The reader now
accepts path-relative resource `refines` values such as
`chapter.xhtml#section`, `metadata/onix.xml#record`, and
`package.opf#title-main`, reports missing local resources as
`missing-metadata-refines-resource`, and keeps malformed or absolute
`refines` values in `invalid-metadata-refines`. This remains lane-local
native PHP and does not invoke upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, generated multi-rendition
authoring beyond preserved OPF payloads, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 package link
package-document reference diagnostics for Data Liberation EPUB intake. The
reader now emits `invalid-package-link-package-document-reference` when OPF
metadata or collection `link@href` values point at package document elements
such as `#chapter` or `package.opf#chapter`, preserves valid linked-record
fragments such as `metadata/onix.xml#record`, and avoids false
`missing-package-link-resource` diagnostics for package-document element
references. This remains lane-local native PHP and does not invoke upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, generated multi-rendition authoring beyond preserved OPF
payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 manifest href fragment
diagnostics for Data Liberation EPUB intake. The reader now emits
`invalid-manifest-href-fragment` when an OPF manifest item `href` contains a
fragment identifier, records the raw `href` plus fragment/path context, and
still imports readable content through the resource path before the fragment
without false `missing-manifest-resource` diagnostics. This remains
lane-local native PHP and does not invoke upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, generated
multi-rendition authoring beyond preserved OPF payloads, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 parsed manifest URL
diagnostics for Data Liberation EPUB intake. The reader now resolves
package-relative manifest, spine, navigation, and content links after URL path
percent-decoding, emits `duplicate-manifest-href` for parsed aliases such as
`text/chapter.xhtml` versus `text/%63hapter.xhtml`, and imports readable spine
content plus TOC metadata through decoded ZIP paths without false
missing-resource diagnostics. This remains lane-local native PHP and does not
invoke upstream Pandoc, live fetching, shelling out to converters, EPUBCheck,
full fixture corpus parity, generated multi-rendition authoring beyond
preserved OPF payloads, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 restricted manifest
resource diagnostics for Data Liberation EPUB intake. The reader now emits
`manifest-package-document-resource` when an OPF manifest entry references the
selected package document, and `manifest-reserved-ocf-resource` when manifest
entries reference reserved OCF files such as `mimetype` or
`META-INF/container.xml`. The entries remain inspectable in manifest metadata,
and readable spine import still proceeds. This remains lane-local native PHP
and does not invoke upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, generated multi-rendition authoring
beyond preserved OPF payloads, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 package child order
diagnostics for Data Liberation EPUB intake. The reader now emits
`invalid-package-child-order` when first valid OPF package children violate the
EPUB content-model order: `metadata`, `manifest`, `spine`, `guide`,
`bindings`, then `collection`. Readable spine content and valid
guide/bindings/collection metadata still import for review. This remains
lane-local native PHP and does not invoke upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, generated
multi-rendition authoring beyond preserved OPF payloads, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 duplicate optional package
child diagnostics for Data Liberation EPUB intake. The reader now emits
`duplicate-package-guide` and `duplicate-package-bindings` when multiple
top-level OPF `guide` or `bindings` branches appear, while keeping import and
diagnostics scoped to the first valid OPF guide/bindings branch. Alternate
guide references and bindings no longer leak missing-resource, missing-type,
media-type, or handler diagnostics. This remains lane-local native PHP and
does not invoke upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, generated multi-rendition authoring
beyond preserved OPF payloads, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 duplicate package branch
scoping for Data Liberation EPUB intake. The reader now keeps package-level
diagnostics and ID lookups on the primary OPF metadata/manifest/spine branches,
so duplicate metadata/spine branches no longer satisfy `unique-identifier` or
metadata `refines` targets and no longer leak package-link, missing-resource,
or rendition diagnostics. This remains lane-local native PHP and does not
invoke upstream Pandoc, live fetching, shelling out to converters, EPUBCheck,
full fixture corpus parity, generated multi-rendition authoring beyond
preserved OPF payloads, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 unexpected package child
diagnostics for Data Liberation EPUB intake. The reader now emits
`invalid-package-child-element` errors when a direct OPF/no-namespace package
root child is outside `metadata`, `manifest`, `spine`, `guide`, `bindings`, or
`collection`, while preserving readable spine import so valid content still
surfaces with package-structure diagnostics. This remains lane-local native PHP
and does not invoke upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, generated multi-rendition authoring
beyond preserved OPF payloads, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 duplicate package
required-child diagnostics for Data Liberation EPUB intake. The reader now
emits `duplicate-package-metadata`, `duplicate-package-manifest`, and
`duplicate-package-spine` errors when multiple OPF metadata/manifest/spine
children appear, and keeps duplicate metadata, manifest resources, and spine
content out of the imported document while preserving first valid OPF child
import precedence. This remains lane-local native PHP and does not invoke
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, generated multi-rendition authoring beyond preserved
OPF payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 package manifest/spine
namespace diagnostics for Data Liberation EPUB intake. The reader now emits
`invalid-package-manifest-namespace` and `invalid-package-spine-namespace`
errors when foreign-namespace top-level manifest/spine lookalikes appear, and
excludes those lookalikes from manifest resource import and readable spine
content import while preserving valid OPF manifest and spine records. This
remains lane-local native PHP and does not invoke upstream Pandoc, live
fetching, shelling out to converters, EPUBCheck, full fixture corpus parity,
generated multi-rendition authoring beyond preserved OPF payloads, DRM
decryption, XML signature cryptographic validation, generic TeX-to-MathML
conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 guide/bindings namespace and
content-model diagnostics for Data Liberation EPUB intake. The reader now emits
`invalid-guide-namespace`, `invalid-guide-reference-namespace`,
`invalid-guide-child-element`, `invalid-bindings-namespace`,
`invalid-binding-media-type-namespace`, and `invalid-bindings-child-element`
errors when foreign-namespace guide/reference/bindings/mediaType lookalikes or
unexpected guide/bindings children appear, and excludes those lookalikes from
`epubGuideReferences` and `epubBindings` import while preserving valid OPF
guide and binding records. This remains lane-local native PHP and does not
invoke upstream Pandoc, live fetching, shelling out to converters, EPUBCheck,
full fixture corpus parity, generated multi-rendition authoring beyond
preserved OPF payloads, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 collection metadata child
namespace diagnostics for Data Liberation EPUB intake. The reader now emits
`invalid-collection-metadata-namespace`, `invalid-collection-meta-namespace`,
and `invalid-collection-link-namespace` errors when foreign-namespace
`metadata`/`meta`/`link` lookalikes appear under OPF collections, and excludes
those lookalikes from `epubCollections` metadata/link import and collection
metadata ID checks while preserving valid OPF collection records. This remains
lane-local native PHP and does not invoke upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, generated
multi-rendition authoring beyond preserved OPF payloads, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 package metadata child
namespace diagnostics for Data Liberation EPUB intake. The reader now emits
`invalid-package-meta-namespace` and `invalid-package-link-namespace` errors
when foreign-namespace `meta`/`link` lookalikes appear under OPF metadata, and
excludes those lookalikes from `epubMetadataProperties` and
`epubMetadataLinks` import while preserving valid OPF metadata records. This
remains lane-local native PHP and does not invoke upstream Pandoc, live
fetching, shelling out to converters, EPUBCheck, full fixture corpus parity,
generated multi-rendition authoring beyond preserved OPF payloads, DRM
decryption, XML signature cryptographic validation, generic TeX-to-MathML
conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 package collection
namespace diagnostics for Data Liberation EPUB intake. The reader now emits
`invalid-package-collection-namespace` errors when foreign-namespace
`collection` lookalikes appear under OPF package, and excludes those
lookalikes from `epubCollections` import while preserving valid OPF collection
metadata and link import. This remains lane-local native PHP and does not
invoke upstream Pandoc, live fetching, shelling out to converters, EPUBCheck,
full fixture corpus parity, generated multi-rendition authoring beyond
preserved OPF payloads, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 package metadata namespace
diagnostics for Data Liberation EPUB intake. The reader now emits
`invalid-package-metadata-namespace` errors when foreign-namespace `metadata`
lookalikes appear under OPF package, and excludes those lookalikes from
imported title, identifier, language, and package metadata while preserving
valid OPF metadata records. This remains lane-local native PHP and does not
invoke upstream Pandoc, live fetching, shelling out to converters, EPUBCheck,
full fixture corpus parity, generated multi-rendition authoring beyond
preserved OPF payloads, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 package child namespace
diagnostics for Data Liberation EPUB intake. The reader now emits
`invalid-manifest-item-namespace` and `invalid-spine-itemref-namespace` errors
when foreign-namespace `item`/`itemref` lookalikes appear under OPF manifest or
spine, and excludes those lookalikes from manifest resource and spine import
while preserving valid OPF records. This remains lane-local native PHP and does
not invoke upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, generated multi-rendition authoring
beyond preserved OPF payloads, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 unexpected manifest child
element diagnostics for Data Liberation EPUB intake. The reader now emits
`invalid-manifest-child-element` errors when OPF manifest contains direct
non-`item` child elements, while preserving readable spine import, existing
manifest item diagnostics, and valid manifest resource metadata. This remains
lane-local native PHP and does not invoke upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, generated
multi-rendition authoring beyond preserved OPF payloads, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 unexpected spine child
element diagnostics for Data Liberation EPUB intake. The reader now emits
`invalid-spine-child-element` errors when OPF spine contains direct
non-`itemref` child elements, while preserving readable spine import, existing
itemref diagnostics, and valid spine metadata. This remains lane-local native
PHP and does not invoke upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, generated multi-rendition
authoring beyond preserved OPF payloads, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 duplicate spine itemref
IDREF diagnostics for Data Liberation EPUB intake. The reader now emits
`duplicate-spine-itemref-idref` errors when OPF spine itemrefs reference the
same manifest item more than once, while preserving readable spine import,
non-linear itemref metadata, first/repeated itemref context, and existing
missing/invalid idref diagnostics. This remains lane-local native PHP and does
not invoke upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, generated multi-rendition authoring
beyond preserved OPF payloads, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 spine foreign-content
fallback diagnostics for Data Liberation EPUB intake. The reader now emits
`missing-spine-fallback-content-document` errors when OPF spine itemrefs point
at foreign content document manifest items whose fallback chains do not reach
an EPUB content document, while preserving valid XHTML/SVG content documents,
valid foreign spine fallback chains, existing readable XHTML fallback import,
raw manifest metadata, and readable spine import from other items. This
remains lane-local native PHP and does not invoke upstream Pandoc, live
fetching, shelling out to converters, EPUBCheck, full fixture corpus parity,
generated multi-rendition authoring beyond preserved OPF payloads, DRM
decryption, XML signature cryptographic validation, generic TeX-to-MathML
conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 OPF property-prefix
diagnostics for Data Liberation EPUB intake. The reader now emits
`undeclared-package-link-rel-prefix`,
`undeclared-package-link-property-prefix`,
`undeclared-manifest-property-prefix`,
`undeclared-spine-itemref-property-prefix`,
`undeclared-collection-metadata-property-prefix`, and
`undeclared-collection-metadata-scheme-prefix` errors when OPF package link
rel/properties, manifest item properties, spine itemref properties, or
collection metadata property/scheme values use prefixes that are neither
reserved nor declared in the package `prefix` attribute, while preserving
unprefixed default-vocabulary values, reserved prefixes, declared custom
prefixes, raw metadata, and readable spine import. This remains lane-local
native PHP and does not invoke upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, generated multi-rendition
authoring beyond preserved OPF payloads, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 OPF package metadata
undeclared-prefix diagnostics for Data Liberation EPUB intake. The reader now
emits `undeclared-package-meta-property-prefix` and
`undeclared-package-meta-scheme-prefix` errors when package metadata property
data type values use prefixes that are neither reserved nor declared in the
package `prefix` attribute, while preserving unprefixed default-vocabulary
values, reserved prefixes, declared custom prefixes, raw metadata, and
readable spine import. This remains lane-local native PHP and does not invoke
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, generated multi-rendition authoring beyond preserved
OPF payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 OPF package
reserved-prefix override diagnostics for Data Liberation EPUB intake. The
reader now emits `overridden-package-prefix` warnings when package prefix
declarations remap reserved EPUB prefixes to unrelated IRIs, while preserving
accepted reserved mappings, raw `epubPackagePrefix` metadata, and readable
spine import. This remains lane-local native PHP and does not invoke upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, generated multi-rendition authoring beyond preserved OPF
payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB OPF package version
diagnostics for Data Liberation EPUB intake. The reader now emits
`unsupported-package-version` for numeric package version values outside
`2.0` and `3.0`, while preserving raw `epubPackageVersion` metadata and
readable spine import. This remains lane-local native PHP and does not invoke
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, generated multi-rendition authoring beyond preserved
OPF payloads, DRM decryption, XML signature cryptographic validation,
generic TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 OPF package prefix
diagnostics for Data Liberation EPUB intake. The reader now emits
`duplicate-package-prefix` for repeated package prefix declarations and
`reserved-package-prefix` for the EPUB-reserved `_` prefix, while preserving
the raw `epubPackagePrefix` metadata and readable spine import. This remains
lane-local native PHP and does not invoke upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, generated
multi-rendition authoring beyond preserved OPF payloads, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 OPF manifest href path
diagnostics for Data Liberation EPUB intake. The reader now emits
`invalid-manifest-href-path` for manifest `item@href` values that are neither
absolute URLs nor path-relative scheme-less URLs, including leading-slash
paths, protocol-relative URLs, and backslash paths. This remains lane-local
native PHP and does not invoke upstream Pandoc, live fetching, shelling out
to converters, EPUBCheck, full fixture corpus parity, generated
multi-rendition authoring beyond preserved OPF payloads, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 OCF rootfile resource
diagnostics for Data Liberation EPUB intake. The reader now emits
`missing-container-rootfile-resource` for OPF-typed
`META-INF/container.xml` rootfiles whose package-relative `full-path` does
not resolve to a ZIP entry, while keeping malformed full-path and non-OPF
media-type diagnostics separate. This remains lane-local native PHP and does
not invoke upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, generated multi-rendition authoring
beyond preserved OPF payloads, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 OCF rootfile full-path
diagnostics for Data Liberation EPUB intake. The reader now emits
`invalid-container-rootfile-full-path` for unsafe or non-archive
`META-INF/container.xml` rootfile paths, including `data:`, `file:`, remote
URLs, leading-slash absolute paths, query/fragment suffixes, parent-directory
traversal, and paths that normalize to an empty archive entry. This remains
lane-local native PHP and does not invoke upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, generated
multi-rendition authoring beyond preserved OPF payloads, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 OCF rootfile ID/property
diagnostics for Data Liberation EPUB intake. The reader now emits
`invalid-container-rootfile-id`, `duplicate-container-rootfile-id`, and
`invalid-container-rootfile-property` for malformed `META-INF/container.xml`
rootfile metadata while preserving selected OPF import behavior and retaining
the malformed rootfile metadata for review. This remains lane-local native
PHP and does not invoke upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, generated multi-rendition
authoring beyond preserved OPF payloads, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 OCF container-link
attribute diagnostics for Data Liberation EPUB intake. The reader now emits
`invalid-container-link-rel`, `invalid-container-link-hreflang`,
`invalid-container-link-dir`, `invalid-container-link-refines`, and
`invalid-container-link-property` for malformed `META-INF/container.xml`
container link attributes while preserving the malformed link metadata for
review. This remains lane-local native PHP and does not invoke upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, generated multi-rendition authoring beyond preserved OPF
payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 OCF container-link unsafe
URL diagnostics for Data Liberation EPUB intake. The reader now emits
`invalid-container-link-data-url` and `invalid-container-link-file-url` for
`META-INF/container.xml` `link@href` values that use `data:` or `file:` URLs,
while keeping missing local container-link resource diagnostics separate and
continuing to skip remote container links in ZIP-resource checks. This
remains lane-local native PHP and does not invoke upstream Pandoc, live
fetching, shelling out to converters, EPUBCheck, full fixture corpus parity,
generated multi-rendition authoring beyond preserved OPF payloads, DRM
decryption, XML signature cryptographic validation, generic TeX-to-MathML
conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 OPF package-link unsafe URL
diagnostics for Data Liberation EPUB intake. The reader now emits
`invalid-package-link-data-url` and `invalid-package-link-file-url` for
package metadata and collection `link@href` values that use `data:` or
`file:` URLs, while keeping missing local package-link resource diagnostics
separate and continuing to skip remote package links in ZIP-resource checks.
This remains lane-local native PHP and does not invoke upstream Pandoc, live
fetching, shelling out to converters, EPUBCheck, full fixture corpus parity,
generated multi-rendition authoring beyond preserved OPF payloads, DRM
decryption, XML signature cryptographic validation, generic TeX-to-MathML
conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 OPF package-link relation
diagnostics for Data Liberation EPUB intake. The reader now emits
`invalid-package-link-rel` for malformed `link@rel` tokens on package metadata
and collection links while keeping missing relation diagnostics in
`missing-package-link-rel` and malformed `link@properties` tokens in
`invalid-package-link-property`. This remains lane-local native PHP and does
not invoke upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, generated multi-rendition authoring
beyond preserved OPF payloads, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 OPF spine itemref
properties diagnostics for Data Liberation EPUB intake. The reader now emits
`invalid-spine-itemref-property` for malformed `itemref@properties` tokens
while keeping unsupported rendition property values in the separate
`invalid-rendition-spine-property` bucket. This remains lane-local native PHP
and does not invoke upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, generated multi-rendition
authoring beyond preserved OPF payloads, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 OPF manifest item
properties diagnostics for Data Liberation EPUB intake. The reader now emits
`invalid-manifest-property` for malformed `item@properties` tokens while
keeping manifest media type, fallback/fallback-style/media-overlay IDREF,
required-property, remote-resource, and local resource diagnostics separate.
Valid manifest property tokens continue to drive existing
nav/mathml/scripted/svg/switch/remote-resources checks. This remains
lane-local native PHP and does not invoke upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, generated
multi-rendition authoring beyond preserved OPF payloads, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 OPF package-link
properties diagnostics for Data Liberation EPUB intake. The reader now emits
`invalid-package-link-property` for malformed `link@properties` tokens on
package metadata and collection links while keeping required `href`/`rel`,
optional `media-type`/`hreflang`/`dir`, and missing local-resource
diagnostics separate. Valid link property tokens remain preserved with the
link metadata records. This remains lane-local native PHP and does not invoke
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, generated multi-rendition authoring beyond preserved
OPF payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 OPF metadata scheme-value
diagnostics for Data Liberation EPUB intake. The reader now emits
`invalid-package-meta-scheme` and `invalid-collection-metadata-scheme` for
malformed metadata `scheme` values while keeping metadata `property`,
missing-property, and legacy OPF2-style diagnostics separate. This remains
lane-local native PHP and does not invoke upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, generated
multi-rendition authoring beyond preserved OPF payloads, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 OPF metadata property-value
diagnostics for Data Liberation EPUB intake. The reader now emits
`invalid-package-meta-property` and `invalid-collection-metadata-property` for
malformed non-empty metadata property values while keeping missing-property
and legacy OPF2-style metadata diagnostics separate. This remains lane-local
native PHP and does not invoke upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, generated multi-rendition
authoring beyond preserved OPF payloads, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 OPF collection-link ID
diagnostics for Data Liberation EPUB intake. The reader now emits
`invalid-collection-link-id` and `duplicate-collection-link-id` for malformed
or reused collection member link IDs, while `invalid-collection-link-refines`
now points at the link element and carries containing collection context. This
remains lane-local native PHP and does not invoke upstream Pandoc, live
fetching, shelling out to converters, EPUBCheck, full fixture corpus parity,
generated multi-rendition authoring beyond preserved OPF payloads, DRM
decryption, XML signature cryptographic validation, generic TeX-to-MathML
conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 OPF collection ID
diagnostics for Data Liberation EPUB intake. The reader now emits
`invalid-collection-id` and `duplicate-collection-id` for malformed or reused
collection `id` attributes while preserving valid collection links, collection
metadata diagnostics, and readable spine import. This remains lane-local
native PHP and does not invoke upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, generated multi-rendition
authoring beyond preserved OPF payloads, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 OPF collection metadata
property diagnostics for Data Liberation EPUB intake. The reader now emits
`missing-collection-metadata-property` for collection-local metadata `<meta>`
records that have neither EPUB3 `property` nor legacy OPF2 `name`, while
keeping legacy named collection metadata in the separate
`invalid-collection-opf2-meta` review bucket. This remains lane-local native
PHP and does not invoke upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, generated multi-rendition
authoring beyond preserved OPF payloads, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 OPF package meta-property
diagnostics for Data Liberation EPUB intake. The reader now emits
`missing-package-meta-property` for direct package metadata `<meta>` records
that have neither EPUB3 `property` nor legacy OPF2 `name`, while keeping
legacy named metadata in the separate `invalid-package-opf2-meta` review
bucket. This remains lane-local native PHP and does not invoke upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, generated multi-rendition authoring beyond preserved OPF
payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 OPF package OPF2-meta
diagnostics for Data Liberation EPUB intake. The reader now emits
`invalid-package-opf2-meta` for direct package metadata `<meta name="...">`
records that lack EPUB3 `property` attributes, while preserving useful legacy
fallbacks such as `meta name="cover"` so importer review can flag the package
problem without losing cover recovery. Collection-local OPF2-style metadata
continues to use `invalid-collection-opf2-meta`. This remains lane-local
native PHP and does not invoke upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, generated multi-rendition
authoring beyond preserved OPF payloads, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 OPF collection metadata ID
diagnostics for Data Liberation EPUB intake. The reader now preserves
collection metadata records while emitting `invalid-collection-metadata-id`
and `duplicate-collection-metadata-id` diagnostics for malformed or reused
collection-local metadata child IDs. Those diagnostics include collection
context and stay separate from package metadata ID diagnostics, collection
metadata `refines` diagnostics, OPF2-style collection metadata diagnostics,
and collection link refinement diagnostics. This remains lane-local native PHP
and does not invoke upstream Pandoc, live fetching, shelling out to converters,
EPUBCheck, full fixture corpus parity, generated multi-rendition authoring
beyond preserved OPF payloads, DRM decryption, XML signature cryptographic
validation, generic TeX-to-MathML conversion, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 OPF package-link attribute
diagnostics for Data Liberation EPUB intake. The reader now preserves package
metadata and collection link records while emitting
`invalid-package-link-media-type`, `invalid-package-link-hreflang`, and
`invalid-package-link-dir` diagnostics for malformed optional link attributes
on package metadata and collection `<link>` elements. Required missing
`href`/`rel` diagnostics and missing local link-resource checks remain separate,
valid local links are not reported, and remote metadata links are still skipped
by ZIP-resource checks. This remains lane-local native PHP and does not invoke
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, generated multi-rendition authoring beyond preserved
OPF payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 OPF collection metadata
`refines` diagnostics for Data Liberation EPUB intake. The reader now
preserves collection metadata records while emitting
`invalid-collection-metadata-refines` diagnostics for collection-local metadata
with empty `refines` attributes, non-fragment values, empty fragments, or
invalid XML-ID fragment targets. Well-formed `#id` references inside
collections still use `collection-metadata-refines-outside` when they point
outside the containing collection, so malformed collection metadata pointers
and outside-collection targets remain separate review categories. This remains
lane-local native PHP and does not invoke upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full fixture corpus parity, generated
multi-rendition authoring beyond preserved OPF payloads, DRM decryption, XML
signature cryptographic validation, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 OPF metadata `refines`
diagnostics for Data Liberation EPUB intake. The reader now preserves package
metadata records while emitting `invalid-metadata-refines` diagnostics for
empty `refines` attributes, non-fragment values, empty fragments, and invalid
XML-ID fragment targets. Well-formed `#id` refinements still use the existing
missing-target diagnostic path, so malformed references and absent valid
targets stay distinguishable for review UI routing. This remains lane-local
native PHP and does not invoke upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, generated multi-rendition
authoring beyond preserved OPF payloads, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 OPF metadata ID diagnostics
for Data Liberation EPUB intake. The reader now preserves direct package
metadata records while emitting diagnostics when metadata child `id` attributes
are not XML NCNames or when the same metadata ID is reused within the package
metadata element. Diagnostic context includes the element, offending ID, prior
duplicate element, and useful metadata fields such as `property`, `name`,
`rel`, `href`, or Dublin Core text where available. This remains lane-local
native PHP and does not invoke upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, full fixture corpus parity, generated multi-rendition
authoring beyond preserved OPF payloads, DRM decryption, XML signature
cryptographic validation, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 OPF guide-reference
diagnostics for Data Liberation EPUB intake. The reader now preserves legacy
`guide` metadata while emitting diagnostics for guide references missing
`type`, missing `href`, pointing at missing local archive resources, or
pointing at local fragments that do not resolve to target element ids. Remote
guide hrefs are preserved as external metadata instead of being treated as
missing ZIP entries. This remains lane-local native PHP and does not invoke
upstream Pandoc, live fetching, shelling out to converters, EPUBCheck, full
fixture corpus parity, generated multi-rendition authoring beyond preserved
OPF payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 OCF container link
diagnostics for Data Liberation EPUB intake. The reader now preserves malformed
`container.xml` link records in `epubContainerLinks`, emits diagnostics for
missing `href`, missing `rel`, invalid `media-type`, and missing local
container-link resources, and skips missing-resource ZIP diagnostics for remote
metadata links. This remains lane-local native PHP and does not invoke upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, generated multi-rendition authoring beyond preserved OPF
payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` exercises bounded EPUB3 OCF container metadata
diagnostics for Data Liberation EPUB intake. The reader now preserves
`container.xml` rootfile entries even when `full-path` normalizes empty, emits
diagnostics for invalid container roots/versions and missing or invalid
rootfile `full-path`/`media-type` attributes, and still imports a readable
fallback OPF package when no OPF-typed rootfile exists but a usable path is
present. This remains lane-local native PHP and does not invoke upstream
Pandoc, live fetching, shelling out to converters, EPUBCheck, full fixture
corpus parity, generated multi-rendition authoring beyond preserved OPF
payloads, DRM decryption, XML signature cryptographic validation, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` and `tests/EpubWriterTest.php` exercise bounded
EPUB3 semantic htmlAttributes fidelity for Data Liberation EPUB packages whose
spine XHTML carries source layout or migration hints in `style` and `data-*`
attributes. The reader records those values as
`epubSpineItemRefs[*].semanticElements[*].htmlAttributes`, normalizes CSS
resource URLs inside captured style metadata, and keeps otherwise untyped
body descendants when those attributes are the useful provenance. Generated
EPUB read-back now exposes writer-emitted `data-source` attributes on semantic
figure, section, and link records. This remains lane-local native PHP and does
not invoke upstream Pandoc, live fetching, shelling out to converters,
arbitrary XHTML DOM preservation, browser CSS/layout rendering, EPUBCheck,
full fixture corpus parity, generated multi-rendition authoring beyond
preserved OPF payloads, generic TeX-to-MathML conversion, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubReaderTest.php` and `tests/EpubWriterTest.php` exercise bounded
EPUB3 XHTML head-title fidelity for Data Liberation EPUB packages whose spine
chapters carry source titles in `<head><title>`. The reader records those
titles as `epubSpineItemRefs[*].headTitle`; the writer emits metadata-provided
head titles into generated spine `<title>` elements while keeping the document
metadata title fallback; read-back verifies the per-spine title survives. This
remains lane-local native PHP and does not invoke upstream Pandoc, live
fetching, shelling out to converters, arbitrary XHTML head DOM preservation,
browser title/navigation rendering, EPUBCheck, full fixture corpus parity,
generated multi-rendition authoring beyond preserved OPF payloads, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` and `tests/EpubWriterTest.php` exercise bounded
EPUB3 nav-section fidelity for Data Liberation EPUB packages whose `nav.xhtml`
TOC, landmarks, or page-list sections carry root attributes or custom heading
labels. The reader records nav root attributes and headings in metadata; the
writer emits those records back onto generated `<nav>` sections while
preserving the required EPUB nav type tokens; read-back verifies ids, extra
type tokens, roles, titles, ARIA labels, classes, language/direction, hidden
state, and custom section headings survive. This remains lane-local native PHP
and does not invoke upstream Pandoc, live fetching, shelling out to
converters, full nav vocabulary validation, EPUBCheck, full fixture corpus
parity, generated multi-rendition authoring beyond preserved OPF payloads,
generic TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` and `tests/EpubWriterTest.php` exercise bounded
EPUB3 spine-root identity fidelity for Data Liberation EPUB packages whose OPF
spine element carries an explicit XML `id`. The reader records
`<spine id="...">` as `epubSpineId`; the writer emits
`spineId`/`epubSpineId` on generated OPF spine roots; read-back verifies the
spine id survives. This remains lane-local native PHP and does not invoke
upstream Pandoc, live fetching, shelling out to converters, full OPF vocabulary
validation, EPUBCheck, full fixture corpus parity, generated multi-rendition
authoring beyond preserved OPF payloads, generic TeX-to-MathML conversion, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` and `tests/EpubWriterTest.php` exercise bounded
EPUB3 package-root identity fidelity for Data Liberation EPUB packages whose
OPF package element carries an explicit XML `id`. The reader records
`<package id="...">` as `epubPackageId`; the writer emits
`packageId`/`epubPackageId` on generated OPF package roots; read-back verifies
the package id survives. This remains lane-local native PHP and does not
invoke upstream Pandoc, live fetching, shelling out to converters, full OPF
vocabulary validation, EPUBCheck, full fixture corpus parity, generated
multi-rendition authoring beyond preserved OPF payloads, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubReaderTest.php` and `tests/EpubWriterTest.php` exercise bounded
EPUB3 package metadata language fidelity for Data Liberation EPUB packages with
external metadata records and collection catalogs. Package metadata links and
collection links now keep `hreflang`; collection-local metadata records keep
`dir` plus `xml:lang`/`lang`; generated OPF output and read-back metadata both
verify those fields survive. This remains lane-local native PHP and does not
invoke upstream Pandoc, live fetching, shelling out to converters, metadata
vocabulary validation, EPUBCheck, full fixture corpus parity, generated
multi-rendition authoring beyond preserved OPF payloads, generic
TeX-to-MathML conversion, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises bounded EPUB3 per-spine rendition-flow
fidelity for a Data Liberation EPUB package whose normalized spine metadata
carries direct `renditionFlow` intent. The writer now emits the itemref
`rendition:flow-*` property and an OPF `<meta property="rendition:flow"
refines="#itemref-id">...</meta>` refinement targeted at the generated
itemref id, then reads the EPUB back and verifies the flow value and rewritten
refinement metadata survive without stale source-package targets. This remains
lane-local native PHP and does not invoke upstream Pandoc, live fetching,
shelling out to converters, EPUBCheck, full rendition/layout validation,
generated multi-rendition authoring beyond preserved OPF payloads, generic
TeX-to-MathML conversion, full EPUB fixture corpus parity, or EPUB2-specific
output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises bounded EPUB3 XHTML root-attribute
fidelity for a Data Liberation EPUB package whose spine document root carries
semantic source attributes. It writes metadata-provided `id`, `role`,
`title`, `aria-label`, class tokens, `prefix`, `lang`/`xml:lang`, `dir`, and
`hidden` attributes onto generated spine `<html>` elements, then reads the
EPUB back and verifies the spine item carries the same root metadata plus
language/direction metadata. Namespace, language, and direction generation
stay on the writer's validated path rather than arbitrary attribute override.
This remains lane-local native PHP and does not invoke upstream Pandoc, live
fetching, shelling out to converters, arbitrary XHTML root DOM preservation,
generated multi-rendition authoring, EPUBCheck, generic TeX-to-MathML
conversion, arbitrary HTML5 tree construction, full EPUB fixture corpus
parity, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises bounded EPUB3 XHTML body-attribute
fidelity for a Data Liberation EPUB package whose spine document body carries
semantic source attributes. It writes metadata-provided `id`, `role`,
`title`, `aria-label`, class tokens, `epub:type`, `lang`/`xml:lang`, `dir`,
and `hidden` attributes onto generated spine `<body>` elements, then reads the
EPUB back and verifies the same body metadata survives. This remains
lane-local native PHP and does not invoke upstream Pandoc, live fetching,
shelling out to converters, arbitrary XHTML body DOM preservation, generated
multi-rendition authoring, EPUBCheck, generic TeX-to-MathML conversion,
arbitrary HTML5 tree construction, full EPUB fixture corpus parity, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises bounded EPUB3 XHTML head-style fidelity
for a Data Liberation EPUB package whose spine documents carry chapter-local
CSS in `<head><style>` elements. It writes a metadata-provided linked base CSS
resource plus an inline style override, verifies generated spine XHTML emits
the style after the linked stylesheet and escapes XML-sensitive CSS characters,
then reads the EPUB back and verifies the original CSS text and style
attributes survive. This remains lane-local native PHP and does not invoke
upstream Pandoc, live fetching, shelling out to converters, arbitrary XHTML
head DOM preservation, CSS cascade validation, browser rendering, generated
multi-rendition authoring, EPUBCheck, generic TeX-to-MathML conversion,
arbitrary HTML5 tree construction, full EPUB fixture corpus parity, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises bounded EPUB3 XHTML head-meta fidelity
for a Data Liberation EPUB package whose spine documents carry review,
accessibility, or browser-style source metadata in `<head><meta>` elements. It
writes metadata-provided `name`, `property`, and `http-equiv` records, verifies
generated spine XHTML keeps `id`, `content`, `refines`, `scheme`, `xml:lang`,
and `dir` where present, confirms generated viewport and charset metadata do
not duplicate, then reads the EPUB back and verifies the same semantic head
metadata survives. This remains lane-local native PHP and does not invoke
upstream Pandoc, live fetching, shelling out to converters, arbitrary XHTML
head DOM preservation, generated multi-rendition authoring, EPUBCheck, generic
TeX-to-MathML conversion, arbitrary HTML5 tree construction, full EPUB fixture
corpus parity, or EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises bounded EPUB3 XHTML head-link fidelity
for a Data Liberation EPUB package whose spine documents carry source
stylesheet/profile metadata in `<head><link>` elements. It writes metadata
provided package-relative and remote head links, verifies generated spine XHTML
keeps `id`, `rel`, `type`, `media`, `title`, `properties`, and rewritten hrefs
without duplicating the packaged CSS resource link, then reads the EPUB back
and verifies the same link metadata survives. This remains lane-local native
PHP and does not invoke upstream Pandoc, live fetching, shelling out to
converters, arbitrary XHTML head DOM preservation, generated multi-rendition
authoring, EPUBCheck, generic TeX-to-MathML conversion, arbitrary HTML5 tree
construction, full EPUB fixture corpus parity, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises bounded EPUB3 navigation-label fidelity
for a Data Liberation EPUB package whose table of contents needs more than
text and hrefs. It writes metadata-provided TOC entries with `id`, `title`,
`role`, `aria-label`, class tokens, `hidden`, `epub:type`, linked labels, and
non-clickable span labels, inspects generated `nav.xhtml`, then reads the EPUB
back and verifies the metadata still carries those fields. This remains
lane-local native PHP and does not invoke upstream Pandoc, live fetching,
shelling out to converters, arbitrary nav DOM preservation, generated
multi-rendition authoring, EPUBCheck, generic TeX-to-MathML conversion,
arbitrary HTML5 tree construction, full EPUB fixture corpus parity, or
EPUB2-specific output behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises bounded EPUB3 container rootfile payload
preservation for a Data Liberation package with a generated primary OPF and a
real alternate OPF rendition. It reads a multi-rootfile `META-INF/container.xml`
with resource extraction enabled, verifies the alternate OPF payload is carried
as metadata, writes the package back, and inspects the archive to confirm the
alternate OPF and rootfile entry survive while a metadata-only non-OPF rootfile
is not emitted. This remains lane-local native PHP and does not invoke upstream
Pandoc, live fetching, shelling out to converters, generated multi-rendition
authoring, EPUBCheck, generic TeX-to-MathML conversion, arbitrary HTML5 tree
construction, full EPUB fixture corpus parity, or EPUB2-specific output
behavior.

Previous scenario:
`tests/EpubWriterTest.php` exercises bounded EPUB3 table import/export for a
Data Liberation document that needs WordPress block-table fidelity after an
EPUB package round trip. It writes a complex table into generated spine XHTML
with caption inlines, colgroup widths, `thead`/`tbody`/`tfoot`, row-head
columns, body header rows, spans, nested paragraph/strong cell content, data
attributes, retained background styles, and left/center/right cell alignment,
then reads the EPUB back and verifies the native table AST still exposes the
same structural sections and table-level alignments. This remains lane-local
native PHP and does not invoke upstream Pandoc, live fetching, shelling out to
converters, EPUBCheck, generic TeX-to-MathML conversion, arbitrary HTML5 tree
construction, full EPUB fixture corpus parity, or EPUB2-specific output
behavior.

Previous scenario:
`examples/wordpress-native-html-standalone-svg-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` `pPlain` plus `pSvg`/raw-inline behavior and
`TagCategories` `eitherBlockOrInline` classification for source packets that
start with standalone HTML `<svg>` fragments. It reads imported source-icon
SVG markup and renders WordPress paragraph HTML with raw SVG boundaries
preserved for review instead of treating the packet as an unmapped block. This
remains lane-local and does not invoke upstream Pandoc, live fetching, shelling
out to converters, DOCX package parsing, browser DOM handling, XML/HTML
support rows, package/PDF support rows, citation engines, PlainMath/MathML
conversion, Unicode/charset ports, syntax-highlighting support rows, SVG
sanitization policy, image extraction, arbitrary SVG DOM behavior, or arbitrary
HTML5 tree construction.

Previous scenario:
`examples/wordpress-native-html-standalone-applet-handoff.php` exercises
upstream `Text.Pandoc.Readers.HTML` `pPlain` plus inline raw-HTML fallback
behavior and `TagCategories` `eitherBlockOrInline` classification for source
packets that start with standalone HTML `<applet>` fragments. It reads legacy
Java applet markup with fallback text and renders WordPress paragraph HTML
with active applet boundaries instead of escaped literal text. This remains
lane-local and does not invoke upstream Pandoc, live fetching, shelling out to
converters, DOCX package parsing, browser DOM handling, XML/HTML support rows,
package/PDF support rows, citation engines, PlainMath/MathML conversion,
Unicode/charset ports, syntax-highlighting support rows, full plugin
execution semantics, arbitrary applet parameter handling, or arbitrary HTML5
tree construction.

Previous scenario:
`examples/wordpress-native-html-standalone-object-handoff.php` exercises
upstream `Text.Pandoc.Readers.HTML` `pPlain` plus inline raw-HTML fallback
behavior and `TagCategories` `eitherBlockOrInline` classification for source
packets that start with standalone HTML `<object>` fragments. It reads legacy
interactive embed markup with an `<embed>` fallback and renders WordPress
paragraph HTML with active object/embed boundaries instead of escaped literal
text. This remains lane-local and does not invoke upstream Pandoc, live
fetching, shelling out to converters, DOCX package parsing, browser DOM
handling, XML/HTML support rows, package/PDF support rows, citation engines,
PlainMath/MathML conversion, Unicode/charset ports, syntax-highlighting
support rows, full media/object DOM semantics, arbitrary media/object
fallback, or arbitrary HTML5 tree construction.

Previous scenario:
`examples/wordpress-native-html-standalone-video-handoff.php` exercises
upstream `Text.Pandoc.Readers.HTML` `pPlain` plus inline raw-HTML fallback
behavior and `TagCategories` `eitherBlockOrInline` classification for source
packets that start with standalone HTML `<video>` fragments. It reads
classic-editor video markup with `<source>` and `<track>` children and renders
WordPress paragraph HTML with active playable media, poster metadata, and
caption-track metadata instead of escaped literal text. This remains
lane-local and does not invoke upstream Pandoc, live fetching, shelling out to
converters, DOCX package parsing, browser DOM handling, XML/HTML support rows,
package/PDF support rows, citation engines, PlainMath/MathML conversion,
Unicode/charset ports, syntax-highlighting support rows, full media DOM
semantics, arbitrary media/object fallback, or arbitrary HTML5 tree
construction.

Previous scenario:
`examples/wordpress-native-html-standalone-audio-handoff.php` exercises
upstream `Text.Pandoc.Readers.HTML` `pPlain` plus inline raw-HTML fallback
behavior and `TagCategories` `eitherBlockOrInline` classification for source
packets that start with standalone HTML `<audio>` fragments. It reads
classic-editor audio markup with `<source>` and `<track>` children and renders
WordPress paragraph HTML with active playable media and caption-track metadata
instead of escaped literal text. This remains lane-local and does not invoke
upstream Pandoc, live fetching, shelling out to converters, DOCX package
parsing, browser DOM handling, XML/HTML support rows, package/PDF support rows,
citation engines, PlainMath/MathML conversion, Unicode/charset ports,
syntax-highlighting support rows, full media DOM semantics, arbitrary
media/object fallback, or arbitrary HTML5 tree construction.

Previous scenario:
`examples/wordpress-native-html-standalone-map-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` `pPlain` plus inline raw-HTML fallback behavior and
`TagCategories` `eitherBlockOrInline` classification for source packets that
start with standalone HTML `<map>` fragments. It reads classic-editor image-map
hotspots and renders WordPress paragraph HTML with active `<map>`/`<area>`
markup instead of escaped literal text. This remains lane-local and does not
invoke upstream Pandoc, live fetching, shelling out to converters, DOCX package
parsing, browser DOM handling, XML/HTML support rows, package/PDF support rows,
citation engines, PlainMath/MathML conversion, Unicode/charset ports,
syntax-highlighting support rows, standalone anchor reconciliation, full
image-map DOM semantics, arbitrary inline raw HTML flow, or arbitrary HTML5
tree construction.

Previous scenario:
`examples/wordpress-native-html-standalone-del-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` `pPlain` plus inline dispatch behavior and
`TagCategories` `eitherBlockOrInline` classification for source packets that
start with standalone HTML `<del>` fragments. It reads classic-editor deletion
markup beside inserted replacement copy and renders WordPress paragraph HTML
with active `<del>`/`<u>` markup instead of escaped literal tags or raw block
boundaries. This remains lane-local and does not invoke upstream Pandoc, live
fetching, shelling out to converters, DOCX package parsing, browser DOM
handling, XML/HTML support rows, package/PDF support rows, citation engines,
PlainMath/MathML conversion, Unicode/charset ports, syntax-highlighting
support rows, standalone anchor reconciliation, arbitrary inline raw HTML
flow, or arbitrary HTML5 tree construction.

Previous scenario:
`examples/wordpress-native-html-standalone-linebreak-handoff.php` exercises
upstream `Text.Pandoc.Readers.HTML` `pPlain` plus `pLineBreak` behavior for
source packets that start with standalone HTML `<br>` fragments instead of a
block wrapper. It reads classic-editor line-break placeholders and renders
WordPress paragraph HTML with active `<br/>` markup instead of escaped literal
tags. This remains lane-local and does not invoke upstream Pandoc, live
fetching, shelling out to converters, DOCX package parsing, browser DOM
handling, XML/HTML support rows, package/PDF support rows, citation engines,
PlainMath/MathML conversion, Unicode/charset ports, syntax-highlighting
support rows, standalone anchor reconciliation, arbitrary inline raw HTML
flow, or arbitrary HTML5 tree construction.

Previous scenario:
`examples/wordpress-native-html-standalone-inline-flow-handoff.php` exercises
upstream `Text.Pandoc.Readers.HTML` `pPlain` plus `inline` dispatch behavior
for source packets that start with balanced inline HTML fragments instead of a
block wrapper. It reads standalone `<small>`, `span.smallcaps`, `<time>`,
`<q cite>`, and `<cite>` fragments and renders WordPress paragraph HTML where
fine print, small-caps terms, date metadata, quoted-source citation, and cite
boundaries stay reviewable instead of appearing as escaped literal tags. This
remains lane-local and does not invoke upstream Pandoc, live fetching,
shelling out to converters, DOCX package parsing, browser DOM handling,
XML/HTML support rows, package/PDF support rows, citation engines,
PlainMath/MathML conversion, Unicode/charset ports, syntax-highlighting support
rows, standalone anchor reconciliation, or arbitrary inline raw HTML flow.

Previous scenario:
`examples/wordpress-native-html-cite-wbr-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` `pRawHtmlInline` fallback behavior for bounded
inline source markup that is not handled by richer semantic branches. It reads
a full HTML source packet where `<cite>` marks the imported source title and
`<wbr>` marks a long slug break, then renders WordPress paragraph HTML with
those source boundaries preserved for review. This remains lane-local and does
not invoke upstream Pandoc, live fetching, shelling out to converters, DOCX
package parsing, browser DOM handling, XML/HTML support rows, package/PDF
support rows, citation engines, PlainMath/MathML conversion, Unicode/charset
ports, syntax-highlighting support rows, or full HTML5 raw inline fallback
parity beyond the bounded tags.

Previous scenario:
`examples/wordpress-native-html-pre-code-breaks-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` `pCodeBlock`/`tagToText` behavior for preformatted
HTML code exports. It reads a full HTML source packet where `<br>` inside
`<pre><code>` separates classic-editor code lines and a bare `<pre>` carries
source review attributes, then renders WordPress code blocks with those line
breaks preserved. This remains lane-local and does not invoke upstream Pandoc,
live fetching, shelling out to converters, DOCX package parsing, browser DOM
handling, XML/HTML support rows, package/PDF support rows, citation engines,
PlainMath/MathML conversion, Unicode/charset ports, syntax-highlighting support
rows, or broader HTML5 tree-construction parity.

Previous scenario:
`examples/wordpress-native-docx-nested-links-handoff.php` exercises upstream
`test/docx/nested_anchors_in_header.native` plus Pandoc writer `removeLinks`
behavior for DOCX-generated TOC/cross-reference labels. It reads a copied
upstream Native fixture with outer links whose labels contain inner page-number
links, and renders WordPress paragraphs where the outer anchors remain active
while the inner page labels become spans. This remains lane-local and does not
invoke upstream Pandoc, live fetching, shelling out to converters, DOCX package
parsing, browser DOM handling, XML/HTML support rows, package/PDF support rows,
citation engines, PlainMath/MathML conversion, Unicode/charset ports,
syntax-highlighting support rows, or broader OpenXML support.

Previous scenario:
`examples/wordpress-markdown-gfm-details-list-handoff.php` exercises upstream
command fixture `test/command/9792.md` behavior for GFM writer output around a
nested list inside raw `<details>` boundaries. It builds a WordPress reviewer
packet AST, emits GFM-safe disclosure markup with the blank lines Pandoc adds
around the nested list and closing raw HTML boundary, and also renders active
WordPress list/raw-HTML blocks from the same AST. This remains lane-local and
does not invoke upstream Pandoc, live fetching, shelling out to converters,
browser DOM handling, XML/HTML support rows, package/PDF support rows,
citation engines, PlainMath/MathML conversion, Unicode/charset ports,
syntax-highlighting support rows, arbitrary raw HTML container parsing, or
broader CommonMark/GFM raw HTML container rules.

Previous scenario:
`examples/wordpress-markdown-details-summary-handoff.php` exercises upstream
command fixture `test/command/6385.md` behavior for Markdown raw
`<details>`/`<summary>` blocks. It imports a disclosure widget from a source
Markdown packet, keeps the `details` and `summary` boundaries as active raw
HTML for review, and parses the details body as editable WordPress paragraph
blocks with emphasis/strong markup. This remains lane-local and does not
invoke upstream Pandoc, live fetching, shelling out to converters, browser DOM
handling, XML/HTML support rows, package/PDF support rows, citation engines,
PlainMath/MathML conversion, Unicode/charset ports, syntax-highlighting
support rows, arbitrary raw HTML container parsing, or broader details
container parsing.

Previous scenario:
`examples/wordpress-native-html-orphan-list-blocks-handoff.php` exercises
upstream `Text.Pandoc.Readers.HTML` orphan list-block handling around `#9187`.
It reads a full HTML source export with malformed direct block children under
`ul`/`ol`, keeps the leading orphan paragraph as a native list item, attaches
a nested orphan list to the preceding item, and keeps an ordered-list
continuation block inside WordPress list markup. This remains lane-local and
does not invoke upstream Pandoc, live fetching, shelling out to converters,
browser DOM handling, XML/HTML support rows, package/PDF support rows,
citation engines, PlainMath/MathML conversion, Unicode/charset ports,
syntax-highlighting support rows, or broader malformed-HTML parser parity.

Previous scenario:
`examples/wordpress-native-html-list-item-id-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` `pListItem` `addId` behavior from
`test/command/3596.md`. It reads a full HTML source export where a tight list
item has an anchor id before a nested list, and a loose paragraph list item
has an anchor id. The handoff keeps the tight anchor as a source span around
the leading inline run, keeps the nested list outside that span, and keeps the
loose anchor as a div wrapper inside the WordPress list item. This remains
lane-local and does not invoke upstream Pandoc, live fetching, shelling out to
converters, browser DOM handling, XML/HTML support rows, package/PDF support
rows, citation engines, PlainMath/MathML conversion, Unicode/charset ports,
syntax-highlighting support rows, or the separate orphan list-block slice.

Previous scenario:
`examples/wordpress-native-html-generic-raw-inline-handoff.php` exercises
upstream `Text.Pandoc.Readers.HTML` `pRawHtmlInline` fallback behavior for
bounded generic inline source markup. It reads a full HTML source export where
source action markup uses a `button`, source date metadata uses `time`, and a
migration comment sits inside reviewer copy. With raw HTML enabled, those
boundaries/comments remain raw inline HTML around parsed child content; with
raw HTML disabled, the lane drops the raw boundaries/comments and keeps child
text. This remains lane-local and does not invoke upstream Pandoc, live
fetching, shelling out to converters, browser DOM handling, XML/HTML support
rows, package/PDF support rows, citation engines, PlainMath/MathML conversion,
Unicode/charset ports, syntax-highlighting support rows, or full HTML5 raw
inline fallback parity beyond the bounded tags/comments.

Previous scenario:
`examples/wordpress-native-html-smallcaps-class-handoff.php` exercises
upstream `Text.Pandoc.Readers.HTML` `pSpan` behavior for
`span class="smallcaps"` source markup. It reads a full HTML source export
where glossary text has neighboring source classes and nested links, maps the
span to native Pandoc `SmallCaps` while dropping the source span metadata like
upstream, and renders WordPress small-caps markup for reviewer handoff. This
remains lane-local and does not invoke upstream Pandoc, live fetching,
shelling out to converters, browser DOM handling, XML/HTML support rows,
package/PDF support rows, citation engines, PlainMath/MathML conversion,
Unicode/charset ports, syntax-highlighting support rows, or broader malformed
HTML parser parity.

Previous scenario:
`examples/wordpress-native-html-checkbox-list-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` `pCheckbox` behavior for
`input type="checkbox"` controls inside list items. It reads a full HTML source
export with checked, unchecked, mixed non-task, and outside-list controls,
renders list-item checkboxes as WordPress reviewer task labels, keeps the
plain non-task item as ordinary text, and drops outside-list form controls from
the reviewer handoff. This remains lane-local and does not invoke upstream
Pandoc, live fetching, shelling out to converters, browser DOM handling,
XML/HTML support rows, package/PDF support rows, citation engines,
PlainMath/MathML conversion, Unicode/charset ports, syntax-highlighting
support rows, or full form-control DOM semantics.

Previous scenario:
`examples/wordpress-native-html-mathml-annotation-handoff.php` exercises
upstream `Text.Pandoc.Readers.HTML` `pMath`, `extractTeXAnnotation`, and
`MJX_Assistive_MathML` behavior. It reads a full HTML source export with
MathML `annotation encoding="application/x-tex"` payloads, unwraps the
assistive MathML span generated by math renderers, renders native WordPress
math spans once, and keeps MathML without embedded TeX visible as a reviewable
fallback span. This remains lane-local and does not invoke upstream Pandoc,
live fetching, shelling out to converters, browser DOM handling, XML/HTML
support rows, package/PDF support rows, citation engines, PlainMath/MathML
full conversion, Unicode/charset ports, or syntax-highlighting support rows.

Previous scenario:
`examples/wordpress-native-html-doc-noteref-table-handoff.php` exercises
upstream Pandoc command fixture 8770-style footnote placement for
`role="doc-noteref"` anchors in a paragraph, table caption, table header cell,
table body cell, and following paragraph. It reads a full HTML export, imports
each anchor as a native note, and renders WordPress table markup where
figcaption remains after the table but footnote numbering follows Pandoc's
logical caption-before-cell order. This remains lane-local and does not invoke
upstream Pandoc, live fetching, shelling out to converters, browser DOM
handling, XML/HTML support rows, package/PDF support rows, citation engines,
PlainMath/MathML conversion, Unicode/charset ports, or syntax-highlighting
support rows.

Previous scenario:
`examples/wordpress-native-html-math-renderer-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` `pSpan` guards for visual MathJax/KaTeX renderer
output. It reads a full HTML source export with `script type="math/tex"`
equation source plus generated `mjx-chtml`, `MathJax_CHTML`,
`MathJax_Preview`, and exact `katex-html` visual spans, drops the renderer-only
duplicates, and renders WordPress math markup once per equation. This remains
lane-local and does not invoke upstream Pandoc, live fetching, shelling out to
converters, browser DOM handling, XML/HTML support rows, PlainMath/MathML
conversion, TeX reference conversion, package/PDF support rows, citation
engines, Unicode/charset ports, or syntax-highlighting support rows.

Previous scenario:
`examples/wordpress-native-html-span-strikeout-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` `pStrikeout` handling for exact
`<span class="strikeout">` source markup plus the adjacent `del`/`ins` edit
branches. It reads a full HTML source export, maps the legacy strikeout span
to a native Pandoc `Strikeout` node instead of a generic span, and renders a
WordPress paragraph where deletion and insertion marks remain reviewable. This
remains lane-local and does not invoke upstream Pandoc, live fetching,
shelling out to converters, browser DOM handling, package/PDF/XML support
rows, citation engines, Unicode/charset ports, or syntax-highlighting support
rows.

Previous scenario:
`examples/wordpress-native-html-line-block-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` `pLineBlock` behavior. It reads a full HTML source
export with `div class="line-block"`, preserves hard `<br>` line splits, empty
lines, NBSP indentation, and source edit links, and renders a WordPress
paragraph handoff instead of a generic div. This remains lane-local and does
not invoke upstream Pandoc, live fetching, shelling out to converters, browser
DOM handling, package/PDF/XML support rows, citation engines,
Unicode/charset ports, or syntax-highlighting support rows.

Previous scenario:
`examples/wordpress-native-html-raw-disabled-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` raw HTML extension guard behavior around
`pRawHtmlBlock`, `pRawHtmlInline`, and `ignore`. It reads a full HTML source
export with `htmlRawHtml` disabled, skips migration `<style>`, generic
`<script>`, and `<textarea>` raw payloads, and still renders
`script type="math/tex"` as native WordPress math markup. This remains
lane-local and does not invoke upstream Pandoc, live fetching, shelling out to
converters, browser DOM handling, package/PDF/XML support rows, citation
engines, Unicode/charset ports, or syntax-highlighting support rows.

Previous scenario:
`examples/wordpress-native-html-script-block-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` generic script handling through `pRawHtmlBlock` and
`pHtmlBlock "script"`. It reads a full HTML source export with a body-level
migration script, preserves the `<script>` element as a native raw HTML block,
and renders it as a WordPress core HTML block instead of paragraph-wrapped
inline HTML. Body-level `script type="math/tex..."` remains routed to native
math rather than a raw block. This remains lane-local and does not invoke
upstream Pandoc, live fetching, shelling out to converters, browser DOM
handling, or activating package, PDF, XML/HTML DOM, citation, Unicode/charset,
math, or syntax support rows.

Previous scenario:
`examples/wordpress-native-html-style-block-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` block-level style handling through
`pRawHtmlBlock` and `pHtmlBlock "style"`. It reads a full HTML source export
with a body-level migration stylesheet, preserves the `<style>` element as a
native raw HTML block, and renders it as a WordPress core HTML block instead
of paragraph-wrapped inline HTML. This remains lane-local and does not invoke
upstream Pandoc, live fetching, shelling out to converters, browser DOM
handling, or activating package, PDF, XML/HTML DOM, citation, Unicode/charset,
math, or syntax support rows.

Previous scenario:
`examples/wordpress-native-html-doc-noteref-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` `replaceNotes`, `eFootnotes`, and `eNoteref`
behavior for full HTML imports. It reads a source export with a
`role="doc-noteref"` anchor and a `role="doc-endnotes"` section, converts the
reference into a native Pandoc `Note`, strips the original backlink, skips the
source endnotes container, and renders a clean WordPress endnotes block. This
remains lane-local and does not invoke upstream Pandoc, live fetching,
shelling out to converters, browser DOM handling, or activating package, PDF,
XML/HTML DOM, citation, Unicode/charset, math, or syntax support rows.

Previous scenario:
`examples/wordpress-native-html-textarea-handoff.php` exercises the upstream
`Text.Pandoc.Readers.HTML` `pRawHtmlBlock` branch for block-level
`<textarea>`. It reads a legacy source packet from a body-level HTML export,
preserves the textarea as a native raw HTML block, and renders it as a
WordPress core HTML block so the payload remains literal during Data
Liberation review. This remains lane-local and does not invoke upstream
Pandoc, live fetching, shelling out to converters, browser DOM/form handling,
or activating package, PDF, XML/HTML DOM, citation, Unicode/charset, math, or
syntax support rows.

Previous scenario:
`examples/wordpress-native-html-style-script-handoff.php` exercises the
upstream `Text.Pandoc.Readers.HTML` inline `<style>` raw HTML branch and
`<script type="math/tex...">` `pScriptMath` branch. It reads an HTML export
with source CSS and TeX equations, keeps the CSS as a raw HTML inline for
review, and renders inline/display script math through native Pandoc math
nodes in the WordPress handoff. This remains lane-local and does not invoke
upstream Pandoc, PlainMath/MathML conversion, live fetching, shelling out to
converters, or activating package, PDF, XML/HTML DOM, citation, Unicode/
charset, or syntax support rows.

Previous scenario:
`examples/wordpress-native-html-svg-raw-handoff.php` exercises the upstream
`Text.Pandoc.Readers.HTML` raw-HTML-enabled SVG path, where `pSvg` is bypassed
by `Ext_raw_html` and the generic raw inline branch preserves SVG markup. It
reads an HTML export with an inline source icon, keeps the SVG as a
`raw_html_inline` node instead of rewriting it to a data image, and renders the
WordPress paragraph with the source SVG still visible for review. This remains
lane-local and does not invoke upstream Pandoc, live fetching, shelling out to
converters, or activating package, PDF, XML/HTML DOM, citation, math,
Unicode/charset, or syntax support rows.

Previous scenario:
`examples/wordpress-native-html-spanlike-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` `pSpanLike` behavior and
`Text.Pandoc.Shared` `htmlSpanLikeElements`. It reads an HTML export with a
keyboard shortcut, publish-highlight text, and source terminology, maps
`<kbd>`, `<mark>`, `<dfn>`, and `<abbr>` to Pandoc spans with tag-name classes
and preserved source metadata, and keeps `<kbd>` distinct from code-like
`code`/`tt`/`samp`/`var` imports. This keeps source-review controls and terms
visible during Data Liberation imports without invoking upstream Pandoc, live
fetching, shelling out to converters, or activating package, PDF, XML/HTML
DOM, citation, math, Unicode/charset, or syntax support rows.

Previous scenario:
`examples/wordpress-native-html-bdo-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` `pBdo` behavior. It reads an HTML export with a
bidirectional source title fragment, maps `<bdo dir="RTL">` to a Pandoc span
with lowercased `dir` metadata, preserves nested strong inline content, and
lets no-dir `<bdo>` contents pass through as plain inline text. This keeps
direction-sensitive source copy visible during Data Liberation imports without
invoking upstream Pandoc, live fetching, shelling out to converters, or
activating package, PDF, XML/HTML DOM, citation, math, Unicode/charset, or
syntax support rows.

Previous scenario:
`examples/wordpress-native-html-small-inline-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` `pSmall` behavior. It reads an HTML export with
source fine print, maps `<small>` to a Pandoc span with class `small`, drops
source id/class attributes to match upstream `B.spanWith ("",["small"],[])`,
preserves nested emphasis/strong inline content, and renders a WordPress
paragraph where the fine print remains reviewable. This keeps source caveats
visible during Data Liberation imports without invoking upstream Pandoc, live
fetching, shelling out to converters, or activating package, PDF, XML/HTML
DOM, citation, math, Unicode/charset, or syntax support rows.

Previous scenario:
`examples/wordpress-native-html-svg-disabled-raw-handoff.php` exercises
upstream `Text.Pandoc.Readers.HTML` `pSvg` behavior when raw HTML is
disabled. It reads an HTML export with a source SVG icon, maps the SVG to a
Pandoc image with a base64 `data:image/svg+xml` URL, preserves the source
id/classes, carries the Font Awesome width hint as `width=1em`, and renders a
WordPress paragraph where the icon remains reviewable inline. This keeps
source SVG status markers visible during Data Liberation imports without
invoking upstream Pandoc, external renderers, live fetching, shelling out to
converters, or activating package, PDF, XML/HTML DOM, citation, math,
Unicode/charset, or syntax support rows.

Previous scenario:
`examples/wordpress-native-html-iframe-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` `pIframe` behavior with local-only resource
injection. It reads an HTML export with a base URL and embedded iframe
resources, maps a text/html frame into reviewable nested blocks, maps an
image frame into a safe image preview inside a Pandoc `iframe` div, and keeps
a generic MIME frame as an empty native iframe container. This keeps embedded
source packets reviewable during Data Liberation imports without invoking
upstream Pandoc, live URL fetching, browser tooling, shelling out to
converters, or activating package, PDF, XML/HTML DOM, citation, math,
Unicode/charset, or syntax support rows.

Previous scenario:
`examples/wordpress-html-writer-remove-links-handoff.php` exercises upstream
`Text.Pandoc.Writers.HTML` `Link` label handling and
`Text.Pandoc.Writers.Shared` `removeLinks` behavior for WordPress review HTML.
It builds a native review link whose label contains a nested source-note link,
renders an HTML preview where the nested label becomes a metadata-preserving
span instead of an invalid nested anchor, and wraps the preview in a WordPress
HTML review block. This keeps source-note identity reviewable during Data
Liberation imports without invoking upstream Pandoc, shelling out to
converters, fetching media, or activating package, PDF, XML/HTML DOM,
citation, math, Unicode/charset, or syntax support rows.

Previous scenario:
`examples/wordpress-html-writer-raw-inline-handoff.php` exercises upstream
`Text.Pandoc.Writers.HTML` `RawInline` handling for WordPress review HTML. It
builds native raw inline nodes for trusted HTML and HTML5 snippets plus a
non-HTML TeX citation payload, renders an HTML preview where only the
HTML-family raw inline snippets pass through, and wraps the preview in a
WordPress HTML review block. This keeps source badges and editorial markups
reviewable during Data Liberation imports without invoking upstream Pandoc,
shelling out to converters, fetching media, or activating package, PDF,
citation, math, or syntax support rows.

Previous scenario:
`examples/wordpress-html-writer-softbreak-handoff.php` exercises upstream
`Text.Pandoc.Writers.HTML` `SoftBreak`/`LineBreak` handling for WordPress
review HTML. It builds native inline break nodes, renders a compact preview
where soft line folds become spaces, renders a source-line-preserving preview
when `writerWrapText=wrap-preserve`, and wraps the preserved preview in a
WordPress HTML review block. This keeps source excerpts and reviewer
checklists readable during Data Liberation imports without invoking upstream
Pandoc, shelling out to converters, fetching media, or activating package,
PDF, citation, math, or syntax support rows.

Previous scenario:
`examples/wordpress-html-writer-spanlike-handoff.php` exercises upstream
`Text.Pandoc.Writers.HTML` `Span` class lowering for WordPress review HTML. It
builds native span nodes for a keyboard shortcut, marked publish-preview text,
and abbr/dfn source terminology, renders an HTML preview with Pandoc-style
`kbd`, `mark`, `dfn`, `abbr`, `u`, and `span.smallcaps` lowering, and wraps
that preview in a WordPress HTML review block. This keeps imported editorial
source notes reviewable without invoking upstream Pandoc, shelling out to
converters, fetching media, or activating package/PDF/citation/math/syntax
support rows.

Previous scenario:
`examples/wordpress-html-writer-media-handoff.php` exercises upstream
`Text.Pandoc.Writers.HTML` image media-category output for WordPress review
HTML. It builds native image nodes for video, audio, and PDF media, renders an
HTML preview with Pandoc-style `<video>`, `<audio>`, and `<embed>` output, and
wraps that preview in a WordPress HTML review block. This keeps imported media
handoffs reviewable without invoking upstream Pandoc, fetching media, shelling
out to converters, or activating PDF/package/rich document-format support.

Previous scenario:
`examples/wordpress-native-html-figure-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` `pFigure`/`pImage` figure and figcaption reader
branches for WordPress review HTML. It reads a source export with a media
figure id, source classes, image alt/title metadata, list body context, and a
rich figcaption containing emphasis and a source-edit link, then renders a
WordPress image block that preserves the reviewable media identity and caption
without invoking upstream Pandoc, a browser, converter shell-outs,
ZIP/package parsing, or broader XML/HTML support-library expansion.

Previous scenario:
`examples/wordpress-native-html-section-aside-handoff.php` exercises upstream
`Text.Pandoc.Readers.HTML` native div-like `section` and `aside` branches for
WordPress review HTML. It reads an HTML export with a `main` article wrapper,
a source-review `section`, and a migration-note `aside`, then renders a
WordPress HTML block that preserves wrapper id/class/data metadata and clears
the duplicated first heading id when it matches the section wrapper. This keeps
source sections and editorial side notes reviewable during Data Liberation
imports without invoking upstream Pandoc, a browser, converter shell-outs,
ZIP/package parsing, or broader XML/HTML support-library expansion.

Previous scenario:
`examples/wordpress-html-writer-math-handoff.php` exercises upstream
`Text.Pandoc.Writers.HTML` MathJax, KaTeX, WebTeX, and GladTeX math output branches for
WordPress review HTML. It builds a native AST with inline and display TeX
equations, then renders a MathJax-style preview with `\(...\)`/`\[...\]`
delimiters, a KaTeX-style preview with raw TeX payloads, a WebTeX image URL
preview with encoded TeX payloads, a GladTeX `eq` preview, and matching
WordPress block handoff markup. This keeps equation source reviewable during
Data Liberation imports without invoking upstream Pandoc, TeXMath/MathML
conversion, image fetching, browser tooling, converter shell-outs,
ZIP/package parsing, or a broader math support library.

Previous scenario:
`examples/wordpress-html-writer-citation-role-handoff.php` exercises upstream
`Text.Pandoc.Writers.HTML` citation and footnote accessibility role branches
for WordPress review HTML. It builds a native AST with a bounded citation
packet, a bibliography link targeting `#ref-source-audit`, an ordinary
WordPress source-review link, a reviewer footnote, and a CSL-style refs block.
The HTML preview emits `data-cites`, `role="doc-biblioref"`,
`role="doc-noteref"`, and `role="doc-backlink"` where upstream does; the
matching WordPress block handoff keeps the citation payload and bibliography
source packet reviewable without invoking upstream Pandoc, citeproc/CSL
processing, browser tooling, converter shell-outs, ZIP/package parsing, or
rich document-format support.

Previous scenario:
`examples/wordpress-html-writer-csl-wrapper-handoff.php` exercises upstream
`Text.Pandoc.Writers.HTML` wrapper `Div` and CSL bibliography `Div` branches
for WordPress review HTML. It builds a native AST with a wrapper div around
the source-review intro and a `refs`/`csl-entry` bibliography packet, then
renders an HTML preview where wrapper attributes move to the paragraph,
`role="list"` and `role="listitem"` are emitted for citation accessibility,
and paragraphs inside CSL entries render as plain bibliography lines. The
matching WordPress block handoff keeps the same source packet as reviewable
HTML blocks without invoking upstream Pandoc, a CSL processor, browser
tooling, converter shell-outs, ZIP/package parsing, or rich document-format
support.

Previous scenario:
`examples/wordpress-html-writer-raw-div-handoff.php` exercises upstream
`Text.Pandoc.Writers.HTML` `RawBlock` and `Div` output for WordPress review
HTML. It builds a native AST with a source review wrapper, trusted raw HTML,
non-HTML raw TeX, and a nested div, then renders an HTML preview where the
wrapper becomes a section element, trusted HTML raw content passes through,
the nested div stays grouped, and non-HTML raw blocks are omitted from the
HTML writer output. The matching WordPress block handoff keeps the raw source
packets reviewable inside the wrapper instead of executing converters or
activating ZIP/package/rich document-format support.

Previous scenario:
`examples/wordpress-html-writer-table-handoff.php` exercises upstream
`Text.Pandoc.Writers.HTML` table output for WordPress review HTML. It builds a
native AST table with a caption, explicit column widths, section metadata,
row-head cells, colspan/rowspan cells, and escaped review text, then renders an
HTML preview preserving the table element attributes, `caption`, `colgroup`,
`thead`, `tbody`, `tfoot`, alignment styles, and spans. The matching WordPress
block handoff keeps the same packet as a core table block for import review.
This covers migration fragments where tabular source audits need reviewable
HTML output without shelling out to Pandoc, invoking a browser/converter, or
activating ZIP/package/rich document-format support.

Previous scenario:
`examples/wordpress-html-writer-figure-line-handoff.php` exercises upstream
`Text.Pandoc.Writers.HTML` structural block output for WordPress review HTML.
It builds a native AST with a source figure from `test/testsuite.native`, a
reviewer line-block stanza, and a section break, then renders an HTML preview
where `<hr />`, `div.line-block`, `figure`, `figcaption`, and alt-equivalent
`aria-hidden` caption behavior are preserved. The matching WordPress block
handoff keeps the same packet as an image block, paragraph with line breaks,
and separator block. This covers migration fragments where imported media,
line-preserved reviewer notes, and section separators need to survive review
output without shelling out to Pandoc, invoking a browser/converter, or
activating ZIP/package/rich document-format support.

Previous scenario:
`examples/wordpress-html-writer-code-block-handoff.php` exercises upstream
`Text.Pandoc.Writers.HTML` `CodeBlock` fallback output for WordPress review
HTML. It builds a native AST with a source-review code snippet, stable source
id, and `data-source` marker, then renders an HTML preview where code text is
escaped inside `pre > code` and source attributes stay on `pre`. The matching
WordPress block handoff keeps the same snippet as a core code block for import
review. This covers migration fragments where legacy shortcode/filter snippets
need to survive review output without shelling out to Pandoc, invoking a
browser/converter, or activating the broader syntax-highlighting support gate.

Previous scenario:
`examples/wordpress-latex-ordered-list-handoff.php` exercises upstream
`Text.Pandoc.Writers.LaTeX` ordered-list label, counter, and tight-list
behavior for WordPress source review exports. It builds a native AST with a
lower-roman source checklist starting at iv and a nested upper-alpha review
subqueue, then renders LaTeX with Pandoc-style `\def\label...`,
`\setcounter`, `\tightlist`, and nested `enumii` output. The matching
WordPress block handoff keeps ordered-list `start` and `type` metadata,
covering review packets where legacy ordered-list numbering must survive in
both printable reviewer output and block-editor HTML without shelling out to
Pandoc, invoking TeX/PDF, using templates, or activating rich package/document
conversion support.

Previous scenario:
`examples/wordpress-latex-quote-hr-handoff.php` exercises upstream
`Text.Pandoc.Writers.LaTeX` block quote and horizontal-rule behavior for
WordPress source review exports. It builds a native AST with a reviewer quote,
Pandoc section separator, and following publish checklist paragraph, then
renders LaTeX where the quote becomes a `quote` environment and the separator
becomes Pandoc's centered rule. The matching WordPress block handoff keeps a
core quote block, separator block, and paragraph, covering review packets where
source caveats and section breaks need printable reviewer output without
shelling out to Pandoc, invoking TeX/PDF, using templates, or activating rich
package/document conversion support.

Previous scenario:
`examples/wordpress-latex-raw-tex-handoff.php` exercises upstream
`Text.Pandoc.Writers.LaTeX` raw TeX passthrough behavior for WordPress source
review exports. It builds a native AST with a raw citation inline and tabular
TeX block, then renders LaTeX where both raw TeX fragments pass through
literally while the matching WordPress block handoff keeps the citation as a
`pandoc-raw-tex` inline span and the table source as review-safe TeX code.
This covers migration-review packets where source TeX needs printable reviewer
output without shelling out to Pandoc, invoking TeX/PDF, using templates, or
activating rich package/document conversion support.

Previous scenario:
`examples/wordpress-latex-highlighted-strikeout-code-handoff.php` exercises
upstream `Tests.Writers.LaTeX` highlighted inline-code behavior inside
strikeout for WordPress source review exports. It builds a native AST with a
Haskell import helper marked as deleted/stale reviewer text, then renders
LaTeX where the code span is protected as
`\st{\mbox{\VERB|\NormalTok{renderBlocks}|} ...}` while the matching
WordPress block handoff keeps `<del>`, the inline `code` class, and
`data-source` metadata. This covers migration-review packets where code-like
source snippets need printable deletion marks without shelling out to Pandoc,
invoking TeX/PDF, using templates, or activating a syntax-highlighting engine.

Previous scenario:
`examples/wordpress-latex-listing-code-handoff.php` exercises upstream
`Tests.Writers.LaTeX` IdiomaticHighlighting code-block listing branches for
WordPress source review exports. It builds a native AST with a labelled PHP
shortcode snippet, then renders LaTeX reviewer text where the snippet is a
`lstlisting` with `label=shortcode-audit` while the matching WordPress block
handoff keeps the code-block id, language class, and `data-source` metadata.
This covers migration-review packets where source snippets need stable
print-review labels without shelling out to Pandoc, invoking TeX/PDF, using
templates, or activating a syntax-highlighting engine.

Previous scenario:
`examples/wordpress-latex-underline-strikeout-note-handoff.php` exercises the
remaining upstream `Tests.Writers.LaTeX` inline-note styling branches for
WordPress source review exports. It builds a native AST with inserted source
context and deleted/stale shortcode text, then renders LaTeX reviewer text
where multi-paragraph notes split outside `\ul{}` and `\st{}` while the
matching WordPress block handoff keeps `<u>`/`<del>` markup and endnotes. This
covers migration-review packets where editorial insertion/deletion marks need
printable reviewer output without changing the block-editor handoff or
shelling out to Pandoc, TeX/PDF, templates, or syntax-highlighting engines.

Previous scenario:
`examples/wordpress-latex-top-level-division-handoff.php` exercises upstream
`Tests.Writers.LaTeX` top-level division writer options for WordPress source
book review exports. It builds a native AST with a legacy handbook heading and
an import-checklist subheading, then renders LaTeX reviewer text with
`writerTopLevelDivision=chapter`, so the hierarchy becomes `\chapter` plus
`\section` while the matching WordPress block handoff remains ordinary heading
blocks. This covers migration-review packets where a source document's book
hierarchy needs printable reviewer output without changing the block-editor
heading levels or shelling out to Pandoc, TeX/PDF, or syntax-highlighting
engines.

Previous scenario:
`examples/wordpress-latex-unnumbered-heading-note-handoff.php` exercises
upstream `Tests.Writers.LaTeX` unnumbered heading-with-note output for
WordPress source audit exports. It builds a native AST heading with
`class="unnumbered"`, an id, and an inline reviewer note, then renders LaTeX
reviewer text with Pandoc's starred section, `\texorpdfstring` fallback,
`\footnote`, `\label`, and `\addcontentsline` shape. The matching WordPress
block handoff keeps the same source audit as a heading plus endnote. This
covers migration-review packets where reviewer-only context must remain
attached to a section title without polluting PDF bookmark text or shelling
out to Pandoc, TeX/PDF, or syntax-highlighting engines.

Previous scenario:
`examples/wordpress-latex-footnote-code-handoff.php` exercises upstream
`Tests.Writers.LaTeX` code-block-in-footnote output for WordPress source audit
exports. It builds a small native AST paragraph with an inline note whose
body contains reviewer prose and a shortcode code block, then renders LaTeX
reviewer text with Pandoc's `\footnote{...}` plus `Verbatim` code-block
shape. The matching WordPress block handoff keeps the same source audit as an
endnote containing a core code block. This covers migration-review packets
where source snippets must remain attached to editorial footnotes without
shelling out to Pandoc or invoking a TeX/PDF/syntax-highlighting engine.

Previous scenario:
`examples/wordpress-latex-heading-image-handoff.php` exercises upstream
`Tests.Writers.LaTeX` heading-image output for WordPress media review exports.
It builds a small native AST heading whose text includes an imported image,
then renders LaTeX reviewer text with Pandoc's
`\texorpdfstring{\protect\pandocbounded{\includegraphics[...]}}{...}` fallback
shape for PDF strings. The matching WordPress block handoff keeps the same
source hero image inside a heading block with alt text preserved. This covers
migration-review packets where imported heading artwork needs printable
reviewer output and block-editor heading HTML without shelling out to Pandoc
or invoking a TeX/PDF/image engine.

Previous scenario:
`examples/wordpress-latex-figure-handoff.php` exercises upstream
`Tests.Writers.LaTeX` figure placement output for WordPress media review
exports. It builds a small native AST figure with `latex-placement="htbp"`,
an imported image URL, alt text, and a caption, then renders LaTeX reviewer
text as a `figure` environment with `\centering`,
`\pandocbounded{\includegraphics[...]}`, and `\caption{...}` output. The
matching WordPress block handoff keeps the same imported media frame as an
image block with the source placement recorded as reviewer metadata. This
covers migration-review packets where imported media needs printable reviewer
output and block-editor media review HTML without shelling out to Pandoc or
invoking a TeX/PDF/image engine.

Previous scenario:
`examples/wordpress-latex-definition-list-handoff.php` exercises upstream
`Tests.Writers.LaTeX` definition-list output for WordPress review exports. It
builds a small native AST definition list with source-review terms, an
internal checklist link, and a heading-bearing definition body, then renders
LaTeX reviewer text with a `description` environment, `\tightlist`, and
`\hyperref` anchors. The matching WordPress block handoff keeps the same
source review packet as definition-list HTML with links and heading content
preserved. This covers migration-review packets where glossary/status terms
need printable reviewer output and block-editor review HTML without shelling
out to Pandoc or invoking a TeX/PDF engine.

Previous scenario:
`examples/wordpress-latex-heading-handoff.php` exercises upstream
`Tests.Writers.LaTeX` heading defaults for WordPress review exports. It builds
a small native AST outline for migration review, media checks, and reviewer
notes, then renders LaTeX reviewer text with `\section`, `\subsection`, and
`\subsubsection` commands. The matching WordPress block handoff keeps the same
source outline as heading blocks with the review anchor preserved. This covers
migration-review packets where an editorial outline needs both printable
review text and block-editor headings without shelling out to Pandoc or
invoking a TeX/PDF engine.

Previous scenario:
`examples/wordpress-latex-code-handoff.php` exercises upstream
`Tests.Writers.LaTeX` inline-code escaping for WordPress review exports. It
builds a small native AST packet with reviewer command/code spans containing
an apostrophe and backticks, then renders LaTeX reviewer text where those code
spans become `\texttt{dog\textquotesingle{}s}` and
`\texttt{\textasciigrave{}nu?\textasciigrave{}}`. The matching WordPress block
handoff keeps the same source fragments as inline `<code>` elements. This
covers migration-review packets where source commands need literal audit text
without shelling out to Pandoc or invoking a TeX/PDF engine.

Previous scenario:
`examples/wordpress-html-writer-list-handoff.php` exercises upstream
`Text.Pandoc.Writers.HTML` `BulletList` and `OrderedList` output for
WordPress review HTML. It builds a small native AST packet with an ordered
source checklist starting at a non-1 upper-alpha marker, nested bullet-list
evidence, and task-list checkbox labels, then renders both an HTML preview and
the matching `WordPressBlockWriter` block-list handoff. This covers migration
review fragments where source numbering, nested list shape, and task status
need to survive without shelling out to Pandoc or using a browser/converter.

Previous scenario:
`examples/wordpress-html-writer-section-div-footnotes-handoff.php` exercises
upstream `Tests.Writers.HTML` `EndOfSection` plus `writerSectionDivs` output
for WordPress review HTML. It builds a small native AST packet with a
top-level review heading, a source-notes section containing a reviewer note,
and a later publish-checklist section. Rendering with `writerSectionDivs`
keeps the footnote block inside the source-notes section before the checklist
section begins, matching Pandoc's section-div footnote placement semantics
without shelling out to Pandoc.

Previous scenario:
`examples/wordpress-html-writer-footnote-placement-handoff.php` exercises
upstream `Tests.Writers.HTML` footnote placement output for WordPress review
HTML. It builds a small native AST packet with a paragraph note and a
blockquote note, then renders with `referenceLocation=end_of_block` so each
note is emitted after the block that introduced it. This covers migration
review fragments where source edit links and quote-scoped notes need to stay
attached without shelling out to Pandoc.

Previous scenario:
`examples/wordpress-html-writer-highlighted-code-handoff.php` exercises
upstream `Tests.Writers.HTML` highlighted inline-code output for WordPress
review HTML. It builds a small native AST transform-diagnostic packet with a
Haskell-style operator, then renders `sourceCode haskell` code where `>>=`
is wrapped in `span.op`; sample diagnostics are wrapped in `samp` and
post-field variables are wrapped in `var`. This covers migration/review
fragments where source diagnostics need semantic code roles and readable
operator highlighting without shelling out to Pandoc or activating the broader
syntax-highlighting support gate.

Previous scenario:
`examples/wordpress-html-writer-definition-list-handoff.php` exercises
upstream `Tests.Writers.HTML` definition-list output for WordPress review HTML.
It builds a small native AST glossary/status packet with one ordinary source
term and one blank source term, then renders Pandoc-style `<dl>`, `<dt>`, and
`<dd>` output. This covers migration/review fragments where a legacy source
exports an empty glossary term and the review handoff must preserve that fact
without placeholder text or a Pandoc shell-out.

Previous scenario:
`examples/wordpress-html-writer-quote-cite-handoff.php` exercises upstream
`Tests.Writers.HTML` quote-with-cite output for WordPress review HTML. It
builds a small native AST packet where a reviewer quote carries source
citation metadata, then renders with the `htmlQTags` option so the output is
`q cite` rather than a nested span. This covers migration/review fragments
where source notes need semantic citation metadata without shelling out to
Pandoc.

Previous scenario:
`examples/wordpress-html-writer-image-attrs-handoff.php` exercises upstream
`Tests.Writers.HTML` image-alt and heading-attribute output for WordPress
media review. It builds a small native AST packet where formatted image label
inlines stringify to a plain `alt` attribute, the source image title and
`data-source` marker survive, and noisy unsupported heading metadata is
dropped while `lang` remains. This covers WordPress import/review HTML
fragments where media accessibility text and source tracing must survive
without rendering markup inside `alt` or shelling out to Pandoc.

Previous scenario:
`examples/wordpress-html-writer-code-roles-handoff.php` exercises upstream
`Tests.Writers.HTML` code-role output for WordPress reviewer diagnostics. It
builds a small native AST packet where a block name stays ordinary `code`, a
sample migration warning renders as `samp`, and a post-field variable renders
as `var`. This covers WordPress import/review HTML fragments where semantic
diagnostic roles must survive without flattening every code-like token into a
classed `<code>` element or shelling out to Pandoc.

Previous scenario:
`examples/wordpress-native-html-codeblock-attrs-handoff.php` exercises upstream
`Tests.Readers.HTML` `pre > code` attribute behavior for WordPress import
review. It parses a legacy HTML export whose code block carries a source
snippet id, language class, and `data-source` metadata, then renders a
WordPress code block that keeps the id/data metadata on the outer `<pre>` while
the language remains on the inner `<code>`. It also covers Pandoc's upstream
precedence rule where attributes on `<pre>` replace nested `<code>` attributes,
so review wrappers win over stale nested snippet ids without shelling out to
Pandoc.

Previous scenario:
`examples/wordpress-native-html-lang-metadata-handoff.php` exercises upstream
`Tests.Readers.HTML` root `lang` and `xml:lang` behavior for WordPress import
review. It parses a legacy HTML export whose `<html>` element declares
`lang="es"` and renders with metadata review enabled, so the source language
stays attached to the imported content as `lang=es` while the body copy becomes
ordinary WordPress paragraph output. This covers Data Liberation imports where
source language metadata is needed for editorial routing, accessibility review,
and translation workflows without shelling out to Pandoc.

Previous scenario:
`examples/wordpress-native-html-inline-code-handoff.php` exercises upstream
`Tests.Readers.HTML` inline `code`, `tt`, `samp`, and `var` behavior for
WordPress import review. It parses a legacy HTML export whose diagnostics
mention a block name, a shortcode-like `tt` fragment, a sample reviewer
message, and a variable name. The WordPress output keeps all four as inline
code and preserves Pandoc's `sample`/`variable` classes on the `samp` and
`var` branches. This covers Data Liberation imports where source HTML uses
semantic inline code roles and migration tooling must not flatten them into
literal tags or ordinary paragraph text.

Previous scenario:
`examples/wordpress-native-html-header-handoff.php` exercises upstream
`Tests.Readers.HTML` native-div `<header>` behavior for WordPress import
review. It parses a legacy HTML export whose `<main>` content contains an
article `<header>` and uses the opt-in `htmlNativeDivs` reader path to keep
that header as a native div with `class="header"`, id metadata, and
review-facing data attributes. This covers Data Liberation imports where the
source export's article title/deck region belongs to the post body and must
not be flattened into an ordinary paragraph or dropped with surrounding site
chrome.

Previous scenario:
`examples/wordpress-native-html-main-handoff.php` exercises upstream
`Tests.Readers.HTML` native-div `<main>` behavior for WordPress import review.
It parses a legacy HTML export with header, nav, main, and footer regions and
uses the opt-in `htmlNativeDivs` reader path to keep only the first main
document body. The WordPress output preserves the main wrapper's id, class,
data-source, and generated `role="main"` metadata while dropping surrounding
export boilerplate. This covers Data Liberation imports where a source HTML
dump contains navigational chrome that must not become post content, without
shelling out to Pandoc.

Previous scenario:
`examples/wordpress-native-html-anchor-image-attrs-handoff.php` exercises
upstream `Tests.Readers.HTML` anchor and image-attribute behavior for
WordPress import review. It parses HTML exported with legacy `<a name>` and
id-only jump targets plus an externally sourced image marked
`data-external="1"`. The WordPress output keeps the anchors as span targets
instead of empty links and carries the external image metadata through to the
rendered image tag. This covers Data Liberation imports where old HTML
bookmarks and externally hosted media need reviewer-visible boundaries without
shelling out to Pandoc.

Previous scenario:
`examples/wordpress-native-html-base-media-handoff.php` exercises upstream
`Tests.Readers.HTML` base-tag behavior for WordPress import review. It parses
HTML exported with `<base href>` and resolves relative media, relative audit
links, and root-relative media to absolute WordPress URLs before block output.
This covers Data Liberation imports where legacy HTML was exported from a
document package or staging directory and media references must stay attached
without shelling out to Pandoc.

Previous scenario:
`examples/wordpress-markdown-abbrev-handoff.php` exercises upstream
`test/command/md-abbrevs.md` and `data/abbreviations` behavior for WordPress
import review. It parses known unescaped abbreviations before following
letters as nonbreaking groups, preserving `Mr. Bob`, `Dr. Rivera`, and
`e.g. examples` in paragraph output, while escaped source periods such as
`Mr\. Bob` keep ordinary spacing. This covers migration paths where editorial
titles, honorifics, and glossary abbreviations must stay visually grouped in
block-editor review packets without shelling out to Pandoc.

Previous scenario:
`examples/wordpress-markdown-raw-attribute-handoff.php` exercises upstream
`test/command/parse-raw.md`-style Markdown raw-attribute output for WordPress
import review. It parses code-span raw attributes such as `{=latex}` and
`{=html}`, plus fenced raw blocks, through MarkdownReader. Raw HTML is
preserved literally in block output when the source format allowed raw HTML,
while latex/opml-style raw payloads remain visible as `data-pandoc-raw-format`
review spans or code blocks. This covers migration paths where a prior Pandoc
stage or trusted Markdown source carries format-specific raw fragments that
must not silently become ordinary code or literal `{=format}` text.

Previous scenario:
`examples/wordpress-native-docx-table-gridbefore-handoff.php` exercises a
bounded upstream-derived `test/docx/table_gridbefore.native` slice for
WordPress import review. It parses a DOCX Native table packet with scientific
column widths, explicit blank gridBefore/gridAfter cells, spacer rows, and
wide colspans, then renders a WordPress core table with the source blank cells
preserved. The example enables `markEmptyTableCells`, adding
`data-pandoc-empty-cell="true"` markers to nineteen empty table cells so
migration reviewers can distinguish intentional DOCX grid placeholders from
missing data without activating DOCX ZIP/OpenXML package parsing.

Previous scenario:
`examples/wordpress-native-empty-paragraphs-handoff.php` exercises upstream
`test/command/empty_paragraphs.md` semantics for WordPress import review. It
parses a Native packet containing `Para []` separators and renders with
`preserveEmptyParagraphs` enabled, producing four WordPress paragraph blocks
including the two empty `<p></p>` blocks that Pandoc's
`html5+empty_paragraphs` branch preserves. The default WordPress handoff still
drops empty paragraphs like Pandoc `html5`, so migration tooling can opt into
blank paragraph evidence only for source formats or reviewer workflows that
need it.

Previous scenario:
`examples/wordpress-native-odt-multi-header-table-handoff.php` exercises an
upstream-shaped ODT Native multi-header table packet for WordPress import
review. It parses the bounded
`test/odt/native/simpleTableWithMultipleHeaderRows.native` slice through
NativeReader and renders a WordPress table whose two source header rows stay in
`<thead>`, whose three body rows keep the empty cells visible, and whose
default-width ODT columns do not invent a `<colgroup>`. The trailing upstream
empty `Para []` is dropped from block output. This covers spreadsheet-like ODT
imports where source tables carry stacked header bands that must remain
reviewable in the block editor without activating OpenDocument ZIP/XML package
parsing.

Previous scenario:
`examples/wordpress-native-docx-track-changes-decision-handoff.php` exercises
upstream-shaped DOCX Native accepted/rejected insertion and deletion packets
for WordPress import review. It parses the bounded
`test/docx/track_changes_insertion_accept.native`,
`test/docx/track_changes_insertion_reject.native`,
`test/docx/track_changes_deletion_accept.native`, and
`test/docx/track_changes_deletion_reject.native` slices through NativeReader
and renders four reviewer sections. Accepted insertion keeps `two exciting`,
rejected insertion omits those inserted words, accepted deletion omits
`an excessively modified`, and rejected deletion retains that deleted text.
This covers Word/DOCX review handoffs where upstream Pandoc has already
applied an accept/reject choice and WordPress output must not retain stale
`<ins>`/`<del>` review markup.

Previous scenario:
`examples/wordpress-markdown-spanlike-handoff.php` exercises upstream
`test/command/nested-spanlike.md` semantics for WordPress Markdown import
review. It parses `[test]{.foo .underline #bar .smallcaps .kbd}` through
MarkdownReader and renders the upstream HTML-writer wrapper shape
`<kbd id="bar"><u><span class="smallcaps">test</span></u></kbd>`. This covers
keyboard/editorial source markers where Pandoc consumes spanlike marker
classes for HTML output, keeps the source id on the outer wrapper, and avoids
leaking consumed marker classes into WordPress block markup.

Previous scenario:
`examples/wordpress-native-docx-paragraph-change-decision-handoff.php`
exercises upstream-shaped DOCX Native paragraph insertion/deletion
accept/reject packets for WordPress import review. It parses the bounded
`test/docx/paragraph_insertion_deletion_accept.native` and
`test/docx/paragraph_insertion_deletion_reject.native` slices through
NativeReader and renders two reviewer sections: the accepted decision keeps
the source paragraph split as `This is a` then `split Paragraph.`, while the
rejected decision emits `This is a split` then `Paragraph.`. This covers
Word/DOCX review handoffs where upstream Pandoc has already applied an
accept/reject choice and WordPress output must not retain stale paragraph
change metadata.

Previous scenario:
`examples/wordpress-native-docx-track-changes-move-decision-handoff.php`
exercises upstream-shaped DOCX Native moved-text accept/reject packets for
WordPress import review. It parses the bounded
`test/docx/track_changes_move_accept.native` and
`test/docx/track_changes_move_reject.native` slices through NativeReader and
renders two reviewer sections: the accepted decision keeps the moved paragraph
between its surrounding context paragraphs, while the rejected decision leaves
the later context before the moved paragraph. This covers Word/DOCX review
handoffs where upstream Pandoc has already applied an accept/reject choice and
WordPress output must not retain stale insertion/deletion markup.

Previous scenario:
`examples/wordpress-native-docx-overlapping-targets-handoff.php` exercises an
upstream-shaped DOCX Native overlapping-target packet for WordPress import
review. It parses the bounded `test/docx/overlapping_targets.native` slice
through NativeReader and renders two same-fragment links plus the shared empty
`#Fizz` target span marked with `data-pandoc-anchor="empty-target"`. This
covers Word/DOCX handoffs where multiple cross-reference names point at one
target and migration reviewers need the preserved in-page anchor to remain
visible in block output without invoking upstream Pandoc or activating DOCX
ZIP/OpenXML package parsing.

Previous scenario:
`examples/wordpress-native-docx-scrubbed-metadata-handoff.php` exercises an
upstream-shaped DOCX Native scrubbed review metadata packet for WordPress
import review. It parses the bounded
`test/docx/track_changes_scrubbed_metadata.native` slice through NativeReader
and renders author-only deletion, insertion, and comment spans with explicit
missing-date metadata status. This covers Word/DOCX handoffs where upstream
Pandoc scrubbed review dates and migration reviewers still need visible change
and comment boundaries without fake `datetime` values, raw upstream
`author`/`date` attributes, or DOCX ZIP/OpenXML package parsing.

Previous scenario:
`examples/wordpress-native-docx-track-changes-move-handoff.php` exercises an
upstream-shaped DOCX Native moved-text review packet for WordPress import
review. It parses the bounded `test/docx/track_changes_move_all.native` slice
through NativeReader and renders the moved-to and moved-from text as paired
`<ins>`/`<del>` spans with `data-pandoc-change-author`,
`data-pandoc-change-date`, and `datetime` metadata, while avoiding raw upstream
`author` or `date` attributes. This covers Word/DOCX handoffs where migration
reviewers need moved text visible in the block editor without invoking
upstream Pandoc or activating DOCX ZIP/OpenXML package parsing.

Previous scenario:
`examples/wordpress-native-docx-image-textbox-caption-handoff.php` exercises an
upstream-shaped DOCX Native image textbox caption packet for WordPress import
review. It parses the bounded `test/docx/image_with_textbox_caption.native`
slice through NativeReader and renders the captioned EMF image with source
dimensions, `data-pandoc-source-format="emf"`, a figcaption, and caption-derived
image alt text marked with `data-pandoc-alt-source="figure-caption"`. This
covers Word/DOCX handoffs where captions stored in textboxes must remain useful
for media review without pretending the original source supplied image alt text.

Previous scenario:
`examples/wordpress-native-docx-diagram-handoff.php` exercises an
upstream-shaped DOCX Native unsupported diagram packet for WordPress import
review. It parses the bounded `test/docx/diagram.native` slice through
NativeReader and renders the upstream `[DIAGRAM]` placeholder as a visible
review span with `data-pandoc-diagram="unsupported-docx-diagram"`. This covers
Word/DOCX handoffs where SmartArt or diagram content survives as an explicit
review marker instead of becoming an ordinary CSS class span or disappearing
from the block editor.

Previous scenario:
`examples/wordpress-native-jats-figure-alt-handoff.php` exercises an
upstream-shaped JATS/XML Native figure packet for WordPress import review. It
parses the bounded `test/jats-reader.native` slice through NativeReader and
renders a WordPress image block whose nested paragraph Image target becomes
`src="foo.png"` and whose source figure body text becomes
`alt="alternative-decription"`. This covers article/XML import handoffs where
source figure alt text must survive as media metadata instead of becoming a
visible paragraph or an empty placeholder image.

Previous scenario:
`examples/wordpress-native-docx-vml-object-image-handoff.php` exercises an
upstream-shaped DOCX Native VML/object image packet for WordPress import
review. It parses the bounded `test/docx/image_vml_as_object.native` slice
through NativeReader and renders a WordPress image block whose EMF source is
tagged with `data-pandoc-source-format="emf"` while browser-native image
formats remain unflagged. This covers Word/DOCX handoffs where Office vector
or object images need a later media conversion decision without invoking
upstream Pandoc or activating DOCX ZIP/OpenXML package parsing.

Previous scenario:
`examples/wordpress-native-html-row-header-table-handoff.php` exercises an
upstream-shaped HTML-reader Native row-header table packet for WordPress import
review. It parses a bounded `test/html-reader.native` slice through
NativeReader and renders a WordPress core table where `RowHeadColumns 1`
promotes first-column body cells to `<th>` while ordinary data cells and
spanning summary cells stay `<td>`. This covers comparison, glossary, and
audit tables where source row headers must stay navigable in the block editor
without invoking upstream Pandoc at import time.

Previous scenario:
`examples/wordpress-native-odt-nested-list-continuation-handoff.php` exercises
an upstream-shaped ODT Native nested continued-list packet for WordPress import
review. It parses a bounded `test/odt/native/listContinueNumbering2.native`
slice through NativeReader and renders WordPress ordered-list blocks that
preserve split top-level `start` values, nested lower-alpha sublists,
interleaved text paragraphs, and opt-in source list-style/delimiter metadata
while dropping Pandoc's empty `Para []` separators. This covers ODT import
handoffs where legal, policy, or documentation lists continue across prose and
still need nested source marker details visible to block-editor reviewers
without activating OpenDocument ZIP/XML package parsing.

Previous scenario:
`examples/wordpress-native-odt-table-spans-handoff.php` exercises an
upstream-shaped ODT Native table-span packet for WordPress import review. It
parses a bounded `test/odt/native/tableWithSpans.native` slice through
NativeReader and renders a WordPress core table that preserves multi-row table
headers, header/body `rowspan` and `colspan` boundaries, and the combined
row+column body span while dropping Pandoc's trailing empty `Para []` packet.
This covers ODT import handoffs where spreadsheet-like merged cells must remain
reviewable in the block editor without activating OpenDocument ZIP/XML package
parsing.

Previous scenario:
`examples/wordpress-native-epub-default-list-handoff.php` exercises an
upstream-shaped EPUB Native styling packet for WordPress import review. It
parses a bounded `test/epub/formatting.native` slice through NativeReader,
preserves Pandoc's `DefaultStyle`/`DefaultDelim` ordered-list markers through
NativeWriter read-back, and renders the list as a plain WordPress `<ol>`
without inventing a concrete HTML `type` attribute. This covers EPUB handoffs
where source default-list semantics should stay distinct from decimal-list
semantics in reviewer packets without activating EPUB ZIP/package parsing.

Previous scenario:
`examples/wordpress-native-epub-math-handoff.php` exercises an
upstream-shaped EPUB Native MathML packet for WordPress import review. It
parses a bounded `test/epub/features.native` slice through NativeReader and
renders an opt-in metadata review block, the source XHTML marker, source EPUB
section divs, three display math spans, and one inline math span. This covers
EPUB import handoffs where source MathML equations must remain visibly
distinguishable as display or inline math in WordPress without invoking
upstream Pandoc or activating EPUB ZIP/package parsing.

Previous scenario:
`examples/wordpress-native-epub-section-handoff.php` exercises an
upstream-shaped EPUB Native section packet for WordPress import review. It
parses a bounded `test/epub/wasteland.native` slice through NativeReader and
renders an opt-in metadata review block, a cover image block, a source XHTML
marker, and nested section divs whose source ids/classes survive as safe HTML
attributes. This covers EPUB import handoffs where source spine/chapter
boundaries must remain reviewable in WordPress without invoking upstream
Pandoc or activating EPUB ZIP/package parsing.

Previous scenario:
`examples/wordpress-native-odt-reference-anchor-handoff.php` exercises
upstream-shaped ODT Native same-document reference packets for WordPress import
review. It parses the `test/odt/native/referenceToText.native` and
`test/odt/native/referenceToListItem.native` slices through NativeReader and
renders WordPress paragraph/list blocks with valid fragments such as
`#an-anchor` while preserving whitespace-containing source anchors in
`data-pandoc-source-id` and `data-pandoc-source-href`. This covers ODT import
handoffs where source anchors and list-item references must remain reviewable
without emitting invalid whitespace-containing HTML fragment ids.

Previous scenario:
`examples/wordpress-native-odt-list-continuation-handoff.php` exercises an
upstream-shaped ODT Native continued-list packet for WordPress import review.
It parses the `test/odt/native/listContinueNumbering.native` slice through
NativeReader and renders WordPress ordered-list blocks that preserve Pandoc's
continued `start` values while dropping empty Native paragraph separators from
the block output. This covers ODT import handoffs where list numbering should
survive conversion without adding blank paragraph artifacts to the editor.

Previous scenario:
`examples/wordpress-native-docx-table-caption-anchor-handoff.php` exercises an
upstream-shaped DOCX Native table-caption packet for WordPress import review.
It parses the `test/docx/table_captions_with_field.native` slice through
NativeReader and renders WordPress table blocks that keep Word-generated
`_Ref...` caption anchors as inline spans inside figcaptions while preserving
the surrounding "See Table" links. This covers DOCX import handoffs where
table fields and cross-references need stable in-page targets after conversion
to WordPress blocks without invoking upstream Pandoc.

Previous scenario:
`examples/wordpress-native-docx-image-dimensions-handoff.php` exercises an
upstream-shaped DOCX Native image packet for WordPress import review. It parses
the `test/docx/image_no_embed.native` slice through NativeReader and renders a
WordPress image block that keeps the source media target, title, alt text, and
DOCX-derived `width`/`height` attributes visible as `data-pandoc-*` metadata
plus sanitized CSS dimensions. This covers DOCX import handoffs where source
image sizing should remain reviewable without emitting invalid raw HTML
dimension attributes or invoking upstream Pandoc.

Previous scenario:
`examples/wordpress-native-docx-table-header-rowspan-handoff.php` exercises an
upstream-shaped DOCX Native table packet for WordPress import review. It parses
a bounded `test/docx/table_header_rowspan.native` slice through NativeReader
and renders a WordPress core table that keeps scientific DOCX column widths as
`colgroup` percentages while preserving multi-row header structure with
`rowspan`, `colspan`, strong header text, and inherited column alignment. This
covers DOCX import handoffs where Word tables use grouped header rows and
small relative widths that upstream Pandoc emits in scientific notation.

Previous scenario:
`examples/wordpress-native-docx-index-field-handoff.php` exercises
upstream-shaped DOCX Native empty index-field packets for WordPress import
review. It parses `test/docx/empty_field.native` through NativeReader and
renders WordPress paragraphs that keep the source index entry visible as
`data-pandoc-index-entry` while preserving imported links and decoded Haskell
string escapes. This covers DOCX import handoffs where migration reviewers
need source index terms before deciding whether they map to taxonomy terms,
custom fields, editorial notes, or dropped print-only artifacts.

Previous scenario:
`examples/wordpress-native-docx-document-properties-handoff.php` exercises
upstream-shaped DOCX Native document-property packets for WordPress import
review. It parses `test/docx/document-properties.native` through NativeReader
and renders an opt-in WordPress metadata review block that keeps title, author,
custom properties, keyword lists, nested custom maps, and raw HTML metadata
visible while escaping source HTML rather than executing it. This covers DOCX
import handoffs where migration reviewers need document properties and custom
metadata before deciding how they map to post fields, custom fields, taxonomy
terms, or audit notes.

Previous scenario:
`examples/wordpress-native-docx-custom-style-handoff.php` exercises
upstream-shaped DOCX Native custom-style packets for WordPress import review
packets. It parses `test/docx/custom_style.native` through NativeReader and
renders WordPress paragraphs plus a reviewer HTML block that keep Word inline
and block style names visible through `data-pandoc-custom-style` without
emitting raw upstream `custom-style` attributes. This covers DOCX import
handoffs where migration reviewers need to preserve source style boundaries
before deciding whether styles map to blocks, classes, or cleanup rules.

Previous scenario:
`examples/wordpress-native-docx-paragraph-change-handoff.php` exercises
upstream-shaped DOCX Native paragraph insertion/deletion markers for WordPress
import review packets. It parses `test/docx/paragraph_insertion_deletion_all.native`
through NativeReader and renders WordPress paragraphs that keep empty
paragraph-boundary `paragraph-insertion` and `paragraph-deletion` spans visible
through `data-pandoc-paragraph-change`, `data-pandoc-change-*`, and `datetime`
metadata without emitting raw upstream `author` or `date` attributes. This
covers DOCX import handoffs where split/merge paragraph review state needs to
remain inspectable in WordPress without invoking upstream Pandoc.

Previous scenario:
`examples/wordpress-native-docx-raw-openxml-handoff.php` exercises
upstream-shaped DOCX Native raw OpenXML packets for WordPress import review
packets. It parses `test/docx/raw-bookmarks.native` and
`test/docx/raw-blocks.native` shapes through NativeReader and renders
WordPress paragraphs that keep bookmark boundary ids/names as
`data-pandoc-bookmark-*` attributes while rendering RawBlock OpenXML table
fragments as escaped reviewer code blocks. This covers DOCX import handoffs
where anchors and raw table fragments need to stay inspectable in WordPress
without executing or silently dropping source OpenXML.

Previous scenario:
`examples/wordpress-native-docx-review-spans-handoff.php` exercises
upstream-shaped DOCX Native review-span fixtures for WordPress import review
packets. It parses `test/docx/comments.native`,
`test/docx/track_changes_insertion_all.native`, and
`test/docx/track_changes_deletion_all.native` shapes through NativeReader and
renders WordPress paragraph blocks that keep comment ids/authors/dates as
`data-pandoc-comment-*` attributes while rendering tracked insertions and
deletions as `<ins>`/`<del>` with `data-pandoc-change-*` metadata. This covers
DOCX import handoffs where reviewers need comments and tracked edits visible
in WordPress without invoking upstream Pandoc.

Previous scenario:
`examples/wordpress-native-docx-inline-formatting-handoff.php` exercises an
upstream-shaped DOCX Native fixture for WordPress import review packets. It
parses the `Pandoc (Meta {unMeta = fromList []}) [...]` wrapper used by
`test/docx/inline_formatting.native` and renders WordPress paragraph blocks
that preserve emphasis, strong/emphasis nesting, small caps, strikeout,
underline, superscript/subscript, and hard line breaks without invoking
upstream Pandoc. This covers DOCX import handoffs where a PHP migration
pipeline receives deterministic Native packets from earlier tooling and needs
to validate inline formatting before block conversion.

Previous scenario:
`examples/wordpress-native-upstream-structure-handoff.php` exercises an
upstream-shaped Native fixture for WordPress import review packets. It parses
DefinitionList, RawBlock, nested Div, and parenthesized table-section
constructors through `NativeReader` and renders WordPress definition-list HTML,
grouped raw HTML, and a core table block without invoking upstream Pandoc.
This covers Native packets produced from older HTML/DOCX-style imports where
table sections appear as `(TableHead ...)` and `(TableFoot ...)` constructor
arguments.

Previous scenario:
`examples/wordpress-native-string-escape-handoff.php` exercises Native packet
read-back for WordPress import review packets that contain Haskell numeric
escape separators before source IDs. It parses `\160\&42`-style Native strings
through `NativeReader` and renders WordPress paragraphs with the intended
nonbreaking spaces, so migration tooling does not corrupt bibliography years or
batch identifiers while validating a deterministic Native handoff without
invoking upstream Pandoc at import time.

Previous scenario:
`examples/wordpress-native-reader-handoff.php` exercises Native packet
read-back for WordPress import review packets. It emits a standalone Pandoc
Native AST, parses it through `NativeReader`, and renders WordPress heading,
paragraph/link, and table blocks, so migration tooling can validate a
deterministic Native handoff without invoking upstream Pandoc at import time.

Previous scenario:
`examples/wordpress-native-table-handoff.php` exercises Pandoc-style Native
writer output for WordPress import review packets that need deterministic table
structure. It emits a standalone `Pandoc` Native AST with metadata, a
captioned `Table`, column alignment/width specs, row-head columns, spanned
cells, a footer row, and nested block-cell review notes, so migration tooling
can compare table boundaries without invoking upstream Pandoc.

Previous scenario:
`examples/wordpress-native-citation-metadata-handoff.php` exercises bounded
Pandoc Native citation packets from `test/markdown-citations.native`. It reads
a copied upstream-shaped Native fixture and emits WordPress citation spans with
visible citation text plus `data-pandoc-citation-*` metadata for ids, modes,
note numbers, prefixes, suffixes, grouped citation records, non-ASCII ids, and
note-contained citations, so citation-aware migration tooling can keep review
metadata without invoking upstream Pandoc or citeproc.

Previous scenario:
`examples/wordpress-native-citation-figure-handoff.php` exercises Pandoc-style
Native writer output for WordPress import review packets that need media and
citation boundaries. It emits a standalone `Pandoc` Native AST with metadata,
a source-media `Figure` carrying short and long captions plus image attributes,
and a `Cite` node with author-in-text and suppress-author citation records, so
a migration pipeline can capture deterministic figure/citation fixtures without
invoking upstream Pandoc or citeproc.

Previous scenario:
`examples/wordpress-native-review-packet-handoff.php` exercises Pandoc-style
Native writer output for WordPress import review-oracle packets. It emits a
standalone `Pandoc` Native AST with metadata, a source archive link, checklist
blocks, and escaped PHP code-block fixture text, so a migration pipeline can
capture deterministic Native fixtures without invoking upstream Pandoc.

Previous scenario:
`examples/wordpress-plain-template-pipe-partial-fixture-handoff.php` exercises
Pandoc-style PlainText custom-template pipe recursion and `.txt` partial
resolution for WordPress import audit packets. It renders reviewer checks
through `pairs/reverse` before applying a `check-row.txt` partial, chomps and
uppercases owner metadata without blank leakage, romanizes milestone lists, and
keeps the PlainText body from leaking source admin URLs.

Previous scenario: `examples/wordpress-plain-template-brace-partial-handoff.php`
exercises Pandoc-style PlainText custom templates with braced `${...}`
delimiters and indented bare partials for WordPress import audit packets. It
renders reviewer rows from metadata, emits a literal budget dollar with `$$`
plus braced interpolation, nests every line of an indented checklist partial,
and keeps the PlainText body from leaking source admin URLs.

Previous scenario: `examples/wordpress-plain-template-diagnostics-handoff.php`
exercises Pandoc-style PlainText custom-template compile diagnostics for
WordPress import audit packets. It reports a malformed reviewer partial with a
derived partial path and line/column before the source body renders, so failed
notification, excerpt, search, or audit output does not leak source admin URLs.

Previous scenario: `examples/wordpress-plain-template-object-loop-handoff.php`
exercises Pandoc-style PlainText custom-template loops over nested object
fields for WordPress import audit packets. It emits reviewer routing rows from
`audit.reviewers`, resolves nested `it.name` fields, and keeps
PlainText-rendered body text from leaking source admin URLs.

Previous scenario: `examples/wordpress-plain-template-pad-handoff.php`
exercises Pandoc-style PlainText custom-template multiline alignment for
WordPress import audit packets. It emits a fixed-width reviewer table whose
multiline notes stay aligned across rows, missing metadata cells remain visibly
blank but width-preserving, over-wide notes are not truncated, and
PlainText-rendered body text does not leak source admin URLs.

Previous scenario: `examples/wordpress-plain-template-loop-guard-handoff.php`
exercises Pandoc-style PlainText custom-template partial recursion handling
for WordPress import audit packets. It emits the upstream `(loop)` sentinel
when reviewer partials include each other, and still keeps PlainText-rendered
body text from leaking source admin URLs.

Previous scenario: `examples/wordpress-plain-template-final-newline-handoff.php`
exercises Pandoc-style PlainText custom-template scalar newline handling for
WordPress import audit packets. It emits newline-terminated review fields
without adding spurious blank lines, preserves one intentional blank from a
double-newline review field, renders true/false metadata visibly, and keeps
PlainText-rendered body text from leaking source admin URLs.

Previous scenario: `examples/wordpress-plain-template-branching-handoff.php`
exercises Pandoc-style PlainText custom-template branch directives for
WordPress import audit packets. It emits a standalone `$elseif$` escalation
block that selects the workflow queue without adding a blank line before the
selected branch, plus PlainText-rendered body text without leaking source admin
URLs.

Previous scenario: `examples/wordpress-plain-template-nesting-handoff.php`
exercises Pandoc-style PlainText custom-template nesting for WordPress import
audit packets. It emits a `$^$`-nested multiline review description with an
internal blank line, an aligned owner continuation line, an automatically
indented multiline summary variable, a nested multiline legal-hold partial, a
blank-line separated legal-hold conditional, and PlainText-rendered body text
without leaking source admin URLs.

Previous scenario: `examples/wordpress-plain-template-align-handoff.php`
exercises Pandoc-style PlainText custom-template parameterized alignment pipes
for WordPress import audit packets. It emits padded batch metadata, a centered
workflow queue, fixed-width reviewer rows using left/right/center pipes, and
PlainText-rendered body text without leaking source admin URLs.

Previous scenario: `examples/wordpress-plain-numbered-toc-handoff.php`
exercises Pandoc-style PlainText default-template numbered table-of-contents
handoff for WordPress import audit packets. It emits generated source section
numbers in TOC labels, keeps numbered `unlisted` audit headings visible, keeps
unnumbered appendix headings visible without numbering, preserves explicit
legacy section numbers, and leaves body headings plain for reviewer-facing
excerpts.

Earlier scenario: `examples/wordpress-plain-toc-handoff.php` exercises
Pandoc-style PlainText default-template table-of-contents handoff for WordPress
import audit packets. It emits a nested TOC before plain body text, respects a
bounded TOC depth, and strips source edit URLs, generated TOC anchors, source
link attributes, and code ticks from reviewer-facing TOC labels.

Earlier scenario: `examples/wordpress-plain-template-include-handoff.php`
exercises Pandoc-style PlainText template handoff for WordPress import audit
packets. It emits header-includes and include-before reviewer metadata ahead of
plain body text, then emits a metadata-derived include-after footer after the
body. Writer-variable values remain raw template text, while metadata block
values are rendered through PlainText semantics so source edit URLs and code
ticks do not leak into reviewer-facing excerpts.

## Current Native Slice

Native Markdown block reader and WordPress block writer for headings,
paragraphs, Pandoc-style inline emphasis/strong/link/code spans, bullet lists,
ordered lists, nested lists, and definition lists. Code spans now preserve
list-marker-looking text such as `- x` and `#. x` inside imported list items.
Pandoc title-block metadata is now available to WordPress import orchestration:
a leading `%` title block is consumed before body parsing, multiline titles
keep a metadata soft break for exact upstream shape, and semicolon or
line-separated authors are exposed as individual author entries that an import
pipeline can map to post title and review/byline metadata without rendering
the title block as stray body paragraphs.
List parsing now also maps the bounded `test/testsuite.txt` loose-list and
continuation-line shape: blank-separated list items become paragraph-bearing
loose items, tab/space-indented continuation lines stay inside the current
item, and multi-paragraph ordered steps render as multiple paragraphs inside
one WordPress list item.
The same upstream Lists section now contributes fancy ordered-list markers:
parenthesized decimal starts, lower/upper roman numerals, upper/lower alphabetic
markers, and Pandoc autonumbering. The AST keeps marker style/delimiter
metadata and the WordPress writer preserves start values for nested ordered
lists.
The bounded `test/command/tasklist.md` HTML examples are now represented too:
Markdown review checkboxes such as `- [ ]` and `- [x]` become task metadata on
list items, all-task bullet lists render with `class="task-list"`, mixed
task/plain lists stay ordinary lists with checkbox labels only on the task
items, ordered task items keep labels, and loose task items preserve later
paragraphs outside the checkbox label.
The adjacent `markdown-reader-more` consecutive-list boundary is now
represented too: a review handoff can place bullet, decimal, and
one-space-indented lower-alpha queues next to each other, and the WordPress
writer emits separate `<ul>`, decimal `<ol>`, and `type="a"` `<ol>` blocks
instead of nesting the alpha queue under the final decimal item.
Definition lists now cover Pandoc-style loose first definitions, lazy
continuation lines, blank-before-second definitions, and indented continuation
paragraphs, which keeps imported FAQ, glossary, and release-note metadata
grouped under the intended term.
The remaining upstream `Tests.Readers.Markdown` definition-list case is now
covered too: a definition list nested inside an HTML `<div>` becomes a `div`
AST node containing the parsed definition list.
The upstream `test/testsuite.txt` Definition Lists section is now represented
for multiple-block bodies and alternate `~` markers: emphasized terms remain
emphasized, additional indented paragraphs stay in the same definition, deeply
indented lines become code blocks, quoted continuation bodies stay block quotes,
and nested ordered review lists stay under the intended glossary term.
Fenced code blocks map the upstream `test/command/indented-fences.md`
indentation-stripping behavior and render as WordPress code blocks. Block quotes
now map Pandoc's `test/testsuite.txt` block quote section, including quoted
paragraphs, nested quotes, ordered lists, and indented code inside a quote.
Indented code blocks from the `test/testsuite.txt` Code Blocks section now also
preserve blank lines, literal backslashes, and Pandoc's tab-expanded remaining
indentation, which matters for older Markdown exports that used tab-indented PHP
or template snippets instead of fenced code.
Horizontal rules from the `test/testsuite.txt` Code Blocks and Lists sections
now map to `horizontal_rule` AST nodes and WordPress separator blocks. This
keeps archive section breaks while avoiding the common import bug where a spaced
asterisk divider such as `*   *   *   *   *` becomes an empty-looking bullet
list.
Raw HTML blocks from the `test/testsuite.txt` HTML Blocks section now preserve
WordPress import boundaries: nested `<div>` wrappers stay structural, raw
tables remain in a WordPress HTML block while Markdown inside table cells is
interpreted, HTML comments can carry migration audit markers, custom `<hr>`
tags stay raw instead of being normalized into core separators, and tab-indented
HTML snippets remain code blocks.
The two-level nested table shape from
`test/command/nested-table-to-asciidoc-6942.md` now has a WordPress-specific
boundary as well: nested HTML tables become table AST nodes inside table cells
and render as nested table HTML in a core table block, while simple non-nested
raw HTML tables remain raw HTML for reviewer inspection.
The same upstream fixture's third-level nested table case is mapped separately
from Pandoc's AsciiDoc warning behavior: AsciiDoc downgrades because that target
only supports two table levels, but the WordPress writer preserves the full
third-level nested table HTML for migration reviewers.
Structured HTML table imports from `test/tables/nordics.html5` now use the
native table AST when an HTML table exposes `caption`, `colgroup`, `thead`, or
`tfoot` boundaries. This lets WordPress imports preserve caption inline
emphasis, explicit column widths, head/body/foot sections, row-header cells,
soft line breaks, and superscript units while keeping plain non-structured raw
tables on the existing reviewer-inspection HTML path.
Bounded HTML-reader table cases from `test/html-reader.html` now cover inferred
header rows and omitted section end tags: tables whose first row is all `<th>`
cells become WordPress tables with a real `<thead>`, body rows that start with
`<th>` cells keep `rowHeadColumns=1` in the AST and render those cells as
`<th>`, and omitted `</thead>`, `</tbody>`, and `</tfoot>` tags are normalized
into explicit WordPress table sections.
The next HTML-reader table slice now covers upstream colspan/rowspan and
attribute-carrying cases: no-header `colspan` tables parse as native table
nodes instead of raw HTML, headed tables keep `colspan`/`rowspan` metadata, and
Pandoc-style table/section/row/cell attrs are captured in the AST. WordPress
table output preserves table identity attrs and practical cell attrs such as
`abbr`, `valign`, `data-*`, and non-alignment `style` values. The writer now
also emits section and row attrs from the upstream Attributes table, so source
batch classes, `data-part` markers, and foot-row review color markers survive
in WordPress table markup.
The upstream empty-table case is now mapped as well: legacy HTML table shells
with no cells are consumed and omitted instead of becoming empty WordPress
table blocks or raw HTML review blocks.
The upstream multiple-`tbody` HTML-reader cases are now mapped too: segmented
legacy tables keep each body group as a separate `table_body` AST node and the
WordPress writer emits one `<tbody>` per group instead of flattening review
batches into a single body.
The second upstream multiple-`tbody` case also keeps block-level paragraph
content inside a table cell: a direct `<p>` cell becomes a paragraph block
child, so WordPress emits `<td><p>...</p></td>` instead of flattening the cell
to inline text.
The plain `Tables without Headers` cases from `test/html-reader.html` are now
bounded too: td-only body tables, omitted-`tbody` tables, empty-head tables, and
explicit body-plus-foot tables become native headerless table blocks when cell
content is plain scalar text, while Markdown-looking legacy review tables stay
on the raw HTML path for reviewer inspection.
The remaining bounded table-body header-row shapes from `test/html-reader.html`
are now represented as well: leading all-`th` rows inside a `tbody` are kept as
body-local table head rows instead of being flattened into ordinary body rows or
promoted to a top-level `thead`. WordPress output preserves those rows inside
the same `tbody` before the ordinary review rows.
The next bounded non-table HTML-reader paragraph slice is represented too:
standalone HTML paragraphs can now carry Pandoc-style hard line breaks and
inline `<q>` quote semantics through the native AST. Citation metadata from
`<q cite="...">` is kept on a span child and rendered into WordPress-safe inline
HTML, so imported review quotes keep their source URL without invoking Pandoc.
The next HTML-reader inline style slice is now represented as well:
`font-variant: small-caps` spans, `<u>`, `<ins>`, `<s>`, `<strike>`, and
`<del>` map to native inline nodes before WordPress output. This keeps
source-glossary labels, underlined reviewer notes, inserted text, and deleted
legacy-caption markers semantic instead of flattening them to plain text.
The next HTML-reader code-block slice is now represented too: standalone
`<pre><code>` blocks from legacy HTML exports become native `code_block` nodes
instead of paragraphs or raw HTML. Blank lines, indentation, and literal
backslash escapes remain intact, and `language-*` classes render as WordPress
code block language classes for reviewer-friendly migration snippets.
The bounded HTML-reader blockquote container slice is now represented as well:
balanced `<blockquote>` blocks become native quote nodes, nested quotes remain
nested, code blocks and ordered lists inside quotes stay as block children, and
HTML text inside those quote containers keeps HTML-reader apostrophes rather
than receiving Markdown smart punctuation.
The bounded HTML-reader top-level list slice is now represented too: imported
`<ul>` and `<ol>` blocks become native list nodes, tight list items stay inline,
paragraph-wrapped list items stay paragraph-wrapped, multi-paragraph ordered
items stay attached to one item, and ordered-list `type`, class, and
`list-style` metadata render as safe WordPress ordered-list `type` attributes.
The next HTML-reader nested-list slice is now represented as well: HTML
headings around imported list sections keep generated or explicit anchors,
nested `<ul>` audit checklists stay tight when they only contain text plus a
nested list, paragraph-bearing source queues stay loose, and nested decimal,
roman, and alphabetic ordered-list styles render with WordPress-safe
`start`/`type` attributes.
The initial HTML-reader Inline Markup slice is now represented too: ordinary
HTML `<em>` and `<strong>` spans stay semantic, empty strong/emphasis markers
are preserved as empty inline nodes, emphasized links stay nested under the
emphasis node, and the upstream implicit paragraph close before a following
`<p>` no longer swallows the next paragraph.
The remaining bounded HTML-reader Inline Markup nested/code slice is now
represented too: nested `<strong><em>...</em></strong>` source emphasis stays
nested in the AST and WordPress output, and HTML `<code>` spans preserve
literal reviewer/source tokens such as `>`, `$`, `\`, `\$`, and `<html>`
without becoming raw HTML or Markdown code-span re-parses.
The bounded HTML-reader Smart quotes, ellipses, dashes slice is now represented
too: bare self-closing `<hr />` separators become WordPress separator blocks on
the HTML-reader path, while straight quotes, source apostrophes, quoted
HTML code/link punctuation, dash strings, numeric hyphen ranges, and spaced
ellipsis dots stay literal instead of receiving Markdown smart-punctuation
rewrites.
The bounded HTML-reader LaTeX slice is now represented too: source TeX commands,
dollar-delimited math-looking strings, and one-line tabular fragments inside
HTML text stay literal on the HTML-reader path, while explicit HTML `<code>` and
`<em>` markup remains semantic. This keeps legacy source snippets reviewable
without incorrectly turning imported HTML into Markdown math or raw TeX spans.
The bounded HTML-reader Special Characters slice is now represented too:
Unicode list text, decoded entities, comparison punctuation, and
Markdown-sensitive punctuation tokens from imported HTML stay literal on the
HTML-reader path. This prevents legacy source snippets like `*`, `_`, `[`, `]`,
`#`, or comparison operators from turning into Markdown markup while still
escaping them safely for WordPress output.
The bounded HTML-reader Links slice is now represented too: explicit HTML
anchors preserve href/title metadata, empty links remain empty placeholders,
reference-looking text stays literal, and code contexts do not autolink.
The bounded HTML-reader Images slice is now represented too: HTML `<img>` nodes
become native image inline nodes with source/title/alt metadata, standalone
image-only paragraphs keep Pandoc's paragraph-image AST shape, and WordPress
output promotes those standalone images into image blocks while preserving
inline images inside paragraph copy.
The bounded HTML-reader Footnotes slice is now represented too:
footnote-looking HTML anchors remain ordinary `link` nodes, note/back-reference
paragraphs and pre/code continuation blocks stay as normal blocks, invalid
space-containing footnote markers remain literal text, and leading/trailing
spaces around HTML emphasis wrappers move outside the emphasis node to match
Pandoc's native AST shape.
The PlainText default-template table-of-contents slice is now represented too:
WordPress import audit packets can emit a TOC before body text when a plain
template requests one, nested headings are bounded by `tocDepth`, source edit
links and generated `toc-*` anchors are stripped from labels, code spans lose
backticks, and unlisted private headings stay out of the reviewer-facing TOC
unless a later numbering slice explicitly maps numbered unlisted behavior.
The bounded early HTML-reader full-document slice is now represented too:
complete `<html>` exports keep title/generator metadata on the document AST,
the source title heading keeps its generated id and `class="title"` marker in
WordPress heading output, heading links/emphasis stay semantic, and
HTML-reader paragraphs keep `*` list-marker-looking text literal instead of
falling back through Markdown parsing.
The upstream `test/testsuite.txt` Inline Markup section is now represented for
underscore emphasis/strong and triple-marker nesting: `_import note_` stays
emphasized, `__review flag__` stays strong, and `___urgent media cleanup___`
renders as nested strong emphasis in WordPress block HTML.
The adjacent `Tests.Readers.Markdown` intraword underscore and raw-LaTeX URL
guard cases are now represented too: filename-style reviewer markers such as
`_foot_ball_` preserve the inner underscore inside one emphasized span, while
an incomplete pasted `\begin` source command remains literal text instead of
becoming raw TeX.
The adjacent `Tests.Readers.Markdown` emph-with-strong delimiter cases are now
represented too: reviewer notes like `*x **xx** x*` and `***a**b **c**d*`
render as outer emphasis containing nested strong spans, matching Pandoc's
reader boundary instead of splitting the paragraph at the first inner `**`
delimiter run.
The adjacent alternating emph/strong softbreak case is now represented too:
multi-line reviewer notes keep the physical Markdown paragraph line break as a
softbreak between repeated emphasis and strong-emphasis runs, so WordPress
handoff HTML preserves reviewer line boundaries without splitting the paragraph.
The remaining bounded Inline Markup script/deletion cases are also mapped:
`~~legacy cleanup~~` renders as deletion markup, `a^*draft*^` renders as a
superscript containing emphasis, and `H~2~O` renders as subscript text while
Pandoc's unescaped-space examples stay plain text.
The adjacent MultiMarkdown short script cases are represented too: compact
reviewer annotations such as `O~2` and `x^2` render as subscript/superscript
when followed by spaces, punctuation, or emphasis, while no-nesting forms keep
the marker literal before ordinary emphasis.
The adjacent citation boundary cases are represented too: reviewer notes can
preserve bare Pandoc citations such as `@cita [review-only note]` while still
keeping following footnotes, inline links, reference links, shortcut reference
links, and implicit header links separate when those brackets are real links.
The adjacent figure attribute case is represented too: immediate image
attributes keep `latex-placement` on the standalone figure and use `alt` as the
image alt override without replacing the reviewer-visible caption.
The bounded Smart quotes, ellipses, dashes section is now mapped too: nested
single and double quote spans render as typographic quotes, contractions and
date possessives keep Pandoc's right-apostrophe behavior, quoted code and
one-line reference links stay semantic, `---` becomes an em dash, numeric `--`
ranges become en dashes, and `...` becomes an ellipsis.
The adjacent smart-punctuation unclosed quote case is now represented too:
bold reviewer notes such as `**this should "be bold**` stay strong while the
unmatched opening quote becomes a left double quote in WordPress output.
The adjacent inline-note quote cases from `Tests.Readers.Markdown` are now
represented too: reviewer text such as `'a^['source quote'.] c.'` and
`"a^["review quote".] c."` keeps the outer quote open across the inline note,
while the note body parses its own nested smart quote. WordPress output keeps
the reviewer sentence quoted and emits the note bodies as normal endnotes.
The remaining `Tests.Readers.Markdown` smart-punctuation edge cases are now
represented too: quoted leading ellipses render as smart quoted ellipsis text,
apostrophes before an emphasized French helper phrase stay right apostrophes
instead of opening quotes, and French guillemet-adjacent apostrophes survive in
reviewer notes with Unicode-aware word-boundary handling.
The bounded LaTeX section is now mapped for import-safe preservation: raw TeX
citations render as escaped inline TeX spans, `$...$` and `$$...$$` math render
as WordPress-safe math spans, currency-like dollar examples and escaped dollars
stay plain text, and raw `tabular` blocks render as escaped TeX code blocks
instead of shelling out to Pandoc.
The adjacent `markdown-reader-more` `$ in math` slice is now represented too:
TeX text-group dollars inside `\text{the $n$th root of $y$}` stay inside one
math span, so reviewer formulas do not split into multiple inline math nodes
or stray paragraph text during WordPress handoff.
The adjacent `markdown-reader-more` raw-HTML-before-header and commented-list
slice is now represented too: empty source anchors immediately before imported
headings stay as raw inline HTML boundaries, trailing-space horizontal rules
stay separators, and commented-out list markers remain attached to list-item
text instead of ending the review checklist.
The bounded Special Characters section is now mapped for import-safe text
round-tripping: Unicode text stays literal, `AT&amp;T` decodes once before the
WordPress writer escapes output, literal comparison characters stay text, and
Pandoc's punctuation backslash escapes collapse to visible characters without
starting emphasis, links, headings, block quotes, or lists.
The bounded Links section is now mapped for import-safe link preservation:
explicit links keep empty destinations, pointy-brace destinations, and
double/single-quoted titles; reference links keep collapsed and shortcut
forms, nested brackets in link text, and up-to-three-space reference
definitions; ampersands stay intact in URLs, link text, and titles; URI and
email autolinks work inside paragraphs, lists, and quotes; and code spans or
indented code blocks keep angle-bracket URLs as literal code.
The `test/markdown-reader-more.txt` URL-space cases are now represented too:
reference definitions may put the URL and title on following lines, and bare
link destinations with spaces are collapsed and percent-encoded as `%20` while
keeping trailing quoted or parenthesized titles attached.
The same upstream fixture's implicit header reference cases are now represented
too: Markdown headings generate Pandoc-style anchors, duplicate generated ids
receive suffixes, shortcut/collapsed/case-insensitive references resolve to the
first matching heading, explicit `{#id .class key="val"}` attributes are kept on
the heading AST, and explicit reference definitions override implicit heading
targets.
The mid-fixture case-insensitive reference and curly-quote literal cases are
represented too: reviewer shortcuts such as `[FUM]` resolve to `[fum]: /fum`,
while pasted curly quote glyphs stay literal WordPress text rather than being
reinterpreted as Markdown smart quote delimiters.
The adjacent `test/markdown-reader-more.txt` backslash-newline and code-span
cases are now represented too: an explicit trailing backslash before a newline
becomes a hard `linebreak` node, code spans preserve literal trailing
backslashes, multiline code spans normalize their internal newline to a single
space, longer backtick delimiters can contain literal backtick runs, and blank
lines terminate an otherwise unterminated code span as ordinary paragraph text.
The WordPress fixture uses that path for reviewer handoff text that needs a
visible `<br/>` plus a normalized inline source token.
The focused `Tests.Readers.Markdown` inline-code attribute cases are now
represented too: immediate attributes attach to code nodes, while spaced
attribute-looking text remains literal. The WordPress fixture uses this path for
reviewer/source tokens such as `wp_enqueue_script` that need stable id, class,
data, and title metadata without shelling out to Pandoc.
The focused `Tests.Readers.Markdown` autolink attribute cases are now
represented too: immediate attributes attach to autolink nodes, while spaced
attribute-looking text remains literal. The WordPress fixture uses this path for
reviewer source links that need stable id, class, data, and title metadata
without changing ordinary autolink markup.
The focused `Tests.Readers.Markdown` bare URI autolink extension cases are now
represented too: all 41 upstream `bareLinkTests` cases now have local PHP
coverage. Plain http(s), DOI, Git, file, and mailto source URLs become links,
trailing sentence punctuation remains outside the anchor, balanced parentheses
remain inside the destination, uppercase schemes are accepted, bracketed path
text keeps a safe percent-encoded destination, raw HTML anchors pass through
without nested autolinking, and Greek, long encoded, port, tilde, `%20`, and
at-sign path variants stay intact. The WordPress fixture uses this path for
legacy import notes where reviewers pasted source URLs without angle brackets
or Markdown link syntax.
The focused `Tests.Readers.Markdown` no-links-inside-link-label cases are now
represented too: autolink-looking source URLs, nested Markdown link syntax, and
bare URI-looking text remain literal inside the outer reviewer link label. The
WordPress fixture uses this path when import notes need the visible source
notation to stay reviewable without producing nested anchors.
The focused `Tests.Readers.Markdown` raw HTML regression cases are now
represented too: a block-start `<del>test</del>` becomes a raw-open, plain,
raw-close block sequence, invalid tags stay literal, technically invalid
comments stay raw HTML, and split angle-bracket text stays in separate
paragraphs. The WordPress fixture uses this path for legacy raw deletion
boundaries that should not be flattened into visible tag text.
The adjacent GitHub-flavored raw email, emoji, and wiki-link extension cases
are now represented too: `**@user**` remains strong text instead of becoming
link syntax, `:smile:` and `:+1:` become Pandoc-style emoji spans with
`class="emoji"` and `data-emoji` metadata, and `[[title|target]]` wiki links
become classed links with literal label text. The WordPress fixture uses this
path for reviewer reaction shortcodes and legacy wiki shortcuts that should
stay visible without importing external media assets or creating nested inline
markup inside the wiki label.
The next adjacent `test/markdown-reader-more.txt` multilingual URL and
numbered-example cases are now represented too: Unicode URI autolinks, Unicode
inline link destinations, and Unicode e-mail autolinks stay clickable, while
`(@)`/`(@label)` example markers become Pandoc Example-style ordered lists and
inline `(@label)` references render as visible example numbers. The WordPress
fixture uses this path for multilingual source audit contacts and numbered
reviewer handoff steps without shelling out to Pandoc.
The adjacent line-block case from `test/markdown-reader-more.txt` is now
represented as well: pipe-prefixed line blocks become `line_block` AST nodes,
leading spaces after `|` become nonbreaking indentation, blank line-block
entries are preserved, and indented continuation lines fold into the previous
line. The WordPress fixture uses this path for source stanzas and reviewer
handoff text where line boundaries must survive block conversion.
The adjacent indented-code-at-beginning-of-list case from
`test/markdown-reader-more.txt` is now represented as well: list items whose
marker is followed by five spaces start with native `code_block` children,
nested ordered and bullet review queues preserve their code snippets, and the
four-space `-    no code` guard remains ordinary reviewer prose.
The bounded Images section is now mapped for import-safe media preservation:
standalone reference images become WordPress image blocks with caption/title
metadata, and inline image spans remain inside paragraph text with escaped alt
and title attributes.
The bounded Footnotes section is now mapped for import-safe note preservation:
reference footnotes are collected from anywhere in the document and rendered at
the reference point as `note` AST nodes, inline notes handle nested emphasis,
links, code spans containing `]`, and bracketed text, quote/list-contained
notes stay attached to their parent blocks, multi-block note definitions retain
paragraph and code-block bodies, and recursive note references inside note
bodies remain literal text instead of expanding forever.
The bounded `test/pipe-tables.txt` pipe-table fixture is now fully represented
for import-safe batch summaries: captioned and no-caption tables preserve their
captions and left/right/center/default alignment metadata, headerless,
header-less one-column, side-less, indented-left-column, one-column, and
no-body forms retain the expected table head/body shape, relative column-width
metadata stays on the AST, and cells containing escaped pipes or code-span pipes
stay in the intended cell. The WordPress writer renders these as core table
blocks with escaped inline emphasis, code spans, links, caption inline markup,
and optional `<colgroup>` width styles.
All seven gridless simple/multiline table cases from `test/tables.markdown` are
now mapped for older Markdown exports: captioned and uncaptioned simple tables
infer Pandoc-style alignment from header spacing, the two-space-indented table
shape is recognized before indented-code parsing, no-column-header simple
tables use opening and closing delimiter rows, multiline header/body rows keep
wrapped lines as soft breaks inside cells, 80-column `ColWidth` fractions render
as WordPress `<colgroup>` widths, and the headed-vs-headerless final-column
alignment distinction is preserved.
The upstream `test/command/short-caption.md` fixture is now represented for a
narrow LaTeX table slice: optional short captions are kept separately from the
visible long caption on the AST, and the WordPress table figure preserves the
short label in `data-pandoc-short-caption` for reviewer handoff, search, or
later export tooling.
The upstream `test/command/table-with-cell-align.md` and
`test/command/table-with-column-span.md` fixtures are now represented for a
narrow DocBook table slice: `informaltable` fragments keep colspec widths,
per-cell left/right/center/default alignment, strong emphasis inside cells, and
colspan metadata. The WordPress table writer preserves those as core table
markup with safe `style` and `colspan` attributes.
The upstream `test/command/rst-writer-gridtable-if-rowspans.md` row-span shape
is now represented as well: DocBook `morerows` imports become AST row spans,
table head/body/foot sections remain distinct, and WordPress table output keeps
`rowspan` plus `<tfoot>` markup for reviewer-audit tables.

## Scenario Fixture

- `fixtures/wordpress-import-markdown.md` is a small Data Liberation import
  sample with editorial emphasis, a source archive link, visible shortcode-like
  code spans, a reviewer quote, conversion steps with a multi-paragraph
  reviewer follow-up item, parenthesized source-ID steps with nested roman
  reviewer checkpoints, definition-list import notes, an alternate-marker source
  glossary with nested ordered review tasks, a div-wrapped glossary audit note,
  underscore-delimited reviewer emphasis, nested urgent cleanup emphasis,
  unclosed bold quote audit text, strikeout cleanup notes, superscript draft
  status, subscript chemical/media labels, short O~2/x^2 reviewer annotations,
  smart import-editor quotes, apostrophes, ellipses, date-range en
  dashes, em-dash review notes, HTML entity text that must not double-escape,
  literal comparison characters, reference audit links with WordPress edit-link
  titles, spaced media/manifest URLs that must be `%20`-encoded, autolinked
  audit URLs, importer email contacts, a standalone referenced release image, a
  latex-placement reviewer gallery figure with an imported alt override, an
  inline thumbnail image, reference and inline footnotes for source audit
  trails, raw TeX citations, inline/display math notes, nested TeX text math
  with literal dollars, a raw TeX table source block, and a fenced PHP
  migration snippet.
- The fixture also includes a raw import table, an HTML migration audit comment,
  and a custom legacy divider to exercise WordPress HTML block output for
  imported raw HTML boundaries.
- The fixture now includes multilingual Markdown source audit links and
  Pandoc-style numbered examples, exercising Unicode URI/e-mail autolinks plus
  `(@label)` example references in WordPress reviewer handoff text.
- The fixture now includes an attributed inline code source token, exercising
  Pandoc-compatible code attrs and WordPress-safe inline `<code>` id/class/data
  attributes for migration review tooling.
- The fixture now includes an attributed autolink source token, exercising
  Pandoc-compatible autolink attrs and WordPress-safe link id/class/data/title
  attributes for migration review tooling.
- The fixture now includes bare source URL audit notes, exercising
  Pandoc-compatible bare URI autolinks with trailing punctuation and balanced
  parenthesized media paths for pasted migration references.
- The fixture now includes extended bare source URL audit notes, exercising
  Greek source URLs, `%20` paths, and at-sign archive paths from the upstream
  bare URI family.
- The fixture now includes a character-reference audit note, exercising
  Pandoc-compatible named, decimal, and hexadecimal entity decoding in
  paragraph text and link titles before WordPress escaping.
- The fixture now includes link-label boundary audit notes, exercising Pandoc's
  rule that link-looking syntax remains literal inside an ordinary link label
  instead of creating nested anchors.
- The fixture now includes a raw Markdown HTML deletion-boundary audit note,
  exercising Pandoc's raw-open/plain/raw-close handling for block-start
  `<del>...</del>` imports.
- The fixture now includes a reviewer emoji shortcode audit note, exercising
  GitHub-flavored Pandoc emoji span output for `:smile:` and `:+1:` without
  shelling out to Pandoc or importing external assets.
- The fixture now includes compact short script annotations, exercising
  Pandoc's MultiMarkdown short subscript/superscript delimiter behavior for
  reviewer notes such as `O~2` and `x^2`.
- The fixture now includes a multi-line softbreak emphasis note, exercising
  Pandoc's alternating emph/strong paragraph case while keeping the reviewer
  note in one WordPress paragraph.
- The fixture now includes an indented list code handoff, exercising Pandoc's
  five-space list-marker code-block rule for migration snippets while keeping a
  four-space nested reviewer note as prose.
- The fixture now includes a citation boundary audit note, exercising Pandoc's
  bare citation suffix behavior while keeping a following reviewer source link
  as an ordinary WordPress link.
- The fixture now includes a latex-placement reviewer image figure, exercising
  Pandoc's immediate image attribute behavior and WordPress-safe
  `data-pandoc-latex-placement` output.
- The fixture now includes a Pandoc-style line block, exercising source stanza
  boundaries, nonbreaking indentation, and continuation-line preservation in
  WordPress paragraph output.
- The fixture now includes empty legacy HTML table shells, documenting the
  upstream-aligned import policy to omit tables with no cells.
- The fixture now includes a nested legacy HTML audit table to exercise nested
  table-cell block children and WordPress nested table rendering.
- The fixture now also includes a third-level nested legacy HTML audit table,
  documenting the WordPress-specific policy to preserve deep review matrices
  rather than applying Pandoc's AsciiDoc-only two-level table downgrade.
- The fixture now includes a structured HTML import table based on the upstream
  `test/tables/nordics.html5` shape, exercising caption emphasis, colgroup
  widths, thead/tbody/tfoot section preservation, row-header cells, soft line
  breaks, and superscript units in WordPress table block output.
- The fixture now includes a segmented HTML import table based on the upstream
  multiple-`tbody` reader cases, exercising separate body groups for published
  and media-review batches, section and row metadata attrs, plus
  paragraph-bearing table cells in WordPress table block output.
- The fixture now includes a plain td-only HTML reader import table, exercising
  the upstream headerless table body path without changing Markdown-looking raw
  review tables.
- The fixture now includes a body-headed HTML reader import table, exercising
  upstream body-local `TableBody` head rows for migration review queues that
  carry headers inside `tbody` plus a table footer.
- The fixture now includes an HTML reader quote import paragraph with a
  citation-bearing `<q>` and a hard `<br />` line break, exercising non-table
  HTML reader inline semantics for migration reviewer source notes.
- The fixture now includes a legacy HTML `<pre><code class="language-php">`
  snippet, exercising upstream HTML-reader code-block behavior and WordPress
  code block language output without shelling out to Pandoc.
- The fixture now includes a legacy HTML `<blockquote>` import note containing
  a PHP code block, ordered checklist, and nested approval quote, exercising
  upstream HTML-reader quote container behavior and WordPress quote block
  output without shelling out to Pandoc.
- The fixture now includes top-level HTML reader list imports, exercising a
  reviewer checklist `<ul>` with nested media-review bullets plus a roman
  ordered review queue that preserves start/style metadata in WordPress list
  output without shelling out to Pandoc.
- The fixture now includes nested/fancy HTML reader list imports, exercising a
  heading-anchored source checklist, a three-level nested unordered audit list,
  paragraph-bearing ordered items, and nested decimal, roman, and alphabetic
  review queues without shelling out to Pandoc.
- The fixture now includes an HTML reader definition-list import, exercising
  glossary/FAQ `<dl>` content with multiple definitions and consecutive term
  aliases that need to stay grouped in WordPress output without shelling out to
  Pandoc.
- The fixture now includes an HTML reader inline-markup import, exercising
  empty strong/emphasis markers and an emphasized WordPress edit link after an
  implicitly closed paragraph without shelling out to Pandoc.
- The fixture now includes nested HTML reader strong/emphasis review text and
  HTML `<code>` source tokens, exercising preservation of urgent review marks,
  block-comment source snippets, PHP variable names, and literal dollar escapes
  without shelling out to Pandoc.
- The fixture now includes HTML reader special-character import text, exercising
  Unicode list items, entity-decoded organization names, comparison operators,
  and Markdown-sensitive punctuation tokens that must remain literal text in
  WordPress output without shelling out to Pandoc.
- The fixture now includes a complete HTML reader document export, exercising
  title/generator metadata capture, source title-heading class preservation,
  generated heading ids, and literal HTML-reader paragraph handling without
  shelling out to Pandoc.
- The fixture now includes pipe-table import metrics and relative-width review
  note summaries with aligned numeric counts, emphasized status text, code
  spans, a caption with a reference link and code span, and colgroup widths,
  exercising the native table AST and WordPress table block writer.
- The fixture now also includes legacy simple-table source totals with a
  caption, plus a wrapped multiline review-note table with colgroup widths,
  exercising gridless table imports from older Pandoc-compatible exports that
  do not use pipe-table syntax.
- The fixture now includes a Markdown grid-table span import queue based on the
  upstream row/column-span shape, exercising colspan and rowspan preservation in
  WordPress table block output without shelling out to Pandoc.
- The fixture now includes a short-caption LaTeX table import that keeps a
  compact reviewer label (`Batch 42`) while rendering the longer handoff
  caption in the WordPress table figcaption.
- `fixtures/wordpress-docbook-table.xml` is a bounded DocBook import-audit
  table with a spanned strong batch heading, aligned status cells, proportional
  colspec widths, spanned remediation summary cells, and a row-spanned media
  review window plus a footer reminder.
- `examples/wordpress-import-markdown.php` converts
  `fixtures/wordpress-import-markdown.md` to WordPress block comments and HTML
  without shelling out to pandoc.
- `examples/wordpress-docbook-table-spans.php` converts the DocBook table
  fixture into WordPress table block HTML without shelling out to pandoc.
- Definition-list support maps Pandoc `Tests.Readers.Markdown` glossary-style
  cases into `<dl>` output inside a WordPress HTML block, which is useful for
  imported FAQs, term lists, release-note metadata, and migration checklists.
- Div-wrapped definition lists preserve legacy import wrappers around glossary
  or FAQ notes as a WordPress HTML block instead of flattening the wrapper into
  text.
- Quote support maps imported reviewer notes, citations, and legacy editorial
  callouts into core WordPress quote blocks instead of flattening them into
  paragraphs.
- Loose ordered-list support keeps a reviewer follow-up paragraph attached to
  the same conversion step instead of emitting a separate paragraph outside the
  list.
- Fancy ordered-list support keeps imported source-ID sequences and nested
  roman reviewer checkpoints grouped as ordered WordPress list markup with the
  correct `start` values.
- Alternate definition-marker support keeps older Pandoc-style `~` glossary
  notes and their nested ordered review tasks inside one WordPress HTML `<dl>`
  block.
- Tab-indented legacy snippets render as core WordPress code blocks with the
  remaining tab indentation expanded to spaces, matching Pandoc's native AST.
- Spaced-asterisk and underscore section dividers render as WordPress separator
  blocks, preserving migration-era article breaks without turning them into list
  markup.
- Raw HTML tables, comments, and custom dividers render inside WordPress HTML
  blocks without shelling out to Pandoc, preserving legacy import annotations
  and table markup that reviewers may need to inspect.
- Raw Markdown HTML deletion boundaries now preserve Pandoc's block boundary:
  the opening and closing `<del>` tags stay raw HTML while the contained text
  renders as ordinary WordPress paragraph content, avoiding literal visible tag
  text in migrated review notes.
- GitHub-flavored Pandoc emoji aliases now render as safe inline WordPress
  spans with `class="emoji"` and `data-emoji` metadata for reviewer reaction
  notes, while unsupported aliases remain literal source text.
- Empty legacy HTML table shells are omitted without shelling out to Pandoc,
  avoiding empty WordPress table blocks in migrated content.
- Nested legacy HTML audit tables render as nested table HTML inside the
  containing WordPress table block, preserving old reviewer matrices that used
  inner tables for grouped import status.
- Third-level nested legacy audit tables are preserved as nested WordPress
  table HTML, making the migration policy explicit for source documents that
  would trigger Pandoc's AsciiDoc depth warning.
- Structured HTML import tables render as core WordPress table blocks with
  preserved `<thead>`, `<tbody>`, `<tfoot>`, `<colgroup>`, caption inline
  markup, row-header `<th>` cell treatment, inferred header rows, omitted
  section-end normalization, and superscript units without invoking Pandoc.
- HTML reader table Attributes imports render as core WordPress table blocks
  with preserved table ids, section classes/data attributes, row
  classes/data/bgcolor attributes, and practical cell attrs without invoking
  Pandoc.
- HTML reader quote/cite paragraphs render as WordPress paragraph blocks with
  Pandoc-style typographic quotes, preserved citation metadata, and hard
  `<br/>` line breaks without invoking Pandoc.
- HTML reader blockquote containers render as core WordPress quote blocks while
  preserving nested quote structure, embedded code blocks, and ordered review
  checklists without invoking Pandoc.
- HTML reader top-level lists render as core WordPress list blocks while
  preserving nested media-review bullets, paragraph-bearing ordered items,
  start values, and roman ordered-list style metadata without invoking Pandoc.
- HTML reader nested/fancy lists render as core WordPress heading and list
  blocks while preserving generated heading anchors, tight nested checklist
  items, paragraph continuations, decimal starts, and nested roman/alpha queue
  styles without invoking Pandoc.
- HTML reader definition lists render as WordPress-safe glossary/FAQ `<dl>`
  markup while preserving consecutive `<dt>` aliases and multiple `<dd>` bodies
  without invoking Pandoc.
- HTML reader inline emphasis/strong markup renders as normal WordPress inline
  HTML, preserving empty source markers and emphasized edit links without
  invoking Pandoc.
- HTML reader literal punctuation imports render as source-preserving WordPress
  paragraphs and separator blocks: straight quotes, apostrophes, quoted
  code/link punctuation, dash strings, hyphen ranges, and spaced ellipses stay
  literal instead of receiving Markdown smart punctuation.
- HTML reader LaTeX-looking source imports render as ordinary WordPress text and
  list markup: `\cite`, `$x \in y$`, and one-line `\begin{tabular}` fragments
  remain literal reviewer source instead of becoming math spans or raw-TeX
  preservation spans.
- HTML reader special-character imports render as ordinary WordPress-safe text,
  list, and separator markup: Unicode source text, decoded `AT&amp;T` entities,
  comparison operators, and punctuation tokens such as `*`, `_`, `[`, `]`, and
  `#` stay literal instead of becoming Markdown syntax.
- HTML reader link imports render as WordPress-safe paragraph links while
  preserving empty `href` placeholders, decoded title entities, ampersand URLs,
  and reference-looking source text such as `[legacy-source]` as literal HTML
  reader content instead of Markdown references. Bare source text immediately
  followed by a `<p>` or `<blockquote>` starts its own paragraph, matching the
  upstream Links fixture's mixed HTML flow shape.
- HTML reader image imports render standalone image-only paragraphs as core
  WordPress image blocks with preserved `src`, `alt`, `title`, and caption
  text, while inline `<img>` nodes remain inside normal paragraph copy for
  reviewer context. This maps the upstream HTML-reader Images fixture without
  invoking Pandoc or treating imported HTML as Markdown image syntax.
- HTML reader footnote exports render footnote-looking anchors as ordinary
  WordPress links, not native Markdown notes, matching the upstream HTML reader
  fixture. Continuation pre/code blocks remain code blocks, and boundary spaces
  around emphasis are normalized outside `<em>` so reviewer copy round-trips
  like Pandoc's native AST.
- Full HTML document exports preserve document title/generator metadata and
  title-heading classes while rendering body content as normal WordPress blocks,
  keeping legacy exporter context available for review without invoking Pandoc.
- Segmented HTML import tables preserve multiple `<tbody>` groups without
  invoking Pandoc, keeping source batches visually grouped for reviewer scans
  with body and row metadata attrs intact.
- Paragraph-bearing cells inside segmented HTML import tables stay as block
  paragraphs inside their table cells without invoking Pandoc.
- Plain headerless HTML reader tables render as core WordPress table blocks
  when the cells contain scalar review data rather than Markdown audit markup.
- Underscore emphasis and nested strong-emphasis render as normal WordPress
  inline HTML, preserving reviewer urgency markers from older Pandoc-compatible
  Markdown exports.
- Strikeout, superscript, and subscript render as normal WordPress inline HTML,
  preserving cleanup annotations and compact metadata labels in imported
  Markdown without shelling out to Pandoc.
- Smart quotes, apostrophes, dashes, and ellipses render as WordPress-safe
  inline text, preserving editor comments and import date ranges without
  shelling out to Pandoc.
- Inline math, display math, raw TeX citation commands, and raw TeX table
  source render as escaped WordPress-safe markup, preserving technical import
  notes for later MathJax/KaTeX or citation-processing passes without shelling
  out to Pandoc.
- Inline math whose TeX arguments contain literal dollars now remains one
  WordPress-safe math span, matching Pandoc's `markdown-reader-more` `$ in
  math` fixture for reviewer notes such as `\text{the $n$th root of $y$}`.
- Raw TeX macro definitions from Markdown imports now stay as escaped TeX code
  blocks, and subsequent math using a one-argument macro expands before
  WordPress output. This preserves reviewer-visible source definitions while
  making the rendered math handoff match Pandoc's `markdown-reader-more`
  fixture behavior.
- HTML entity text and comparison characters render as normal escaped
  WordPress paragraph text: `AT&amp;T` is decoded into the AST and emitted once
  as `AT&amp;T`, while `<` is emitted as `&lt;` instead of being treated as raw
  HTML.
- Character and numeric Markdown entity references from
  `Tests.Readers.Markdown` now decode before WordPress escaping too:
  reviewer notes containing `&lang; &ouml;`, decimal references, and
  lowercase/uppercase hexadecimal references render as visible Unicode/text,
  and link title attributes receive the same decoded metadata.
- Reference audit links render as normal WordPress paragraph links with title
  attributes preserved, URI autolinks render as escaped clickable URLs, bare
  pasted http(s) source URLs become anchors with trailing punctuation kept
  outside the link, and importer email autolinks render as `mailto:` links
  without invoking Pandoc.
- Reviewer-pasted source URI notes now map the adjacent Pandoc bare URI
  extension cases: DOI identifiers, Git remote URLs, local `file://` export
  paths, and `mailto:` handoff contacts become WordPress-safe links while
  commas and periods remain outside the anchor text.
- Extended reviewer source URL notes now cover the rest of the upstream bare
  URI shape family: Greek source pages, `%20` paths, and at-sign mailing-list
  archives render as WordPress-safe links without requiring angle brackets.
- Legacy media and manifest links with spaces render as WordPress-safe
  `%20`-encoded URLs, including split reference definitions whose title is on a
  following line.
- Legacy source links whose destinations, titles, or autolink text contain
  HTML entities now decode to the same native URL/title/label text Pandoc
  reports, then render through WordPress escaping once. Parenthesized campaign
  URLs and nested parenthesized reference destinations also remain intact, so
  import-review links such as `/hi(there)` and `hi_(there_(nested))` do not get
  truncated at the first closing parenthesis.
- Backslash-heavy source link labels now preserve escaped visible punctuation
  and reviewer-visible raw TeX commands inside the linked text, unresolved
  reference-looking source markers fall back to bracketed emphasized text,
  citation-adjacent shortcut links keep the source link clickable while leaving
  the citation marker visible, and empty reference placeholders render as empty
  `href` links without swallowing the following review paragraph.
- Backslash-escaped source URL/title punctuation now follows Pandoc's reader
  boundary for migration links: escaped closing parentheses remain part of the
  destination, escaped title quotes render as WordPress-safe title attributes,
  and reference definitions can carry escaped `)` or `.` punctuation without
  leaving literal backslashes in reviewer-facing links.
- Bare Pandoc citation imports now keep reviewer citation text visible while
  preserving link boundaries around adjacent source logs. This lets later
  citation-processing passes see `@cita [review-only note]` without turning a
  real migration source link into citation suffix text.
- Bracketed review spans now preserve Pandoc-style id/class/key-value metadata
  in the AST while the WordPress output emits safe span attributes for migration
  review markers around emphasized edit links.
- Attributed inline code spans now preserve Pandoc-style id/class/key-value
  metadata in the AST while the WordPress output emits safe code attributes for
  migration review markers around source tokens.
- Implicit intra-document reviewer links render as WordPress anchor links, and
  attributed Markdown headings preserve stable ids/classes for migration review
  without shelling out to Pandoc.
- ATX headings with closing `#` markers and setext headings from legacy editor
  notes now normalize to stable WordPress heading anchors, so Data Liberation
  imports do not expose trailing Markdown fence characters in block output.
- Referenced import images render as core WordPress image blocks with preserved
  captions/titles, and inline thumbnail images render inside paragraph blocks
  without invoking Pandoc.
- Reference and inline import footnotes render as numbered note references plus
  one appended WordPress HTML endnotes block, preserving reviewer source trails,
  nested links, code spans, continuation paragraphs, and indented code snippets
  without invoking Pandoc.
- Pipe-table import metrics and relative-width review-note tables render as
  core WordPress table blocks with `<thead>`, `<tbody>`, aligned cells,
  optional `<colgroup>` widths, a figcaption where present, escaped emphasis,
  links, and code spans without invoking Pandoc.
- Rectangular Pandoc grid-table import queues render as core WordPress table
  blocks with upstream-style grid widths, header/headless table shapes,
  right/left/center alignment markers, scalar multiline cells, Unicode source
  text, and empty cells preserved without invoking Pandoc.
- Block-rich Pandoc grid-table import queues now preserve headings, paragraphs,
  and bullet lists inside table cells while keeping scalar multiline cells
  compact. This maps the upstream multiple-block cell case and gives migration
  reviewers WordPress table output without flattening cell-level structure.
- Pandoc grid-table span import queues now preserve omitted interior column
  dividers as `colspan` metadata, partial horizontal separators as `rowspan`
  metadata, and the adjacent complex multi-row header shape as a WordPress table
  head with spanning header cells.
- Legacy simple-table source totals render as core WordPress table blocks with
  inferred alignment styles and captions without invoking Pandoc.
- Wrapped multiline review tables render as core WordPress table blocks with
  softbreak newlines inside cells, inferred alignment styles, captions, and
  colgroup widths without invoking Pandoc.
- Short-caption LaTeX tables render as core WordPress table blocks with
  alignment styles, visible long captions, and preserved short-caption metadata
  without invoking Pandoc.
- DocBook import-audit tables render as core WordPress table blocks with
  colgroup widths, per-cell alignment, strong inline cell content, preserved
  `colspan`/`rowspan` structural metadata, and table footers without invoking
  Pandoc.
- Markdown review lists that contain raw HTML controls now stay attached to the
  intended list item. The fixture maps Pandoc's list issue #1154 shape with
  div/button/div children so migration review markup does not escape the list
  and reorder editorial checklist context.
- GitHub-style reviewer task lists now render as WordPress-safe checkbox list
  HTML from native AST metadata, including nested task follow-up items, without
  shelling out to Pandoc or flattening completed/incomplete review state into
  plain bracket text.
- The same task metadata can now be exported through native Markdown and LaTeX
  writer paths for reviewer handoff documents: unchecked/checked WordPress
  review tasks round-trip as Markdown `- [ ]`/`- [x]` markers and as Pandoc's
  LaTeX square/boxtimes item labels without invoking the upstream binary.
- Native Markdown reviewer handoff exports now preserve Pandoc fancy ordered
  list markers too: source-ID queues can leave WordPress review as `(2)`,
  roman `iv.`, alpha `A.`/`c)`, and default autonumbered Markdown lists with
  Pandoc-style marker spacing instead of flattening every ordered list to
  decimal periods.
- `examples/wordpress-markdown-review-handoff.php` demonstrates a native
  Markdown reviewer packet for WordPress editorial handoff: inline notes and
  quote-contained notes are emitted at Pandoc-compatible block boundaries, and
  source-review links can be written as shortcut reference links with their
  definitions beside the relevant block instead of being flattened into inline
  URLs.
- The same reviewer handoff example now covers Pandoc's shortcut-reference
  boundary rules for adjacent source links, repeated labels, bracketed reviewer
  notes, and citation-adjacent references. This keeps exported WordPress review
  packets parseable by Pandoc-compatible Markdown tooling when multiple source
  URLs share a human label like `source`.
- Native Markdown reviewer handoff exports now also follow Pandoc's top-level
  writer boundaries for review packets assembled from the shared AST:
  multi-paragraph ordered review steps write the first paragraph on the marker
  line and continuation paragraphs under the marker content column, a top-level
  indented source snippet after a list is separated with Pandoc's `<!-- -->`
  guard so it does not become a list continuation when re-read, tight nested
  checklists stay compact, and delimiter-adjacent strong/emphasis spacing keeps
  source-review markers parseable by Pandoc-compatible Markdown tooling.
- Native Markdown reviewer handoff exports now escape literal audit tokens using
  Pandoc's Markdown inline writer rules. This keeps source text such as
  heading-looking `#` markers, Markdown emphasis delimiters, code ticks,
  pipe-table separators, TeX/math punctuation, HTML-looking tags, entity
  references, and raw-TeX backslashes visible as reviewer text instead of being
  reinterpreted when the packet is re-imported.
- Native Markdown reviewer handoff exports now emit Pandoc-style URI and e-mail
  autolinks plus link attributes. The reviewer handoff example writes
  `<https://example.test/review-packet>` and `<editor@example.test>` directly,
  and emits a packet reference definition with `{#review-packet .source-link
  data-source="batch-42"}` metadata so WordPress editorial packets can preserve
  source-review ids/classes without falling back to inline HTML.
- Native Markdown reviewer handoff exports now emit Pandoc-style image Markdown
  too. A reviewer media preview can leave WordPress as a shortcut reference
  image with a definition carrying title, id, class, `alt`, and
  `data-source` metadata, while URI-looking alt text is guarded from becoming
  invalid `!<uri>` autolink syntax.
- Native Markdown reviewer handoff exports now also preserve Pandoc-style
  attributed inline code and bracketed spans. The handoff example writes
  source-review metadata as `[...]{#migration-span .review-span ...}`, emits
  reviewer/source code tokens as `` `wp_enqueue_script`{#enqueue-call .php
  data-source="batch-42"}``, keeps emoji spans as `:smile:`, and escapes a
  literal bang before following link/span syntax so source-review prose cannot
  turn into an unintended image.
- Native Markdown reviewer handoff exports now preserve Pandoc-style
  strikeout, superscript, subscript, math, raw TeX, and raw-attribute inline
  output. The handoff example writes reviewer cleanup text as
  `~~legacy TeX screenshot~~`, `H~2~`, `^*draft*^`, `$x \in y$<!-- -->2`,
  `\cite[22-23]{smith.1899}`, and `` `<outline .../>`{=opml}``, keeping
  editorial packets parseable by Pandoc-compatible Markdown tooling without
  shelling out to the upstream binary.
- Native Markdown reviewer handoff exports now also preserve Pandoc-style
  quoted, underline, and small-caps inline writer output. The handoff example
  writes source excerpts with Markdown quote delimiters and sends editorial
  style markers as `[manual underlines]{.underline}` and
  `[source glossary]{.smallcaps}`, so reviewer packets keep source styling
  hints without inline HTML.
- Native Markdown reviewer handoff exports now preserve Pandoc-style mark spans.
  The handoff example writes highlighted source-review copy as
  `==verify source caption==` while escaping literal `==audit tokens==`, so
  imported reviewer packets can distinguish intentional highlights from source
  text that merely contains equal-sign markers.
- Native Markdown reviewer handoff exports now preserve Pandoc-style citation
  writer output. The handoff example writes author-in-text review citations as
  `@migration-audit [p. 12; see @source-log ch. 4]` and suppress-author
  entries as `[-@{legacy key}, appendix]`, so exported editorial packets keep
  citeproc-ready review markers without shelling out to Pandoc.
- Native Markdown reviewer handoff exports now also map Pandoc's raw HTML
  fallback for attributed links and images when Markdown link attributes are
  disabled. `examples/wordpress-markdown-raw-html-fallback.php` demonstrates a
  reviewer edit link and media preview emitted as raw `<a>`/`<img />` HTML with
  id, class, title, alt, and `data-source` metadata, preserving WordPress review
  context for downstream Markdown profiles that cannot carry Pandoc attribute
  tuples.
- Native Markdown reviewer handoff exports now also map Pandoc's raw
  HTML/native-span fallback for attributed spans when bracketed span attributes
  are disabled. `examples/wordpress-markdown-raw-html-fallback.php` now
  demonstrates a scoped reviewer span emitted as raw `<span>` HTML beside the
  edit link and media preview, so WordPress migration packets can preserve
  source-scope ids/classes/data attributes for Markdown profiles without
  Pandoc bracketed spans.
- Native Markdown reviewer handoff exports now map Pandoc's underline and
  small-caps fallback toggles too. When downstream Markdown profiles disable
  bracketed spans, the raw-HTML fallback example emits reviewer underline as
  `<u>...</u>` and source-glossary small caps as
  `<span class="smallcaps">...</span>`; when raw HTML/native spans are both
  unavailable, the writer falls back to emphasis for underline and Pandoc-style
  uppercase `Str` text for small caps while preserving code tokens.
- Native Markdown reviewer handoff exports now follow Pandoc's nested-emphasis
  normalization too. The handoff example collapses a doubled source-review
  emphasis node to plain `source flag` text and drops empty source
  emphasis/strong markers, so review packets do not accidentally turn empty
  editorial placeholders into visible Markdown delimiters.
- Native Markdown reviewer handoff exports now follow Pandoc's disabled
  strikeout fallback as well. When a downstream Markdown profile disables
  strikeout syntax, the raw-HTML fallback example emits deleted source-review
  captions as `<s>legacy caption</s>` if raw HTML is available, and the writer
  can drop the strikeout wrapper to plain rendered content when raw HTML is
  unavailable.
- Native Markdown reviewer handoff exports now follow Pandoc's disabled
  superscript/subscript fallback as well. When a downstream Markdown profile
  disables script syntax, the raw-HTML fallback example emits compact reviewer
  annotations as `H<sub>2</sub>` and `x<sup>2</sup>` if raw HTML is
  available; when raw HTML is unavailable, the writer falls back to Pandoc's
  Unicode script digit/symbol output or parenthesized text for content that
  cannot be represented by the upstream script conversion table.
- Native Markdown reviewer handoff exports now follow Pandoc's smart-disabled
  quoted fallback as well. When a downstream Markdown profile disables smart
  punctuation, the raw-HTML fallback example emits reviewer source quotes as
  `&lsquo;legacy reviewer quote&rsquo;` and
  `&ldquo;migration excerpt&rdquo;` under `preferAscii`, while native Unicode
  curly delimiters are available when ASCII preference is off. Smart-disabled
  handoff text also leaves ordinary quotes, dash ranges, and ellipses literal
  instead of escaping them as smart-punctuation triggers.
- Native Markdown reviewer handoff exports now map Pandoc's `preferAscii`
  behavior for ordinary `Str` text too. The raw-HTML fallback example emits
  non-ASCII reviewer metadata as `R&eacute;sum&eacute;`, `&COPY;`, `&in;`,
  decimal `&#128512;`, `&ldquo;curly excerpt&rdquo;`, and `&mldr;`, so
  WordPress editorial packets can target ASCII-only Markdown channels without
  flattening source-review text or shelling out to Pandoc.
- Native Markdown reviewer handoff exports now map Pandoc's `LineBreak`
  writer option branches too. The review-handoff example emits an escaped
  line-break backslash by default so a source-review line stays attached to the
  editor continuation, while focused tests cover the two-space Markdown
  hard-break fallback and the plain-newline `hard_line_breaks` branch for
  downstream Markdown profiles.
- Native Markdown reviewer handoff exports now map Pandoc's raw-inline
  extension fallback order too. The review-handoff example preserves raw TeX
  citations, OPML source packets, and raw HTML reviewer markers with
  Pandoc-style raw-attribute Markdown by default; focused tests cover
  disabling `raw_attribute` so HTML and TeX can pass through literally when
  their target raw extensions are enabled, or be omitted when those extensions
  are disabled.
- Native Markdown reviewer handoff exports now map Pandoc's block-level
  RawBlock and Div fallbacks too. The review-handoff example emits a
  source-scoped WordPress review packet as fenced Div Markdown with
  `data-source` metadata, preserves an OPML review outline as a fenced
  raw-attribute block, and keeps a TeX review environment literal while tests
  cover raw-HTML/raw-TeX pass-through, raw-attribute fallback, unsupported raw
  block omission, native/raw HTML Div wrappers, and content-only Div fallback
  for constrained downstream Markdown profiles.
- Native Markdown reviewer handoff exports now map Pandoc's Figure fallback
  boundary too. The raw-HTML fallback example emits an attributed source-review
  `<figure>` with id, class, `data-source`, image title/alt, and caption
  metadata when a figure cannot be represented as an implicit Markdown image,
  while focused tests cover implicit figures, raw HTML fallback, fenced figure
  Divs, disabled implicit-figure output, and content-only degradation for
  constrained downstream Markdown profiles.
- Native Markdown reviewer handoff exports now map Pandoc's table fallback
  boundary too. Simple review tables can round-trip as pipe tables with
  alignment delimiters, inline caption content, and Pandoc caption attributes;
  spanned source-review tables fall back to raw HTML with table class,
  `data-source`, caption, colgroup widths, colspan, and cell alignment
  metadata; raw-disabled profiles can still get an approximate pipe table for
  simple spanned content; and fully constrained profiles receive Pandoc's
  `[TABLE]` placeholder plus caption/attrs instead of silently dropping the
  reviewer table.
- Native Markdown reviewer handoff exports now map the bounded Pandoc
  grid-table branch for source-review tables that need Markdown-native output
  but cannot be represented as pipe tables. `examples/wordpress-markdown-grid-table-handoff.php`
  emits a block-rich migration review queue with heading, paragraph, bullet
  list, hard line break, footer total, width-derived grid columns, alignment
  markers, and caption/source attrs without shelling out to Pandoc or falling
  back to raw HTML.
- Native Markdown reviewer handoff exports now map Pandoc's multiline-table
  branch for width-bearing simple-cell tables. `examples/wordpress-markdown-multiline-table-handoff.php`
  emits wrapped Data Liberation review notes as Pandoc-style multiline
  Markdown with headed full borders, width-derived alignment, multiline source
  cells, and caption/source attrs, keeping reviewer packets Markdown-native
  without degrading to raw HTML or pipe syntax when multiline tables are
  available.
- Native Markdown reviewer handoff exports now map Pandoc's spanned
  grid-table branch. `examples/wordpress-markdown-spanned-grid-table-handoff.php`
  emits a migration review queue with row-spanned media areas, colspanned
  remediation status cells, partial horizontal-rule gaps, double head/body
  boundaries, caption/classes/source attrs, and no raw HTML fallback while
  `grid_tables` is available. Raw HTML and approximate pipe fallbacks are still
  available for constrained downstream Markdown profiles that disable grid
  tables.
- Native Markdown reviewer handoff exports now map Pandoc's simple-table
  branch for widthless simple-cell tables. `examples/wordpress-markdown-simple-table-handoff.php`
  emits Data Liberation import totals as Pandoc-style simple table Markdown
  with right/left/center/default alignment padding, caption/classes/source
  attrs, and no pipe/raw HTML fallback while `simple_tables` is available.
  Disabling `simple_tables` still gives pipe syntax when `pipe_tables` is
  available, and disabling both falls through to a multiline Pandoc table
  before raw HTML.
- Native Markdown reviewer handoff exports now map Pandoc's display-width
  `numChars` branch for widthless simple tables.
  `examples/wordpress-markdown-unicode-table-width-handoff.php` emits
  multilingual WordPress import labels with CJK wide characters plus
  zero-width joiner/non-joiner source tokens as aligned native Pandoc
  simple-table Markdown, so Data Liberation reviewer packets do not need a raw
  HTML table fallback just to preserve readable column alignment.
- Native Markdown reviewer handoff exports now map Pandoc's width-constrained
  pipe-table branch. `examples/wordpress-markdown-pipe-width-handoff.php`
  emits a narrow migration review queue with relative delimiter widths derived
  from source column hints, unpadded over-wide reviewer notes, stable alignment
  markers, caption/classes/source attrs, and no raw HTML fallback while
  `pipe_tables` is available.
- Native Markdown reviewer handoff exports now map Pandoc's positional
  default-width pipe-table branch too.
  `examples/wordpress-markdown-pipe-default-width-handoff.php` emits a narrow
  multilingual WordPress import queue where a default-width label column keeps
  its zero-width delimiter slot and later 25 percent/75 percent reviewer
  columns keep their own relative delimiter widths. This prevents a source
  label column from stealing the review-note column's width hint when
  downstream Markdown profiles need pipe tables instead of raw HTML.
- Native Markdown reviewer handoff exports now map Pandoc's table-caption
  `WrapAuto` branch for constrained pipe-table output.
  `examples/wordpress-markdown-pipe-caption-wrap-handoff.php` emits a narrow
  WordPress import review queue where a long caption wraps under
  `writerColumns` while the caption attribute tuple stays attached to the
  caption block. `wrap=none` and `hardLineBreaks` retain no-wrap output, so
  downstream Markdown handoff profiles can choose between readable wrapped
  captions and source-preserving captions without invoking Pandoc.
- Native Markdown reviewer handoff exports now map Pandoc's disabled
  `table_captions` / CommonMark caption-marker boundary too.
  `examples/wordpress-markdown-commonmark-caption-handoff.php` emits a
  CommonMark-flavored WordPress import review table where caption text and
  source attrs stay visible, but the Pandoc-specific leading `: ` marker is
  omitted. This keeps captions reviewable in downstream Markdown profiles that
  support pipe tables but not Pandoc table-caption syntax.
- Native Markdown reviewer handoff exports now map Pandoc's multiline-table
  `WrapAuto` minimum word-width branch. `examples/wordpress-markdown-multiline-wrap-handoff.php`
  emits a narrow import-token review queue where a long WordPress source token
  stays unbroken, normal reviewer notes wrap at word boundaries, caption/source
  attrs survive, and the handoff remains Pandoc-style multiline Markdown
  instead of raw HTML.
- Native Markdown reviewer handoff exports now map Pandoc's multiline-table
  `WrapNone` full-line-width branch. `examples/wordpress-markdown-multiline-nowrap-handoff.php`
  emits the same kind of import-token review queue for no-wrap editorial
  packets: source tokens and reviewer notes keep full cell-line widths,
  `hardLineBreaks` uses the same no-wrap table sizing boundary, caption/source
  attrs survive, and the handoff remains Pandoc-style multiline Markdown
  instead of raw HTML.
- Native Markdown reviewer handoff exports now map Pandoc's `SoftBreak`
  `WrapPreserve` branch. `examples/wordpress-markdown-wrap-preserve-handoff.php`
  emits a WordPress source-review packet where nonsemantic paragraph line
  boundaries stay visible for editorial audit under `wrap-preserve`, while
  default `WrapAuto`, explicit `WrapNone`, and the `hardLineBreaks` guard use
  Pandoc-compatible spacing instead of forcing preserved source wraps.
- Native Markdown reviewer handoff exports now map Pandoc's heading attribute
  branch. `examples/wordpress-markdown-heading-anchors-handoff.php` emits a
  WordPress review packet with custom Pandoc heading ids, classes, and
  source attrs for intra-document audit links, while duplicate imported
  auto-generated headings stay clean and do not receive redundant `{#...}`
  attributes.
- Native Markdown reviewer handoff exports now map Pandoc's fenced code-block
  attribute branch. `examples/wordpress-markdown-fenced-code-handoff.php`
  emits a shortcode cleanup snippet as fenced PHP code with a stable Pandoc
  block id, language/numbering classes, source batch metadata, and start-line
  metadata so downstream review packets keep snippet provenance without
  shelling out to Pandoc or downgrading to raw HTML.
- Native Markdown reviewer handoff exports now map Pandoc's DefinitionList
  writer branch. `examples/wordpress-markdown-definition-list-handoff.php`
  emits editable import glossary/checklist terms as Pandoc-style definition
  list Markdown with repeated tight definitions, loose nested shortcode
  snippet provenance, source attrs, and an adjacent reviewer-packet definition
  list separated by Pandoc's neutral `<!-- -->` block separator, keeping
  reviewer packets Markdown-native before WordPress block conversion without
  merging separate glossary/checklist sections.
- Native Markdown reviewer handoff exports now map Pandoc's adjacent same-type
  list separator branch. `examples/wordpress-markdown-adjacent-list-handoff.php`
  emits separate bullet and ordered reviewer queues with neutral `<!-- -->`
  separators between same-type list blocks, so a downstream Pandoc/WordPress
  import does not merge review phases that should remain separate editorial
  queues.
- Native Markdown reviewer handoff exports now map Pandoc's RawBlock/Plain
  `fixBlocks` tight-boundary branch.
  `examples/wordpress-markdown-raw-boundary-handoff.php` emits plain
  WordPress source-review notes adjacent to raw HTML review cards without
  inserting extra blank Markdown blocks, while a following review heading still
  receives normal block separation. This keeps trusted source-review HTML
  packets attached to surrounding editorial notes for downstream Pandoc or
  WordPress import.
- Native Markdown reviewer handoff exports now map Pandoc's in-list
  Plain-before-fenced-Div `fixBlocks` branch.
  `examples/wordpress-markdown-list-div-handoff.php` emits a WordPress
  reviewer source note as a real list item, then separates the following
  fenced review Div with Pandoc-compatible loose spacing. This prevents source
  review packets from becoming a dangling list marker while keeping the Div
  grouped with the same import checklist item.
- Native Markdown reviewer handoff exports now map the ordered-list
  paragraph-before-fenced-Div boundary.
  `examples/wordpress-markdown-ordered-list-div-handoff.php` emits a
  three-digit review checklist item whose following fenced source-metadata Div
  keeps the paragraph/Div blank block boundary and uses marker-width
  continuation indentation. This keeps long WordPress import step numbers from
  pushing reviewer packet Divs out of the intended checklist item.
- Native Markdown reviewer handoff exports now map Pandoc's Plain/Para
  marker-escaping branch. `examples/wordpress-markdown-plain-marker-handoff.php`
  emits literal WordPress source labels such as `1.`, `(2)`, `-`, and `%` with
  Pandoc-compatible escapes, so downstream Markdown re-read keeps them as
  reviewer paragraphs rather than ordered lists, bullet lists, or title-block
  metadata. Nested reviewer paragraphs inside checklist items receive the same
  guard.
- Native Markdown reviewer handoff exports now map Pandoc's CommonMark
  `RawInline` and `LineBreak` variant behavior.
  `examples/wordpress-markdown-commonmark-raw-handoff.php` emits
  CommonMark-compatible raw inline source spans and raw HTML review markers
  literally, keeps Markdown-only raw inline formats out of the default
  CommonMark handoff, and forces backslash hard breaks even when the generic
  escaped-line-break option is disabled. This keeps WordPress review packets
  compatible with CommonMark-oriented downstream tools while preserving trusted
  source annotations.
- Native Markdown reviewer handoff exports now map Pandoc's CommonMark
  `RawBlock` variant behavior.
  `examples/wordpress-markdown-commonmark-raw-block-handoff.php` emits
  block-level CommonMark-compatible source HTML literally, applies Pandoc's
  raw HTML blank-line escaping so strict CommonMark review packets do not gain
  accidental blank HTML block breaks, and omits GitHub-only raw Markdown unless
  a raw-attribute-capable profile is selected.
- Native Markdown reviewer handoff exports now map Pandoc's Markdown writer
  `LineBlock` branch.
  `examples/wordpress-markdown-line-block-handoff.php` emits a source-review
  stanza as pipe-prefixed Pandoc line-block Markdown, preserving indentation as
  nonbreaking spaces and keeping an empty line entry visible. This gives
  migration reviewers an editable Markdown handoff for poems, addresses, logs,
  and source excerpts before conversion into WordPress paragraph blocks.
- Native plain-text reviewer handoff exports now map Pandoc's `writePlain`
  `LineBlock` branch.
  `examples/wordpress-plain-line-block-handoff.php` emits the same kind of
  source-review stanza without Pandoc pipe markers, while preserving
  nonbreaking source indentation and empty line entries. This gives WordPress
  import tools a native plain-text path for excerpts, notification emails,
  search snippets, and audit logs without shelling out to Pandoc.
- Native plain-text reviewer handoff exports now map additional Pandoc
  `writePlain` block branches.
  `examples/wordpress-plain-review-blocks-handoff.php` emits unmarked plain
  headings, source paragraph labels without Markdown link markup,
  two-space-indented quote notes, literal plain raw review packets, and
  writer-column dash separators. This gives WordPress import tools a native
  plain-text packet for excerpts, notification emails, search snippets, and
  audit logs where Markdown markup would leak into reviewer-facing text.
- Native plain-text reviewer handoff exports now map Pandoc's `writePlain`
  list and DefinitionList branches.
  `examples/wordpress-plain-definition-list-handoff.php` emits import
  glossary/checklist terms without Markdown emphasis or link markers, uses the
  upstream PlainText two-space definition leader instead of `:` markers, and
  keeps nested shortcode/code and quote review notes visibly indented. This
  gives WordPress import tools a plain-text glossary/audit path for excerpts,
  notification emails, search snippets, and reviewer logs without shelling out
  to Pandoc or leaking Markdown syntax to non-technical reviewers.
- Native plain-text reviewer handoff exports now map Pandoc's `writePlain`
  image and note branches.
  `examples/wordpress-plain-media-note-handoff.php` emits plain media-review
  packets with bracketed image captions, numeric note references, stripped
  source edit link text inside note bodies, and indented code-note snippets.
  This gives WordPress import tools a native plain-text media audit path for
  excerpts, notification emails, search snippets, and reviewer logs without
  leaking Markdown image or footnote syntax.
- Native plain-text reviewer handoff exports now map Pandoc's `writePlain`
  Gutenberg inline branch too.
  `examples/wordpress-plain-gutenberg-handoff.php` emits Gutenberg-oriented
  plain review text where strong reviewer status becomes Unicode-safe
  uppercase text, source edit links are stripped to their labels, code tokens
  such as `wp_update_post` stay literal, and emphasis remains visible with
  underscore delimiters. This gives WordPress import tools a native plain-text
  path for Gutenberg excerpts, notification emails, search snippets, and audit
  logs where strong review statuses need to stand out without leaking Markdown
  link destinations.
- Native plain-text reviewer handoff exports now map Pandoc's `writePlain`
  table cell and caption fallback branch too.
  `examples/wordpress-plain-table-fallback-handoff.php` emits an unsupported
  spanned import table as a visible `[TABLE]` review marker with a plain source
  caption and attrs. Strong/link/code markup and admin URLs are stripped from
  reviewer-facing table text, giving WordPress import tools a plain-text audit
  path for excerpts, notifications, search snippets, and logs when an import
  table cannot be represented faithfully without raw HTML.
- Native plain-text reviewer handoff exports now map Pandoc's `writePlain`
  template titleblock branch.
  `examples/wordpress-plain-titleblock-handoff.php` emits a Data Liberation
  import audit packet with title, semicolon-joined authors, and date metadata
  ahead of body text. Metadata inlines use PlainText semantics, so source admin
  links, Markdown emphasis, and code ticks are stripped before excerpts,
  notifications, search snippets, or audit logs reach non-technical reviewers.
- Native plain-text reviewer handoff exports now map Pandoc's default plain
  template table-of-contents branch.
  `examples/wordpress-plain-toc-handoff.php` emits a nested TOC before plain
  body text for import audit packets. TOC labels keep reviewer-visible heading
  text while stripping source edit URLs, generated `toc-*` anchors, source
  link attributes, and code ticks; `tocDepth` keeps private deeper headings
  out of short reviewer packets.
- Native plain-text reviewer handoff exports now map Pandoc's numbered default
  plain template table-of-contents branch too.
  `examples/wordpress-plain-numbered-toc-handoff.php` emits generated source
  section numbers in TOC labels, keeps numbered `unlisted` audit headings
  visible, keeps `unnumbered` appendix headings visible without advancing
  counters, and preserves explicit legacy section numbers while leaving body
  headings plain. This gives WordPress import tools a shell-free audit packet
  for source traceability when reviewers need numbered legacy sections in the
  excerpt, notification, search snippet, or log output.
- Native plain-text reviewer handoff exports now map Pandoc's default plain
  template `body` context override branch.
  `examples/wordpress-plain-body-override-handoff.php` emits a redacted
  WordPress import audit packet where a template-provided body replaces the
  converted source body while metadata `include-after` footer text still
  follows it. Metadata body values render through PlainText semantics when no
  writer body variable is supplied, while writer variables remain raw template
  values and take precedence. This gives WordPress import tools a shell-free
  handoff for approved notification/search/audit text without losing the
  source conversion body in the underlying import record.
- Native plain-text reviewer handoff exports now map a bounded Pandoc custom
  template branch syntax slice.
  `examples/wordpress-plain-template-branching-handoff.php` emits a custom
  WordPress plain import branch packet with dotted workflow metadata, a
  fallback `$else$` branch, a standalone `$elseif$` escalation block that
  swallows the branch newline before selecting the workflow queue,
  comma-separated reviewer recipients from `$for$`/`$sep$`, and body text
  rendered through PlainText semantics. This gives WordPress import tools a
  shell-free way to build notification, excerpt, and audit packets from
  structured metadata without leaking admin URLs into reviewer-facing body
  text or adding spurious blank lines to reviewer packets.
- Native plain-text reviewer handoff exports now map Pandoc custom template
  final-newline and boolean scalar rendering.
  `examples/wordpress-plain-template-final-newline-handoff.php` emits a custom
  WordPress reviewer packet where newline-terminated review fields do not add
  spurious blank lines, double-newline fields keep one intentional reviewer
  gap, and true/false metadata values render visibly for downstream audit
  packets. The source body still renders through PlainText semantics, so admin
  edit URLs are stripped before notification, excerpt, search, and audit
  output.
- Native plain-text reviewer handoff exports now map Pandoc custom template
  direct-list interpolation and the `space-in-loop` whitespace boundary.
  Compact reviewer lists or status fragments interpolated directly into custom
  WordPress audit packets concatenate without implicit paragraph gaps, while
  explicit loop bodies still preserve intentional blank lines between rendered
  values. Empty loops with blank bodies emit nothing, which prevents absent
  metadata sections from leaving stray whitespace in notification, excerpt,
  search, or audit output.
- Native plain-text reviewer handoff exports now map a bounded Pandoc custom
  template `meta-json` branch.
  `examples/wordpress-plain-meta-json-template-handoff.php` emits a custom
  WordPress import audit packet whose template exposes metadata-only JSON,
  generated titleblock/body values, and a writer-variable preface override.
  Metadata block values inside `meta-json` render through PlainText semantics,
  so source edit links and code ticks are stripped from the JSON audit summary
  while the custom preface can remain raw reviewer text.
- Native plain-text reviewer handoff exports now map a bounded Pandoc custom
  template nested-control branch.
  `examples/wordpress-plain-template-nested-handoff.php` emits a custom
  WordPress reviewer packet with scalar label loops through `it`, nested
  phase/reviewer loops, an `elseif` fallback status branch, a literal-dollar
  charge field, and omitted template comments before the PlainText body. This
  gives WordPress import tools a shell-free notification, excerpt, and audit
  packet path for structured migration workflow metadata without leaking
  template comments or admin-only source values.
- Native plain-text reviewer handoff exports now map a bounded Pandoc custom
  template partial branch.
  `examples/wordpress-plain-template-partial-handoff.php` emits a custom
  WordPress reviewer packet assembled from a partial map: a reviewer-list
  partial applies a reviewer partial to each metadata entry with bracket
  separators, includes a nested footer partial, renders workflow metadata
  through the anaphoric `it` context, omits final partial newlines before the
  next template line, and leaves the body rendered through PlainText semantics.
  This gives WordPress import tools a shell-free way to share reviewer packet
  subtemplates across notification, excerpt, search, and audit outputs without
  leaking source admin URLs.
- Native plain-text reviewer handoff exports now map Pandoc custom template
  partial recursion guards.
  `examples/wordpress-plain-template-loop-guard-handoff.php` emits a custom
  WordPress reviewer packet where accidentally cyclic partials produce
  Pandoc's `(loop)` sentinel instead of disappearing or exhausting recursion.
  The converted source body still renders through PlainText semantics, so
  admin edit URLs are stripped before notification, excerpt, search, and audit
  output.
- Native plain-text reviewer handoff exports now map a bounded Pandoc custom
  template pipe branch.
  `examples/wordpress-plain-template-pipes-handoff.php` emits a custom
  WordPress reviewer packet using MANUAL-documented no-parameter doctemplate
  pipes: status is lowercased, queue text is uppercased, labels are counted,
  sliced with first/last/rest/allbutlast, reversed with a bracket separator,
  and reviewer arrays are converted with `pairs` so one-based keys can be
  rendered through `alpha/uppercase`. The body still renders through PlainText
  semantics, so source edit links are stripped before notification, excerpt,
  search, and audit output.
- Native plain-text reviewer handoff exports now map a bounded Pandoc custom
  template parameterized alignment pipe branch.
  `examples/wordpress-plain-template-align-handoff.php` emits a custom
  WordPress reviewer packet using MANUAL-documented `left`, `right`, and
  `center` pipes with positive widths and quoted borders. Batch metadata,
  workflow queue labels, reviewer counts, and status text are padded into
  predictable PlainText columns while the source body still renders through
  PlainText semantics, so admin edit URLs are stripped before notification,
  excerpt, search, and audit output.
- Native plain-text reviewer handoff exports now map Pandoc doctemplates
  `loop-in-object.test` behavior and the upstream partial nesting-depth guard.
  `examples/wordpress-plain-template-object-loop-handoff.php` emits a reviewer
  packet where a nested metadata object (`audit.reviewers`) is looped directly,
  anaphoric `it.name` fields resolve inside each reviewer, and the native
  partial guard follows doctemplates' level-50 boundary before emitting
  `(loop)`. The source body still renders through PlainText semantics, so admin
  edit URLs are stripped before notification, excerpt, search, and audit output.
- Native plain-text reviewer handoff exports now map Pandoc doctemplates
  `pad.test` multiline alignment behavior.
  `examples/wordpress-plain-template-pad-handoff.php` emits a reviewer packet
  table whose multiline notes compose line-by-line with adjacent aligned cells,
  whose blank metadata cells are vertically filled to preserve table shape, and
  whose over-wide legal-hold note is kept intact instead of being truncated.
  The source body still renders through PlainText semantics, so admin edit URLs
  are stripped before notification, excerpt, search, and audit output.
- Native plain-text reviewer handoff exports now map Pandoc custom template
  breakable spaces and the MANUAL `/nowrap` pipe.
  `examples/wordpress-plain-template-nowrap-handoff.php` emits a custom
  WordPress reviewer packet where a breakable editorial summary wraps at a
  narrow PlainText column, a legal-hold source reference stays on one line
  through `/nowrap`, a readiness marker trims trailing breakable space through
  `/chomp`, and the source body still renders through PlainText semantics
  without leaking admin edit URLs.
- Native plain-text reviewer handoff exports now map Pandoc custom template
  nesting.
  `examples/wordpress-plain-template-nesting-handoff.php` emits a custom
  WordPress reviewer packet where `$^$` keeps multiline review descriptions and
  legal-hold partials aligned with their labels, preserves internal blank
  template lines without indentation-only output, keeps a following owner line
  at the same nesting level, and lets an indented multiline summary variable
  nest automatically. The source body still renders through PlainText
  semantics, so admin edit URLs are stripped before excerpts, notification
  emails, search snippets, and audit logs.
- Markdown reviewer handoff exports now map Pandoc wiki-link writer variants.
  `examples/wordpress-markdown-wikilink-writer-handoff.php` emits compact
  `[[target|title]]` and `[[title|target]]` shortcuts for migration runbooks,
  editorial checklist pages, and legacy wiki plugins while preserving regular
  Markdown links when extra source attributes would otherwise be lost. This
  gives WordPress migration tooling a shell-free reviewer Markdown path for
  old wiki-style cross-document shortcuts.
- Markdown reviewer handoff exports now map Pandoc standalone table-of-contents
  writer behavior.
  `examples/wordpress-markdown-toc-handoff.php` emits a standalone reviewer
  Markdown packet for a WordPress migration batch with Pandoc-style TOC links,
  duplicate-safe generated fragments, source/media/publish sections, and
  interior scratch headings kept in the body but omitted from the TOC. This
  gives migration tooling a shell-free outline packet for editorial review
  before content is converted into blocks.
- LaTeX reviewer export now maps Pandoc's bounded LaTeX writer math pipe
  behavior.
  `examples/wordpress-latex-math-handoff.php` emits a WordPress import review
  equation as LaTeX `\(...\)`/`\[...\]` output while also showing the
  WordPress block math spans used by the import handoff. This gives migration
  tooling a shell-free way to preserve inline/display equation source during
  editorial review without activating a PDF or TeX engine dependency gate.
- Native DOCX reviewer handoff exports now map Pandoc DOCX notes and
  links-inside-notes fixtures.
  `examples/wordpress-native-docx-notes-handoff.php` reads copied upstream
  Native fixtures for `notes.native` and `link_in_notes.native`, combines them
  into one document, and emits WordPress endnotes with backlinks while
  preserving the source link inside the final note. This gives document-import
  tooling a shell-free review path for DOCX footnote/endnote handoff before
  the DOCX ZIP/OpenXML dependency gate is opened.
- `examples/wordpress-literate-haskell.php` demonstrates source-documentation
  imports that opt into Pandoc's literate Haskell extension. Bird-track and
  inverse-bird-track snippets become WordPress code blocks with Haskell
  language classes, ordinary indented source remains code, and reviewer notes
  written as one-space-indented block quotes stay WordPress quote blocks instead
  of being misclassified as literate source.
- Native AST reviewer handoff exports now map a bounded Pandoc Native writer
  boundary from `Tests.Writers.Native` plus upstream native figure/citation
  fixtures.
  `examples/wordpress-native-docx-task-list-handoff.php` reads a copied
  upstream DOCX Native `task_list` fixture and emits reviewer-safe WordPress
  task-list checkboxes through an opt-in `taskGlyphsAsCheckboxes` mode while
  leaving default output faithful to Pandoc's source ballot glyph text. This
  gives document-import tooling a shell-free way to turn DOCX checklist glyphs
  into actionable review controls without losing Native read-back evidence.
  `examples/wordpress-native-odt-mixed-list-handoff.php` reads a copied
  upstream ODT Native `orderedListMixed` fixture and emits WordPress ordered
  lists where the default handoff remains clean HTML while reviewer mode
  preserves the source LowerAlpha/OneParen marker as `data-pandoc-list-style`
  and `data-pandoc-list-delimiter`. This gives document-import tooling a
  shell-free way to keep source list marker evidence for editorial review when
  HTML cannot display Pandoc's one-parenthesis delimiter directly.
  `examples/wordpress-native-odt-image-caption-handoff.php` reads a copied
  upstream ODT Native `imageWithCaption` fixture and emits a WordPress image
  block with the source `Pictures/...jpg` target, ODT width/height metadata,
  the original image alt label, and the Native figure caption as the visible
  figcaption. This gives document-import tooling a reviewer-safe ODT image
  handoff without shelling out to Pandoc or activating OpenDocument package
  parsing.
  `examples/wordpress-native-citation-figure-handoff.php` emits a standalone
  Pandoc Native reviewer packet with sorted metadata, a source-media `Figure`
  carrying short/long captions, Image metadata, and `Cite`/`Citation` records
  for author-in-text and suppress-author citation boundaries. This gives
  WordPress migration tooling a deterministic Native AST oracle for media and
  source-citation review without shelling out to Pandoc or citeproc.
  `examples/wordpress-native-review-packet-handoff.php` emits a standalone
  Pandoc Native reviewer packet with sorted metadata, source-link inlines,
  checklist blocks, and escaped PHP code-block fixture text. This gives
  WordPress migration tooling a deterministic Native AST oracle for import
  review without shelling out to Pandoc.
  `examples/wordpress-native-reader-handoff.php` emits a Native packet, parses
  it through `NativeReader`, and renders WordPress heading, paragraph/link, and
  table blocks. This gives WordPress migration tooling a deterministic
  read-back handoff for Native packets without shelling out to Pandoc at import
  time.
  `examples/wordpress-native-upstream-structure-handoff.php` reads a copied
  upstream-shaped Native fixture with DefinitionList, RawBlock, nested Div, and
  parenthesized table sections, then renders reviewer-facing WordPress
  definition-list, raw-HTML grouping, and table blocks without shelling out to
  Pandoc.
- HTML reader reviewer exports now map Pandoc's `pLineBlock` branch.
  `examples/wordpress-native-html-line-block-handoff.php` reads an upstream
  shaped HTML line-block fixture and emits a WordPress paragraph handoff where
  reviewer stanzas keep hard line breaks, an intentionally empty line, NBSP
  indentation, and the source edit link. This gives migration tooling a
  shell-free import path for line-block HTML generated by Pandoc/DocBook-style
  sources without activating broader HTML5 DOM, package, PDF, citation, or
  math-conversion dependency gates.
- HTML writer preview exports now map Pandoc's span-like class lowering branch.
  `examples/wordpress-html-writer-spanlike-handoff.php` emits a reviewer-facing
  HTML preview where keyboard shortcuts, marked publish-preview text, and
  abbr/dfn source terminology lower to real HTML tags before the preview is
  wrapped in a WordPress HTML block. This gives migration tooling a shell-free
  way to preserve semantic editorial source notes without activating package,
  PDF, citation, math-conversion, or syntax-highlighting dependency gates.
- HTML writer preview exports now map Pandoc's styled inline constructor
  branches.
  `examples/wordpress-html-writer-styled-inline-handoff.php` emits a
  reviewer-facing HTML preview where underline, deletion, small-caps,
  subscript, and superscript marks remain visible before the preview is wrapped
  in a WordPress HTML block. This gives migration tooling a shell-free way to
  preserve editorial marks and formula-style annotations during review without
  activating broader DOCX/ODT/PDF, citation, math-conversion, or syntax
  highlighting dependency gates.
- EPUB3 package imports and exports now preserve bounded SMIL media-overlay
  review metadata. `EpubReader` records OPF `media-overlay` links from XHTML
  spine items to SMIL resources, while `EpubWriter` can emit those links back
  into OPF and package SMIL resources as `application/smil+xml`. WordPress
  import/export queues can surface narrated-book synchronization evidence during
  review without executing playback, decoding audio, fetching remote resources,
  or shelling out to upstream Pandoc.
- EPUB3 package imports and exports now preserve HTML5 media resource
  references for `audio`, `video`, `source`, `track`, and `poster`. `EpubWriter`
  packages local video/audio/WebVTT resources with explicit OPF media types and
  rewrites chapter-relative URLs, while `EpubReader` records those packaged
  resources on read-back. WordPress review queues can keep source media bundles
  attached to imported EPUB chapters without fetching remote assets or shelling
  out to upstream Pandoc.
- EPUB3 package exports now add semantic note and pagebreak markers to spine
  XHTML. Generated note references, footnote containers/items, and classed
  pagebreak spans carry EPUB/ARIA semantics, which lets the import path recover
  generated footnote bodies as native notes during read-back instead of keeping
  empty note references.
- EPUB3 package exports now preserve explicit MathML payloads on math nodes and
  mark the generated XHTML spine item with the OPF `mathml` property. WordPress
  import/export review can keep source MathML equations attached to chapters
  and recover embedded TeX annotations on read-back without claiming generic
  TeX-to-MathML conversion.
- EPUB3 package exports now declare OPF `svg`, `scripted`, and
  `remote-resources` properties on XHTML spine items when the chapter AST
  contains inline SVG, script/event-handler content, or remote media/object
  references. WordPress review queues can surface package-risk/provenance
  metadata without fetching remote assets, rendering SVG, or executing scripts.
- EPUB3 package imports and exports now round-trip the core OPF Dublin Core
  metadata set, including contributors, publishers, dates, types, formats,
  source/relation links, coverage, and rights. WordPress import queues can keep
  provenance and licensing metadata attached to draft content without falling
  back to Native fixture handoffs or upstream Pandoc shell-outs.
- EPUB3 package imports and exports now preserve Dublin Core element ids and
  OPF attributes. Imported metadata records keep ids plus `opf:file-as`,
  `opf:role`, `opf:scheme`, `opf:authority`, and `opf:term` values alongside
  the simple title/author/lang fields, and EPUB output can re-emit those records
  without duplicating generated creators or breaking refinements such as
  `title-type` and `display-seq`.
- EPUB3 package imports and exports now preserve bounded OPF package-structure
  metadata: package attributes, selected unique identifiers, manifest fallback
  links, bindings, nested collections, and stable manifest ids for
  metadata-carried resources. WordPress review queues can keep package-level
  provenance and alternate-resource relationships visible without executing
  handlers, fetching external links, or shelling out to upstream Pandoc.
- EPUB3 package exports now normalize raw HTML fragments into XML-valid XHTML
  spine content. Browser-style void elements, boolean attributes, HTML named
  entities, and XML-sensitive script/style text are normalized before packaging,
  so WordPress-sourced raw HTML media/form fragments can survive EPUB3 export
  without producing malformed spine XML.
- EPUB3 package imports and exports now preserve fixed-layout XHTML viewport
  metadata. Imported `<meta name="viewport">` dimensions are recorded on spine
  item metadata, and EPUB exports write per-spine viewport tags for split
  fixed-layout chapters, so WordPress review/export queues can keep page-size
  intent without shelling out to upstream Pandoc or a browser renderer.
- EPUB3 package exports now preserve hierarchical `nav.xhtml` structure for
  generated TOCs and metadata-provided page lists/landmarks. WordPress export
  queues can keep chapter/section and review-page hierarchy in EPUB navigation
  while the read-back path recovers the same levels without shelling out to
  upstream Pandoc.
- EPUB3 package imports and exports now preserve non-clickable navigation
  parent labels. Imported nav `<span>` labels are recorded as no-href entries
  rather than discarded, and metadata-provided TOCs can render those entries
  back as typed `<span>` parents with nested linked chapters. WordPress review
  queues can keep part/section hierarchy even when the parent is not a reading
  target.
- EPUB3 package imports and exports now preserve per-spine XHTML language and
  direction metadata. Imported `lang`/`xml:lang` and `dir` values are recorded
  on spine item metadata, and EPUB exports write per-spine `lang`,
  `xml:lang`, and `dir` attributes for split chapters, so multilingual and
  RTL/LTR mixed WordPress review queues can keep page-level reading direction
  intent without shelling out to upstream Pandoc.
- EPUB3 package exports now preserve resource-backed non-linear XHTML spine
  items. Metadata-carried appendices, indexes, or other auxiliary XHTML
  resources can be packaged, referenced from OPF with `linear="no"`, linked from
  landmarks, and recovered as non-linear provenance on read-back without
  injecting those resources into the WordPress reading body.
- EPUB3 package imports and exports now preserve OPF metadata
  properties/refinements and package metadata links. WordPress review queues can
  keep identifier-type refinements, accessibility metadata, language/direction
  hints, and linked record references visible in package metadata without
  shelling out to upstream Pandoc or fetching external records.
- EPUB3 package imports and exports now preserve per-spine OPF rendition
  metadata refinements. Fixed-layout or mixed-layout source EPUBs can keep
  `rendition:layout`, `rendition:orientation`, `rendition:spread`, and
  refined `rendition:flow`
  refinements attached to the correct spine item, and generated EPUB packages
  re-target those refinements to the current chapter manifest ids instead of
  leaking stale source-package ids.
- EPUB3 package exports now preserve valid source manifest ids for generated
  linear XHTML spine chapters. Metadata-carried `spineManifestIds`,
  `epubSpineManifestIds`, or linear `epubSpineItemRefs` can keep safe source
  `idref` values in generated OPF manifest/spine entries, while non-linear,
  invalid, duplicate, and reserved ids fall back to unique generated
  `chapter-N` ids.
- EPUB3 package imports and exports now preserve manifest properties for linear
  XHTML spine items. Imported OPF item `properties` are exposed as
  `manifestProperties` on spine metadata, and generated chapter manifest items
  merge those source properties with auto-detected `mathml`, `svg`, `scripted`,
  and `remote-resources` values.
- EPUB3 package imports and exports now preserve narrated-book media-overlay
  package/item metadata and `rendition:flow` reading-mode intent. WordPress
  import/export queues can keep narrator/duration/active-class metadata plus
  paginated or scrolled spine behavior attached to drafts without running audio
  playback, fetching remote resources, or shelling out to upstream Pandoc.
- EPUB3 package imports and exports now preserve OCF sidecars for
  `META-INF/encryption.xml`, `metadata.xml`, `rights.xml`, and
  `signatures.xml`. WordPress review queues can see encrypted resource
  references and encryption algorithms, and export can carry extracted sidecar
  payloads back into the package without incorrectly manifesting those
  sidecars as normal EPUB assets.
- EPUB3 package imports and exports now preserve OCF `container.xml` metadata
  beyond the selected OPF path. WordPress review queues can inspect the full
  rootfile matrix, OPF media-type parameters, selected rootfile marker, and
  container record links, while generated packages can emit metadata-provided
  container links without fabricating alternate OPF rootfiles.
- EPUB3 package imports now preserve semantic XHTML spine descendant metadata.
  Imported chapter sections, footnote asides, pagebreak spans, and similar
  source wrappers with EPUB/ARIA/role/id/class/title/lang/dir/hidden attributes
  are recorded on `epubSpineItemRefs[*].semanticElements` with normalized text
  excerpts, so WordPress review queues can inspect chapter/footnote/page
  provenance even when the visible block body is simplified.
- EPUB3 package imports now preserve auxiliary `nav.xhtml` lists for
  illustrations, tables, audio, and video. EPUB3 `loi`, `lot`, `loa`, and
  `lov` sections are recorded as `epubAuxiliaryNavSections` with section
  attributes, headings, normalized entries, and counts, so WordPress review
  queues can inspect non-TOC navigation instead of losing it at ingest.
- EPUB3 package exports now emit metadata-provided auxiliary `nav.xhtml`
  sections back into generated packages. `epubAuxiliaryNavSections` records can
  generate `loi`, `lot`, `loa`, and `lov` nav roots with headings, section
  attributes, linked labels, and span-only labels, with read-back preserving
  the same section and entry metadata.
- EPUB3 package imports and optional NCX exports now preserve NCX
  `playOrder` values. Source `navPoint` and `pageTarget` order metadata is
  recorded on TOC and page-list entries, merged into matching `nav.xhtml`
  metadata when both navigation resources exist, and reused when generating
  compatibility `toc.ncx` output.
- EPUB3 package imports and optional NCX exports now preserve NCX document
  metadata. Source `dtb:uid`, `dtb:depth`, page-count records, custom NCX head
  metadata, `docTitle`, and `docAuthor` records are exposed as `epubNcx*`
  metadata and reused when generating compatibility `toc.ncx` output.
- EPUB3 package imports now preserve HTML5 semantic descendant metadata from
  XHTML spine documents. `details`/`summary`, media resources, captions,
  object/embed sources, `srcset`, data/meter/output values, ruby readings, and
  MathML display hints are recorded on `epubSpineItemRefs[*].semanticElements`,
  so WordPress ingestion can inspect and route source-specific semantics instead
  of relying only on flattened text or raw HTML blocks.
- EPUB3 package imports now convert path-qualified local note references such
  as `chapter.xhtml#fn1` into native notes when the referenced footnote
  definition is present in the same spine document. EPUB-typed backlinks are
  removed before note body conversion, so WordPress output keeps a single
  generated backlink instead of duplicating source return links.
- EPUB3 package exports now use package-level `epubRenditionViewport` and
  `renditionViewport` metadata as XHTML chapter viewport fallbacks. Fixed-layout
  drafts that only carry OPF `rendition:viewport` intent now emit matching
  chapter `<meta name="viewport">` tags without needing duplicate
  `epubViewport` metadata.
- EPUB3 package imports and exports now preserve body rowspans across row-head
  columns. WordPress review/export queues can keep first-column table header
  semantics when a row-head cell spans later rows, and read-back preserves
  `rowHeadColumns`, `rowspan`, `colspan`, alignments, widths, captions, header
  background styles, and review `data-*` attributes instead of shifting later
  cells into the wrong logical column.
- EPUB3 package imports and exports now preserve link-level navigation metadata
  on clickable `nav.xhtml` entries. WordPress review/export queues can keep TOC,
  landmark, page-list, and auxiliary-nav anchor `rel`, `hreflang`, `media`, and
  `target` fields attached to their source labels, while non-clickable nav
  parents remain span labels without anchor-only attributes.
- EPUB3 package imports now surface OCF top-level `mimetype` local ZIP header
  metadata. WordPress intake can record `localHeaderName` and
  `localHeaderExtraBytes` in `epubOcfMimetype`, and can flag
  `ocf-mimetype-extra-field` when an otherwise readable, first, stored, and
  byte-exact EPUB mimetype entry violates the OCF no-extra-field requirement.
- EPUB3 package imports now surface OCF `container.xml` metadata diagnostics.
  WordPress intake can preserve malformed rootfile records for review, flag
  invalid container root/version metadata, flag missing or invalid rootfile
  `full-path` and `media-type` attributes, and still import readable fallback
  OPF packages instead of dropping the whole EPUB at the first malformed
  rootfile entry.
- EPUB3 package imports now surface OCF container link diagnostics. WordPress
  intake can preserve malformed `container.xml` link records for review, flag
  missing `href`, missing `rel`, invalid `media-type`, and missing local
  metadata resources, while remote metadata links remain external references
  instead of false missing-ZIP-entry errors.
- EPUB3 package imports now surface OPF guide-reference diagnostics. WordPress
  intake can preserve legacy guide/cover/start-page records for review, flag
  missing guide `type`/`href` values, missing local guide resources, and
  unresolved local guide fragments, while remote guide hrefs remain external
  references instead of false missing-ZIP-entry errors.
- EPUB3 package imports now surface OPF metadata ID diagnostics. WordPress
  intake can preserve package metadata records while flagging invalid metadata
  IDs and duplicate metadata IDs with enough context to route records into
  title, creator, collection, external-record, or review metadata fields.
- EPUB3 package imports now surface OPF metadata `refines` syntax diagnostics.
  WordPress intake can distinguish malformed refinement pointers from
  well-formed `#id` references whose package target is missing.
- EPUB3 package imports now surface OPF collection metadata `refines` syntax
  diagnostics. WordPress intake can distinguish malformed collection-local
  refinement pointers from well-formed `#id` references that leave the
  containing collection.

## Next Task

Continue `epub3-package-core` after the OPF collection metadata refines diagnostics slice
with richer XHTML spine conversion, additional navigation/table edge cases,
generic TeX-to-MathML conversion decisions, deeper footnote edge cases,
fixed-layout/layout-style refinements, EPUBCheck/full fixture validation,
multi-rendition authoring beyond preserved OPF payloads, and EPUB2-specific
output decisions if that upstream output token is in scope.
