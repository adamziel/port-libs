Slice: `plib-xhr7e` EPUB3 package ingestion core blocker.

Scope: native PHP EPUB3 OPF package ingestion under `lanes/pandoc` only.

Implemented one bounded package-ingestion gap: OPF collection links now carry
the same vocabulary-token review surface as OPF metadata package links. This
keeps collection-local `rel` and `properties` tokens inspectable for package
review queues, including package prefix resolution, absolute URL-with-fragment
tokens, duplicate tokens, unknown prefixes, invalid token diagnostics, and
WordPress summary propagation.

Also covered collection link handoff details that were previously thinner than
package links: `hreflang`, element `xml:lang`, `dir`, and `refines` subject
provenance now survive in parsed collection link records.

Non-overlap: this does not repeat accepted package metadata links, OCF metadata
links, collection link target/suffix policy, package remote-resource policy,
manifest fallback chains, media overlays, bindings, guide references, nav/page
lists, rootfile provenance, encryption, OCF sidecars, or manifest media-type
parameter work.

Verification:

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests`

Result after rebasing on current main `d504ad4468`: focused
`EpubPackageTest.php` passed 1 test file, 1621 assertions, 0 failures.
Full Pandoc lane passed 44 test files, 67230
assertions, 0 failures.
