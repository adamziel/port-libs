# Independent Audit - 2026-05-24T22:39Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `dependency-backlog.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`, and recent Git history through `2e8fae5cac6a`.

I did not edit lane implementation files, launch agents or tmux sessions, push, read secrets, inspect process environments, credential stores, provider configs, or auth files. Bridge code, generated fixtures, shell-outs, whole applications, external converter wrappers, and hidden process launchers are treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC sample: 2026-05-24T22:39:04Z
HEAD: 2e8fae5cac6a
recent history: 2e8fae5c Refresh independent audit status; fdae6bf7 Refresh capacity queue source freshness; 83dde5f9 Add lane group integrator workflow; bf77aeb5 Add isolated lane worker workflow; f292e50e Refresh independent audit status; 2392e5c5 Refresh independent audit status; cf61fae5 Refresh esbuild integration status; 6cb369fd Integrate esbuild resolver slice; eb1222be Refresh independent audit status; 9cc0ffe3 Refresh independent audit status
status rows including untracked: 25113
tracked dirty rows: 249
dirty shortstat: 249 files changed, 196822 insertions(+), 25624 deletions(-)
targeted status: all 12 lane manifests and all 12 lane-status files are dirty
exact no-argument root process gate at audit sample: active PID 2416152: `php tools/run-tests.php`
pre-commit root process recheck: no exact no-argument root PID
root run by this audit: not started; the earlier active root PID prevented a duplicate during review, and the later empty gate still found a checkout that is not a frozen acceptance snapshot
dependency backlog: 37 rows; blocked 1, candidate 25, deferred 11; 0 active support ports
porting.html source snapshot: `main 6cb369fd15d0`, generated `2026-05-24 22:29:19 UTC`
```

Live dirty status samples are not accepted progress except where `latestCommit` already names an integrated commit:

```text
lane          live status evidence
Difftastic    pending QML object/property slice; focused PHP 3933 assertions / 0 failures
Dolt          not committed accumulated query-diff numeric/JSON/date/string stack; focused lane 3517 assertions / 0 failures
esbuild       last accepted commit remains 6cb369fd; new package-tsconfig slice is focused-only, 1791 assertions / 0 failures
Gitoxide      pending tree-entry ordering plus accumulated discovery/mailmap/object stack; full lane PHP 7734 assertions / 0 failures
libsqlite     pending abs/string scalar plus JSON aggregate/table/mutation stack; focused file 1923 assertions / 0 failures
LightningCSS  uncommitted gradient/attr formatter follow-up; status updated after native src/tests/examples work
markerPDF     pending PDF literal-string escape / inline-image stream work; latest accepted markerPDF commit remains older than live handoff
Pandoc        pending HTML reader standalone noscript slice; focused PHP 4455 assertions / 0 failures
Quadrable     pending docopt metadata unknown short-option slice inside broad dirty lane; full lane PHP 5678 assertions / 0 failures
rclone        pending WebDAV GET/HEAD/POST conditional-date/gzip/file-response plus earlier WebDAV stack; lane PHP 4881 assertions / 0 failures
Readability   uncommitted fifteen-slice fixture/import dirty pile; latest focused fixture evidence is blogger
Syncthing     pending expanded REST route-registry slice; full lane PHP 9368 assertions / 0 failures
```

## Findings

1. **Critical - the repository still lacks a stable aggregate acceptance baseline.**
   - Paths: `tools/run-tests.php`, `progress.md`, `audits/latest.md`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: periodically run repo-wide tests and commit small, reviewable slices with passing verification from a stable snapshot.
   - Evidence: current sample shows `25113` status rows including untracked files, `249` tracked dirty rows, `249 files changed`, and all 12 lane manifests/status files dirty. This is a mixed integration pile, not one accept/reject snapshot.

2. **Critical - a no-argument root harness was active during audit sampling.**
   - Paths: `tools/run-tests.php`, `audits/latest.md`, `progress.md`.
   - Goal requirement at risk: repo-wide verification must be serialized and tied to one frozen snapshot.
   - Evidence: `pgrep -af '^php tools/run-tests\.php$'` returned PID `2416152` running exactly `php tools/run-tests.php` during review. A later pre-commit gate was empty, but I still did not start a root run because the checkout is a mixed dirty handoff pile. The observed PID should not count as acceptance unless the integrator can prove it ran against a frozen accepted snapshot.

3. **Critical - accepted commits are being followed by broader unaccepted lane metadata.**
   - Paths: `lanes/esbuild/lane-status.json`, `lanes/pandoc/lane-status.json`, `lanes/markerpdf/lane-status.json`, `lanes/lightningcss/lane-status.json`, `lanes/gitoxide/lane-status.json`, `porting.html`.
   - Goal requirement at risk: accepted progress must be tied to the exact committed slice and must not launder later dirty handoffs into progress.
   - Evidence: recent commits accepted esbuild resolver and other focused slices, but live statuses now advertise package-tsconfig, noscript HTML, PDF string/image, formatter, tree/object/discovery, and other pending follow-ups that are not root-accepted.

4. **Critical - most lane statuses remain unaccepted worker output.**
   - Paths: `lanes/difftastic/lane-status.json`, `lanes/dolt/lane-status.json`, `lanes/gitoxide/lane-status.json`, `lanes/libsqlite/lane-status.json`, `lanes/lightningcss/lane-status.json`, `lanes/markerpdf/lane-status.json`, `lanes/pandoc/lane-status.json`, `lanes/quadrable/lane-status.json`, `lanes/rclone/lane-status.json`, `lanes/readability/lane-status.json`, `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: dirty worker handoffs must not count as native implementation progress until supervisor/integrator acceptance and verification occur.
   - Evidence: statuses say pending, uncommitted, not committed, or root/integrator owned. Several explicitly describe accumulated piles rather than one reviewable slice.

5. **High - `porting.html` is stale relative to live metadata and still overstates accepted parity.**
   - Paths: `porting.html`, `porting-summary.json`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: dashboard must show current accepted upstream denominator, mapped tests, PHP pass/fail, phase, audit, blocker, and commit.
   - Evidence: dashboard snapshot is `6cb369fd15d0`, while live `HEAD` is `2e8fae5c` and lane metadata is dirty. Average progress remains `93.3%` despite most live lane work being unaccepted and many upstream runners being static-only or bounded.

6. **High - support-library coverage is still routing, not first-class port progress.**
   - Paths: `dependency-backlog.json`, `progress.md`, `porting.html`.
   - Goal requirement at risk: support libraries require a bounded native PHP component, activation gate, dependency-specific upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, malformed/corrupt cases where relevant, and as much upstream/spec-suite evidence as can actually run.
   - Evidence: backlog has 37 rows, 0 active support ports, and no accepted dependency-specific pass/fail ledgers. Candidate/deferred rows are useful gates but not progress.

7. **High - Pandoc rich-format requirements are visible but not satisfied.**
   - Paths: `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, `lanes/pandoc/lane-status.json`, `dependency-backlog.json`, `porting.html`.
   - Goal requirement at risk: Pandoc must account for DOC, DOCX/OpenXML, PDF input/output handoff, EPUB, ODT/OpenDocument, citations, math, tables, templates, package containers, XML/HTML, Unicode/charset, JSON/YAML metadata, syntax highlighting, and archive/compression with bounded rows and real upstream/spec evidence.
   - Evidence: those areas are gated as candidate/deferred rows or reuse paths, but none is an active support port with its own denominator, malformed/corrupt coverage, or PHP pass/fail ledger. Pandoc lane behavior tests remain a narrow subset of the full static inventory.

8. **High - markerPDF still risks counting dependency planning as native extraction progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`, `lanes/markerpdf/lane-status.json`, `dependency-backlog.json`.
   - Goal requirement at risk: markerPDF must be a native PDF-to-structured-content pipeline; external runtime planning, supplied model callbacks, benchmark archive probing, and dependency inspection cannot count as port progress.
   - Evidence: markerPDF still carries PDF dictionary/filter/font/runtime/OCR/layout/table planning alongside native text extraction. The required support rows for PDF text dictionaries, page layout, OCR/layout result, tables, Unicode/charset, and archive/package handling remain inactive.

9. **High - base lanes keep crossing inactive support-library boundaries.**
   - Paths: `dependency-backlog.json`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`, `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: rich dependency work should not count as shared reusable progress unless a bounded row is activated, tested, and evidenced.
   - Evidence: esbuild resolver work is ahead of deferred `js-package-resolution-core`; rclone WebDAV/XML/gzip work is ahead of inactive `webdav-protocol-core`, `xml-html5-dom-core`, and `archive-compression-streams`; Dolt/libsqlite JSON and SQL expression work are ahead of inactive `json-json5-document-core` and `sql-expression-semantics-core`; Syncthing protocol work is ahead of inactive `protobuf-wire-core`; Gitoxide wire/hash/archive needs remain inactive rows.

10. **Medium - manifest/status schemas still make progress math unreliable.**
    - Paths: `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`, `porting-summary.json`, `porting.html`.
    - Goal requirement at risk: progress percentages must be comparable and tied to upstream denominator, mapped tests, PHP pass/fail, phase, blocker, and commit.
    - Evidence: manifests use per-lane nested counters, status `phpPass` often means assertions or behavior cases rather than mapped upstream tests, and markerPDF/Pandoc-style mapped counts can exceed or fully cover static inventory without comparable native behavior parity.

11. **Medium - Readability is too broad for one reviewable acceptance.**
    - Paths: `lanes/readability/UPSTREAM_TEST_MANIFEST.json`, `lanes/readability/lane-status.json`, `lanes/readability/tests/ArticleExtractorTest.php`.
    - Goal requirement at risk: prefer small correct slices over broad shallow ports, with mapped upstream tests and reviewable commits.
    - Evidence: the current status names fifteen interleaved fixture/import slices across shared files. Even with green Mozilla/PHP evidence, this needs integrator hunk splitting or an explicit preserved-work package before root acceptance.

12. **Medium - missing-package/full-suite evidence is not yet strong enough for support-row activation.**
    - Paths: `dependency-backlog.json`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`.
    - Goal requirement at risk: missing packages are not final blockers until bounded `sudo -n` installs are attempted or ruled out, and fixture-only credit is insufficient unless broader suites were attempted and honestly bounded.
    - Evidence: support rows do not yet record install attempts, spec-suite runner attempts, malformed/corrupt ledgers, or dependency-specific pass/fail counts.

## Root Harness Decision

I did not run `php tools/run-tests.php`. During review, the exact no-argument process gate was occupied by PID `2416152` (`php tools/run-tests.php`), so a duplicate root run was forbidden. A later pre-commit recheck was empty, but the tree remains too unstable for a meaningful audit-owned aggregate run: all lane manifests/status files are dirty and live statuses describe mixed pending handoffs rather than one frozen accepted batch.

## Next Intervention

Freeze writers/status publishers/dashboard regeneration/test-loop starters for two stable polls; wait for PID `2416152` to finish and record its result without counting it unless it matches a frozen snapshot; select exactly one owner-free reduced batch; run focused verification and `git diff --check`; run one serialized no-argument root harness only from that same frozen snapshot with an empty exact process gate; normalize manifest/status count units; keep support rows inactive unless a real accepted base-lane gate or blocker exists; regenerate dashboard artifacts from the accepted commit; then commit or reject.
