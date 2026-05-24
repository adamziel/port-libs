# Independent Audit - 2026-05-24T20:47Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, `dependency-backlog.json`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`, and
recent Git history through `7a578dd1 Record Quadrable handoff rejection`.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T20:40:55Z -> 2026-05-24T20:47:08Z
HEAD during audit: 57fc4d7d00f3 -> 6bc3d986f021 -> 7a578dd16262
recent history: 7a578dd1 Record Quadrable handoff rejection; 6bc3d986 Record Gitoxide handoff rejection; 57fc4d7d Refresh independent audit status; 2944e876 Record Syncthing handoff rejection; 9ec80339 Refresh independent audit status; 6bd136e9 Record Syncthing handoff rejection
tracked status rows: 221 -> 220 -> 222
default status rows including untracked: 23190 -> 23192 -> 23224
dirty shortstat: 221 files changed, 183931 insertions(+), 22936 deletions(-) -> 220 files changed, 183855 insertions(+), 22936 deletions(-) -> 222 files changed, 184983 insertions(+), 23436 deletions(-)
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
dependency backlog: 37 rows, 0 active (blocked 1, candidate 25, deferred 11)
root process gate samples: initially no rows; mid-audit pgrep matched 1795494 php tools/run-tests.php; final pgrep returned no rows
root owner evidence: 1795494 claude 1791967 Rs 00:21 php tools/run-tests.php
root run by this audit: not started
```

I did not start `php tools/run-tests.php`. The exact no-argument root gate was
clear when first sampled, but the checkout was not stable enough: `HEAD`, dirty
row counts, and shortstat changed during the audit. New integration-status
commits rejected rather than accepted Gitoxide (`6bc3d986`) and Quadrable
(`7a578dd1`) handoffs. Before finish, a non-audit no-argument root harness
appeared as PID `1795494`, owned by `claude`; it later exited, and I did not
start a duplicate.

Latest sampled live manifest/status counts. These are moving-worktree samples,
not accepted progress:

```text
lane          manifest mapped/total     status phpPass/phpFail
difftastic    1158/1298                 3768/0
dolt          613/613                   464/0
esbuild       224/2567                  224/0
gitoxide      1472/2877                 7609/0
libsqlite     218/1589                  218/0
LightningCSS  3011/3548                 4390/0
markerPDF     166/78                    271/0
pandoc        2276/2276                 403/0
quadrable     55/55                     260/0
rclone        489/1601                  489/0
Readability   1609/1984                 148/0
syncthing     658/658                   9170/0
```

## Findings

1. **Critical - repo-wide acceptance evidence is still invalid because the checkout moved during the audit.**
   - Paths: `tools/run-tests.php`, `progress.md`, `audits/integration-status.md`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: small reviewable committed slices, verify tests
     before integration/reassignment, and record repo-wide checks honestly.
   - Evidence: `HEAD` moved from `57fc4d7d00f3` to `6bc3d986f021` and then
     `7a578dd16262` while this audit was running. Default status rows moved
     `23190 -> 23192 -> 23224`, tracked rows moved `221 -> 220 -> 222`, and
     dirty shortstat changed from `221 files changed, 183931 insertions(+),
     22936 deletions(-)` to `222 files changed, 184983 insertions(+), 23436
     deletions(-)`. The exact root process gate was initially clear, then
     matched active PID `1795494` with owner evidence `1795494 claude 1791967
     Rs 00:21 php tools/run-tests.php`; it later cleared. A root run from this
     moving tree would not prove the accepted snapshot, and starting another
     while that PID was active would have violated the duplicate-root gate.

2. **Critical - the live tree is still a broad unaccepted dirty aggregate, not a queue of isolated accepted slices.**
   - Paths: `lanes/difftastic/lane-status.json`,
     `lanes/dolt/lane-status.json`, `lanes/esbuild/lane-status.json`,
     `lanes/gitoxide/lane-status.json`, `lanes/libsqlite/lane-status.json`,
     `lanes/lightningcss/lane-status.json`,
     `lanes/markerpdf/lane-status.json`, `lanes/pandoc/lane-status.json`,
     `lanes/quadrable/lane-status.json`, `lanes/rclone/lane-status.json`,
     `lanes/readability/lane-status.json`, `lanes/syncthing/lane-status.json`,
     `audits/integration-status.md`.
   - Goal requirement at risk: implement native PHP incrementally, commit small
     reviewable slices with passing tests, and integrate only after verification.
   - Evidence: every current lane status still reports `pending`,
     `uncommitted`, or supervisor/integrator root acceptance pending in
     `latestCommit`/`blocker`. The latest history reinforces that point:
     `2944e876` rejected Syncthing, `6bc3d986` rejected Gitoxide because the
     advertised cross-FS slice was coupled to an accumulated untracked discovery
     stack, and `7a578dd1` rejected Quadrable because the proof-patch claim was
     mixed with unrelated dirty proof transport, noTrack, iterator/checkpoint,
     sync, raw-LMDB, path-display, and current-head work. The current dirty tree
     still has 222 tracked dirty rows and 23,002 untracked files.

3. **High - `porting.html` is a stale accepted-snapshot dashboard and materially disagrees with live lane metadata.**
   - Paths: `porting.html`, `porting-summary.json`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: dashboard must show visible status by upstream
     denominator, mapped tests, PHP pass/fail, phase, audit, blocker, and commit.
   - Evidence: the dashboard still publishes source snapshot
     `main 5e46840f9573`, generated `2026-05-24 20:23:24 UTC`. Live metadata has
     advanced beyond it: Difftastic dashboard `240/586` versus live
     `1158/1298`; Dolt `303/613` versus `613/613`; Gitoxide `1432/2877` versus
     `1472/2877`; LightningCSS `886/3532` versus `3011/3548`; markerPDF
     `165/78` versus `166/78`; Pandoc `619/2276` versus `2276/2276`; rclone
     dashboard denominator `2553` versus live `1601`; Readability `1563/1984`
     versus `1609/1984`; Syncthing dashboard PHP `324` versus live `9170`.
     The page is useful as an accepted baseline, but it must not be read as live
     progress.

4. **High - support-library coverage is still backlog-only while current lane work is crossing support boundaries.**
   - Paths: `dependency-backlog.json`, `porting.html`,
     `lanes/dolt/lane-status.json`, `lanes/libsqlite/lane-status.json`,
     `lanes/markerpdf/lane-status.json`, `lanes/rclone/lane-status.json`,
     `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: support libraries need lane-level granularity:
     bounded native PHP component, activation gate, dependency-specific upstream
     or spec denominator, mapped fixtures, PHP pass/fail evidence,
     malformed/corrupt cases, and no shell-out/whole-application credit.
   - Evidence: `dependency-backlog.json` has 37 rows and 0 active bounded
     support ports. Current lane-local work already touches likely support
     boundaries: rclone WebDAV LOCK/If-header behavior while
     `webdav-protocol-core` is inactive; markerPDF ToUnicode/CMap searchable
     text while `pdf-text-dictionary-core` is inactive; Dolt JSON_MERGE_PATCH and
     libsqlite `json_quote` while `json-json5-document-core` and
     `sql-expression-semantics-core` remain candidate-only; Syncthing global
     discovery URL/query encoding while `url-percent-encoding-core` still does
     not list Syncthing as a consumer. These may stay lane-local while
     unaccepted, but they should not become accepted rich-function progress
     without activating or explicitly declining the corresponding bounded support
     row.

5. **High - Pandoc rich document-conversion status is overstated by a fully mapped manifest.**
   - Paths: `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/lane-status.json`, `dependency-backlog.json`,
     `porting.html`.
   - Goal requirement at risk: Pandoc requires a document conversion kernel with
     Markdown, HTML, WXR, EPUB/PDF-oriented intermediate forms, and WordPress
     block output, backed by meaningful upstream parity and explicit blockers.
   - Evidence: live Pandoc reports `2276/2276` mapped and `403/0` PHP evidence,
     but the full Haskell upstream runner remains unexecuted. The required
     latest rich areas remain inactive support rows: DOC/CFB,
     DOCX/OpenXML, PDF input/text extraction, PDF output handoff, EPUB,
     ODT/OpenDocument, templates, citations, math, tables, package containers,
     XML/HTML, Unicode/charset, JSON/YAML metadata, syntax highlighting, and
     archive/compression. None has an accepted support manifest, PHP ledger,
     malformed/corrupt evidence, activation record, or bounded install-attempt
     note.

6. **High - markerPDF is now exercising real PDF text dependencies while the denominator and support evidence remain non-normalized.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/lane-status.json`,
     `lanes/markerpdf/src/PdfTextExtractor.php`,
     `lanes/markerpdf/src/ChunkConversionPlanner.php`,
     `lanes/markerpdf/src/MarkerRuntimePlanner.php`, `dependency-backlog.json`.
   - Goal requirement at risk: markerPDF must become a native PDF-to-structured
     content extraction pipeline; external runtime planning and converter/model
     shell-outs cannot count as native progress.
   - Evidence: live markerPDF maps `166` focused semantics against a denominator
     of `78` tracked upstream repository paths. The new ToUnicode CMap handoff is
     the right kind of native searchable-PDF text work, but it is still pending
     and explicitly not accepted. Broader PDF text, page/layout, OCR/layout, and
     table claims remain behind inactive `pdf-text-dictionary-core`,
     `pdf-page-render-plan-core`, `layout-ocr-result-core`, and
     `table-geometry-core` rows. Runtime and chunk planners are useful
     quarantine boundaries, not native conversion progress.

7. **Medium - manifest, status, and dashboard count units are still mixed.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`, `porting-summary.json`.
   - Goal requirement at risk: coordination must track upstream denominator,
     mapped tests, PHP pass/fail, phase, blocker, and commit in comparable
     units.
   - Evidence: markerPDF reports more mappings than its denominator (`166/78`).
     Pandoc reports `2276/2276` mapped while the status has `403` PHP behavior
     tests. Rclone's live denominator is `1601`, while the dashboard still shows
     `2553`. Several `phpPass` values are assertions, some are behavior cases,
     and some are test-file ledgers. Percent estimates cannot be audited until
     upstream artifacts, mapped behavior units, PHP assertions, PHP behavior
     cases, and accepted commit state are split explicitly.

8. **Medium - shell-out and bridge-adjacent scaffolding still needs strict quarantine from progress credit.**
   - Paths: `lanes/difftastic/tests/TokenDifferTest.php`,
     `lanes/gitoxide/tests/FetchResponseTest.php`,
     `lanes/gitoxide/tests/GitIndexUntrackedCacheTest.php`,
     `lanes/gitoxide/src/SshReceivePackTransport.php`,
     `lanes/gitoxide/src/GitFilterDriver.php`,
     `lanes/gitoxide/src/CredentialProgram.php`,
     `lanes/markerpdf/src/ChunkConversionPlanner.php`,
     `lanes/markerpdf/src/MarkerRuntimePlanner.php`.
   - Goal requirement at risk: bridge code, generated fixtures, and shell-outs
     may exist only as temporary oracle tooling and must not count as native
     implementation progress.
   - Evidence: the tree still contains `shell_exec` example invocations in
     Difftastic tests, `proc_open`-based Git oracle/test helpers, Git SSH/filter
     and credential command planners, and markerPDF runtime/chunk conversion
     planners. Some are legitimate oracle or planning boundaries, but manifests
     and status rows should keep them out of native pass/mapped counts unless
     the native path is independently tested and the bridge role is explicit.

## Required Next Intervention

Freeze writers/status publishers and handoff markers long enough for two stable
polls, then accept or reject exactly one owner-free reduced lane batch whose
dirty files match its evidence. Normalize that lane's manifest/status count
units before publishing it, and activate a bounded support-library row only if
the accepted slice is ready for or blocked by that dependency. Regenerate
`progress.md`, `porting.html`, and `porting-summary.json` from the accepted
commit. Only after the exact `pgrep -af '^php tools/run-tests\.php$'` gate is
empty and the tree remains frozen should one serialized no-argument
`php tools/run-tests.php` run be used as repo-wide acceptance evidence.
