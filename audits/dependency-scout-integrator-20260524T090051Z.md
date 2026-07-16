# Dependency Scout Integrator - 2026-05-24T090051Z

Scope: integrated tracker/status implications from `audits/doc-format-dependency-scout-20260524T085334Z.md` and `audits/shared-runtime-dependency-scout-20260524T085334Z.md`. I did not edit lane files, source code, dashboard artifacts, dashboard scripts, test runners, `porting.html`, or `porting-summary.json`. I did not run the root PHP test runner or dashboard generator, push, inspect secrets, inspect process environments, inspect credential stores, inspect provider configs, or touch cloud/browser auth state.

## Tracker Changes

- Added `url-percent-encoding-core` as a bounded native PHP support-library candidate for WebDAV URL escaping, Git URL parsing/rendering, esbuild asset URLs, and LightningCSS URL/import tokens.
- Added `sequence-diff-merge-core` as a bounded native PHP support-library candidate for shared edit-script and hunk-building primitives across Difftastic, Gitoxide, Dolt, and Quadrable.
- Sharpened document/PDF activation gates and expectations for ZIP packages, DOCX/OpenXML, legacy DOC/CFB, EPUB, ODT/OpenDocument, PDF text dictionaries, table geometry, XML/HTML DOM, charset decoding, math/TeX, and citation/CSL rows.
- Sharpened runtime activation gates and expectations for glob/pathspec matching, checksum/hash primitives, archive/compression streams, provider metadata normalization, and SQL/storage codecs.
- Kept every support-library row gated and inactive; no row was marked active and no whole application, shell-out, live service, parser-generator runtime, credential-bearing config, or broad generic abstraction is counted as progress.

## Backlog Snapshot

- Item count: 31.
- Status split: 21 `candidate`, 10 `deferred`.
- Priority split: 4 `critical`, 23 `high`, 4 `medium`.
- New-row non-empty field check: `url-percent-encoding-core` and `sequence-diff-merge-core` both have non-empty `activationGate`, `testExpectation`, `scopeBoundary`, and `reuseNotes`.

## Intentionally Deferred

- `glob-filter-pathspec-core` remains `deferred`/`medium`; the scout recommendation only promotes it to `candidate`/`high` once a concrete dialect slice is selected.
- `provider-metadata-normalization-core` and `sql-storage-codec-core` remain deferred until their exact local-only provider/config or storage-codec gates open.
- Optional document rows such as ODT, math/TeX, and citation/CSL received higher priority where recommended, but remain deferred until a concrete base-lane gate opens.
- No dashboard regeneration or root PHP run was performed because this lane was tracker/status-only.

## Validation Snapshot

- `jq empty dependency-backlog.json`: passed.
- Duplicate-ID check over `dependency-backlog.json`: no duplicate IDs.
- Item count check: 31.
- Status split check: 21 `candidate`, 10 `deferred`.
- New-row required field check: passed.
