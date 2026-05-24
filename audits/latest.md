# Independent Audit - 2026-05-24T22:31Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `dependency-backlog.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`, and recent Git history through `cf61fae52782`.

I did not edit lane implementation files, launch agents or tmux sessions, push, read secrets, inspect process environments, credential stores, provider configs, or auth files. Bridge code, generated fixtures, shell-outs, whole applications, external converter wrappers, and hidden process launchers are treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC sample: 2026-05-24T22:31:07Z
HEAD: cf61fae52782
recent history: cf61fae5 Refresh esbuild integration status; 6cb369fd Integrate esbuild resolver slice; eb1222be Refresh independent audit status; 9cc0ffe3 Refresh independent audit status; cc740727 Integrate Pandoc HTML linebreak slice; 5fa9dbe6 Integrate markerPDF CMap codespace slice; c65d5e26 Integrate LightningCSS page rule formatter slice; 59f84374 Integrate Gitoxide signature consuming slice
default status rows including untracked: 25034
tracked dirty rows: 238
dirty shortstat: 238 files changed, 192796 insertions(+), 23259 deletions(-)
targeted status: all 12 lane manifests and all 12 lane-status files are dirty
initial exact root process gate: `pgrep -af '^php tools/run-tests\.php$'` returned no rows
root run by this audit: not started; the checkout is still a mixed moving handoff pile, not a frozen acceptance snapshot
dependency backlog: 37 rows, 0 active, 37 null dependency-specific upstream denominators
```

Live dirty manifest/status samples are not accepted progress except where `latestCommit` already names an integrated commit:

```text
lane          status phpPass/phpFail     latest status
difftastic    3921/0                     pending C/C++ preprocessor slice plus prior parser repairs
dolt          473/0                      not committed accumulated query-diff and JSON/math/date/string stack
esbuild       238/0                      accepted at 6cb369fd Integrate esbuild resolver slice
gitoxide      7725/0                     pending gix-object blob-write/compute-hash plus accumulated discovery/mailmap/object stack
libsqlite     240/0                      pending abs/string scalar plus JSON aggregate/table/mutation stack
LightningCSS  4419/0                     uncommitted attr formatter slice
markerPDF     282/0                      pending PDF inline-image payload skip handoff
pandoc        415/0                      pending HTML reader standalone object/embed slice
quadrable     263/0                      pending docopt metadata pre-open slice
rclone        529/0                      pending WebDAV GET/HEAD/POST plus earlier WebDAV stack
Readability   159/0                      uncommitted mixed fixture/import dirty pile
syncthing     9352/0                     pending system route registry slice
```

## Findings

1. **Critical - the repository still has no stable aggregate acceptance baseline.**
   - Paths: `tools/run-tests.php`, `progress.md`, `audits/latest.md`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: periodically run repo-wide tests and commit small, reviewable slices with passing verification from a stable snapshot.
   - Evidence: `HEAD` is `cf61fae52782`, but the checkout remains a mixed dirty pile: `25034` status rows including untracked files, `238` tracked dirty rows, `238 files changed`, and all 12 lane manifests plus all 12 lane-status files dirty. A root run from this state would not establish acceptance for any single lane slice.

2. **Critical - accepted commits are being followed immediately by broader unaccepted lane piles.**
   - Paths: `lanes/esbuild/lane-status.json`, `lanes/pandoc/lane-status.json`, `lanes/markerpdf/lane-status.json`, `lanes/lightningcss/lane-status.json`, `lanes/gitoxide/lane-status.json`, `progress.md`, `audits/integration-status.md`.
   - Goal requirement at risk: accepted progress must be tied to the exact committed slice and must not launder later dirty handoffs into progress.
   - Evidence: recent history accepted the esbuild resolver, Pandoc linebreak, markerPDF CMap, LightningCSS page-rule, and Gitoxide signature slices. Live statuses now advertise broader follow-up stacks: Pandoc standalone object/embed, markerPDF PDF inline-image work, LightningCSS attr formatting, Gitoxide blob-write/compute-hash plus discovery/mailmap/object work, and other accumulated dirty handoffs. Those later stacks remain pending root/integrator acceptance.

3. **Critical - nearly every live lane status remains unaccepted worker output.**
   - Paths: `lanes/difftastic/lane-status.json`, `lanes/dolt/lane-status.json`, `lanes/gitoxide/lane-status.json`, `lanes/libsqlite/lane-status.json`, `lanes/lightningcss/lane-status.json`, `lanes/markerpdf/lane-status.json`, `lanes/pandoc/lane-status.json`, `lanes/quadrable/lane-status.json`, `lanes/rclone/lane-status.json`, `lanes/readability/lane-status.json`, `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: dirty worker handoffs must not count as native implementation progress until supervisor/integrator acceptance and verification occur.
   - Evidence: all listed statuses say pending, uncommitted, not committed, or root/integrator owned. Several describe accumulated piles rather than one reviewable slice. The esbuild status is the only sampled lane status that names an accepted `latestCommit` for its current work.

4. **Critical - an audit-owned no-argument root run would be low-signal right now.**
   - Paths: `tools/run-tests.php`, `audits/latest.md`, `progress.md`.
   - Goal requirement at risk: repo-wide root verification must be serialized and tied to the same frozen snapshot being accepted.
   - Evidence: the exact no-argument process gate returned no rows, but the checkout failed the stability gate: `HEAD` moved since the prior audit, all lane manifest/status files are dirty, and current lane statuses remain pending or mixed. I did not run `php tools/run-tests.php` because the result would be a moving-pile smoke test rather than acceptance evidence.

5. **High - `porting.html` is stale relative to live accepted and dirty metadata.**
   - Paths: `porting.html`, `porting-summary.json`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: the dashboard must show current accepted upstream denominator, mapped tests, PHP pass/fail, phase, audit, blocker, and commit.
   - Evidence: `porting.html` still says verified source snapshot `0fa9ecafcd10` generated at `2026-05-24 21:19:36 UTC`, while live `HEAD` is `cf61fae52782`. The dashboard also predates the accepted esbuild resolver commit and all current dirty lane metadata.

6. **High - support-library rows are visible but still not first-class support ports.**
   - Paths: `dependency-backlog.json`, `progress.md`, `porting.html`.
   - Goal requirement at risk: support libraries require the same granularity as lanes: bounded native PHP component, activation gate, dependency-specific upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, malformed/corrupt cases where relevant, and as much upstream/spec-suite evidence as can actually run.
   - Evidence: the backlog has `37` rows, `0` active rows, and `37` null `upstreamDenominator` values. These are routing notes, not accepted support-library progress. Missing packages are not final blockers until bounded `sudo -n` installs are attempted or ruled out, but no row currently records that runner ledger.

7. **High - Pandoc rich-format coverage remains over-credited even after the accepted linebreak slice.**
   - Paths: `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, `lanes/pandoc/lane-status.json`, `dependency-backlog.json`, `porting.html`.
   - Goal requirement at risk: Pandoc must provide a document conversion kernel with meaningful readers/writers and upstream-backed rich-format support, not fixture routing.
   - Evidence: the lane status reports only `415` PHP behavior tests while the manifest still presents broad upstream mapping. DOC, DOCX/OpenXML, PDF input/output handoff, EPUB, ODT/OpenDocument, citations, math, tables, templates, package containers, XML/HTML, Unicode/charset, JSON/YAML metadata, syntax highlighting, and archive/compression remain visible as gated rows/reuse paths, but none is an active bounded support port with a denominator, pass/fail ledger, malformed/corrupt coverage, or bounded install/runner ledger.

8. **High - markerPDF still mixes dependency planning and native extraction progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`, `lanes/markerpdf/lane-status.json`, `dependency-backlog.json`.
   - Goal requirement at risk: markerPDF must become a native PDF-to-structured-content extraction pipeline; external runtime planning, supplied converter/model callbacks, benchmark archive probing, and dependency inspection cannot count as native conversion progress.
   - Evidence: markerPDF still reports more mapped behavior than its upstream path denominator and carries stream/filter, font, runtime, app, model, benchmark, output, OCR/layout, and table-dependency planning entries. The accepted CMap/stream-filter work helps searchable text extraction, but broader PDF text dictionaries, page/layout planning, OCR/layout results, table geometry, Unicode/charset, and archive/package concerns remain inactive support rows.

9. **High - base lanes are crossing inactive support-library boundaries without activating shared rows.**
   - Paths: `dependency-backlog.json`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`, `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: rich dependency work should not count as shared reusable progress unless a bounded row is activated, tested, and evidenced.
   - Evidence: the accepted esbuild resolver slice is ahead of deferred `js-package-resolution-core`; rclone WebDAV/XML work is ahead of inactive `webdav-protocol-core` and `xml-html5-dom-core`; Dolt and libsqlite JSON work is ahead of inactive `json-json5-document-core` and `sql-expression-semantics-core`; Syncthing BEP/session work is ahead of inactive `protobuf-wire-core`; Gitoxide protocol/discovery work remains ahead of inactive `git-wire-protocol-core`, checksum/hash, and URL support rows.

10. **Medium - manifest/status count units remain non-comparable.**
    - Paths: `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`, `porting-summary.json`, `porting.html`.
    - Goal requirement at risk: progress percentages must be comparable and tied to upstream denominator, mapped tests, PHP pass/fail, phase, blocker, and commit.
    - Evidence: markerPDF reports mapped counts above its upstream path denominator; Pandoc reports broad mapping while status has `415` behavior tests; Syncthing, Gitoxide, Difftastic, and LightningCSS `phpPass` values are assertion-like counts; rclone and Dolt mix behavior tests, assertions, selected upstream functions, and static path inventories.

11. **Medium - Readability's fixture mapping is improving but the dirty pile is not reviewable as one narrow slice.**
    - Paths: `lanes/readability/UPSTREAM_TEST_MANIFEST.json`, `lanes/readability/lane-status.json`, `lanes/readability/tests/ArticleExtractorTest.php`.
    - Goal requirement at risk: prefer small correct slices over broad shallow ports, with mapped upstream tests and reviewable commits.
    - Evidence: status describes a mixed uncommitted fixture/import pile. Even with focused Mozilla fixture evidence, this should be accepted only after integrator hunk splitting or an explicit preserved-work package followed by root verification from that frozen package.

12. **Medium - support rows should not be activated by fixture-only base-lane evidence.**
    - Paths: `dependency-backlog.json`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`.
    - Goal requirement at risk: support-library work needs as much upstream/spec-suite evidence as can actually run; fixture-only credit is insufficient unless the broader suite was attempted and honestly bounded.
    - Evidence: Pandoc package formats, rclone WebDAV/XML/file responses, and markerPDF PDF dictionaries/layout/table needs are all currently represented by base-lane fixtures or planning notes. None records a dependency-specific upstream/spec denominator, mapped malformed/corrupt cases, or a bounded `sudo -n` install/runner attempt where missing packages block fuller evidence.

## Root Harness Decision

I did not run `php tools/run-tests.php`. The exact no-argument process gate was clear, but the tree was not stable enough: all lane manifests/status files are dirty, current lane statuses remain pending or mixed, and the worktree is not a frozen accept/reject snapshot. A no-argument root result here would be smoke evidence for an unattributable pile, not acceptance evidence for the original goal.

## Next Intervention

Freeze writers/status publishers/dashboard regeneration/test-loop starters for two stable polls; select exactly one owner-free reduced batch; run focused verification and `git diff --check`; run one serialized no-argument root harness only from that same frozen snapshot with an empty exact process gate; normalize manifest/status count units; keep support rows inactive unless a real accepted base-lane gate or blocker exists; regenerate dashboard artifacts from the accepted commit; then commit or reject.
