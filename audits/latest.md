# Independent Audit - 2026-05-24T22:36Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`, `dependency-backlog.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`, and recent Git history through `f292e50e623a`.

I did not edit lane implementation files, launch agents or tmux sessions, push, read secrets, inspect process environments, credential stores, provider configs, or auth files. Bridge code, generated fixtures, shell-outs, whole applications, external converter wrappers, and hidden process launchers are treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC sample: 2026-05-24T22:36:09Z
HEAD: f292e50e623a
recent history: f292e50e Refresh independent audit status; 2392e5c5 Refresh independent audit status; cf61fae5 Refresh esbuild integration status; 6cb369fd Integrate esbuild resolver slice; eb1222be Refresh independent audit status; 9cc0ffe3 Refresh independent audit status; cc740727 Integrate Pandoc HTML linebreak slice; 5fa9dbe6 Integrate markerPDF CMap codespace slice; c65d5e26 Integrate LightningCSS page rule formatter slice; 59f84374 Integrate Gitoxide signature consuming slice
default status rows including untracked: 25095
tracked dirty rows: 244
dirty shortstat: 253 files changed, 195356 insertions(+), 24314 deletions(-)
targeted status: all 12 lane manifests and all 12 lane-status files are dirty
initial exact root process gate: `pgrep -af '^php tools/run-tests\.php$'` returned no rows
final exact root process gate: active PID 2416152 owned by claude, PPID 2413150, elapsed 00:10, state R: `php tools/run-tests.php`
root run by this audit: not started; an active root harness appeared before finish and the checkout is not a frozen acceptance snapshot
dependency backlog: 37 rows, 0 active, 37 null dependency-specific upstream denominators
dashboard source snapshot: `porting.html` says `main 6cb369fd15d0`, generated `2026-05-24 22:29:19 UTC`
```

Live dirty status samples are not accepted progress except where `latestCommit` already names an integrated commit:

```text
lane          status phpPass/phpFail     latest status
difftastic    3933/0                     pending QML object/property slice
dolt          473/0                      not committed accumulated query-diff numeric/JSON/date/string stack
esbuild       239/0                      latest accepted commit remains 6cb369fd; new package-tsconfig evidence is focused-only
gitoxide      7734/0                     pending tree-entry ordering plus accumulated discovery/mailmap/object stack
libsqlite     240/0                      pending abs/string scalar plus JSON aggregate/table/mutation stack
LightningCSS  4419/0                     uncommitted gradient/attr formatter follow-up
markerPDF     282/0                      pending PDF literal-string escape / inline image stream work
pandoc        416/0                      pending HTML reader standalone applet slice
quadrable     264/0                      pending docopt metadata unknown short-option slice inside broad dirty lane
rclone        530/0                      pending WebDAV GET/HEAD/POST gzip/file-response plus earlier WebDAV stack
Readability   159/0                      uncommitted fourteen-slice fixture/import dirty pile
syncthing     9352/0                     pending REST route-registry slice
```

## Findings

1. **Critical - the repository still has no stable aggregate acceptance baseline.**
   - Paths: `tools/run-tests.php`, `progress.md`, `audits/latest.md`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: periodically run repo-wide tests and commit small, reviewable slices with passing verification from a stable snapshot.
   - Evidence: `HEAD` moved to `f292e50e623a` during audit sampling; the checkout still has `25095` status rows including untracked files, `244` tracked dirty rows, `253 files changed`, and every lane manifest/status file dirty. This is an integration pile, not an accept/reject snapshot for one lane slice.

2. **Critical - a no-argument root harness is active from another owner, so this audit must not start a duplicate.**
   - Paths: `tools/run-tests.php`, `progress.md`, `audits/latest.md`.
   - Goal requirement at risk: repo-wide verification must be serialized and tied to one frozen snapshot.
   - Evidence: the initial exact gate returned no rows, but the final gate found PID `2416152` owned by `claude` running exactly `php tools/run-tests.php`. I did not start another root run. Because the tree is dirty and mixed, this active run should not be counted as acceptance unless the integrator can tie it to a frozen snapshot and unchanged staged content.

3. **Critical - accepted commits are immediately followed by broader unaccepted lane piles.**
   - Paths: `lanes/esbuild/lane-status.json`, `lanes/pandoc/lane-status.json`, `lanes/markerpdf/lane-status.json`, `lanes/lightningcss/lane-status.json`, `lanes/gitoxide/lane-status.json`, `progress.md`, `audits/integration-status.md`.
   - Goal requirement at risk: accepted progress must be tied to the exact committed slice and must not launder later dirty handoffs into progress.
   - Evidence: recent history accepted the esbuild resolver, Pandoc linebreak, markerPDF CMap, LightningCSS page-rule, and Gitoxide signature slices. Live statuses now advertise broader pending follow-ups: esbuild package-tsconfig, Pandoc applet, markerPDF PDF string/image stream work, LightningCSS formatter work, Gitoxide tree/object/discovery work, and more.

4. **Critical - nearly every live lane status remains unaccepted worker output.**
   - Paths: `lanes/difftastic/lane-status.json`, `lanes/dolt/lane-status.json`, `lanes/gitoxide/lane-status.json`, `lanes/libsqlite/lane-status.json`, `lanes/lightningcss/lane-status.json`, `lanes/markerpdf/lane-status.json`, `lanes/pandoc/lane-status.json`, `lanes/quadrable/lane-status.json`, `lanes/rclone/lane-status.json`, `lanes/readability/lane-status.json`, `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: dirty worker handoffs must not count as native implementation progress until supervisor/integrator acceptance and verification occur.
   - Evidence: all listed statuses say pending, uncommitted, not committed, or root/integrator owned. Several explicitly describe accumulated piles rather than one reviewable slice. Esbuild is the only sampled current lane with an accepted `latestCommit`, and even it now contains focused-only unaccepted follow-up evidence.

5. **High - dashboard progress is a verified snapshot, but it is stale relative to live metadata and still overstates accepted parity.**
   - Paths: `porting.html`, `porting-summary.json`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: the dashboard must show current accepted upstream denominator, mapped tests, PHP pass/fail, phase, audit, blocker, and commit.
   - Evidence: `porting.html` says snapshot `main 6cb369fd15d0`, while live `HEAD` is `f292e50e623a` and all lane manifest/status files are dirty. It reports average progress `93.3%` despite most live lane work being unaccepted and several upstream runners being static-only or bounded.

6. **High - support-library rows are visible but still not first-class support ports.**
   - Paths: `dependency-backlog.json`, `progress.md`, `porting.html`.
   - Goal requirement at risk: support libraries require the same granularity as lanes: bounded native PHP component, activation gate, dependency-specific upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, malformed/corrupt cases where relevant, and as much upstream/spec-suite evidence as can actually run.
   - Evidence: `dependency-backlog.json` has `37` rows, `0` active rows, and `37` rows without `upstreamDenominator`. The rows are useful routing notes, but none is accepted support-library progress and none records a bounded install/runner ledger for missing package blockers.

7. **High - Pandoc rich-format coverage remains routing-only, not support-library progress.**
   - Paths: `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, `lanes/pandoc/lane-status.json`, `dependency-backlog.json`, `porting.html`.
   - Goal requirement at risk: Pandoc must account for DOC, DOCX/OpenXML, PDF input/output handoff, EPUB, ODT/OpenDocument, citations, math, tables, templates, package containers, XML/HTML, Unicode/charset, JSON/YAML metadata, syntax highlighting, and archive/compression with bounded support rows and real upstream/spec evidence.
   - Evidence: those needs are visible as gated rows or reuse paths, but every relevant row is candidate/deferred, has no dependency-specific denominator, no PHP pass/fail ledger, and no malformed/corrupt evidence ledger. The current Pandoc lane reports `416` behavior tests while the manifest maps `2276/2276`, so broad rich-format parity is over-credited unless clearly labeled as static inventory.

8. **High - markerPDF still mixes dependency planning and native extraction progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`, `lanes/markerpdf/lane-status.json`, `dependency-backlog.json`.
   - Goal requirement at risk: markerPDF must become a native PDF-to-structured-content extraction pipeline; external runtime planning, supplied converter/model callbacks, benchmark archive probing, and dependency inspection cannot count as native conversion progress.
   - Evidence: markerPDF still reports mapped counts above its upstream path denominator and carries stream/filter, font, runtime, app, model, benchmark, output, OCR/layout, and table-dependency planning entries. PDF dictionaries, page/layout planning, OCR/layout results, table geometry, Unicode/charset, and archive/package support remain inactive support rows.

9. **High - base lanes are crossing inactive support-library boundaries without activating shared rows.**
   - Paths: `dependency-backlog.json`, `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`, `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json`, `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: rich dependency work should not count as shared reusable progress unless a bounded row is activated, tested, and evidenced.
   - Evidence: esbuild resolver work is ahead of deferred `js-package-resolution-core`; rclone WebDAV/XML/gzip work is ahead of inactive `webdav-protocol-core`, `xml-html5-dom-core`, and `archive-compression-streams`; Dolt/libsqlite JSON and SQL expression work are ahead of inactive `json-json5-document-core` and `sql-expression-semantics-core`; Syncthing BEP/REST work is ahead of inactive `protobuf-wire-core`; Gitoxide protocol/object work remains ahead of inactive `git-wire-protocol-core`, checksum/hash, URL, and archive/compression rows.

10. **Medium - manifest and status schemas still prevent reliable dashboard math.**
    - Paths: `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`, `porting-summary.json`, `porting.html`.
    - Goal requirement at risk: progress percentages must be comparable and tied to upstream denominator, mapped tests, PHP pass/fail, phase, blocker, and commit.
    - Evidence: the manifests store counts under nested `benchmarkDenominator` and per-lane ad hoc fields, while status files expose assertion-like `phpPass` counts but no normalized accepted `mappedTests` or `upstreamDenominator`. MarkerPDF maps above its upstream denominator; Pandoc maps the full static inventory with only `416` behavior tests; Syncthing/Gitoxide/Difftastic/LightningCSS `phpPass` values are assertion-like counts.

11. **Medium - Readability remains too broad to review as one accepted slice.**
    - Paths: `lanes/readability/UPSTREAM_TEST_MANIFEST.json`, `lanes/readability/lane-status.json`, `lanes/readability/tests/ArticleExtractorTest.php`.
    - Goal requirement at risk: prefer small correct slices over broad shallow ports, with mapped upstream tests and reviewable commits.
    - Evidence: the status lists fourteen interleaved uncommitted fixture/import slices across the same tracked files. Even where Mozilla fixture evidence is green, this should be accepted only after integrator hunk splitting or an explicit preserved-work package followed by root verification from the frozen package.

12. **Medium - support rows should not activate from fixture-only base-lane evidence.**
    - Paths: `dependency-backlog.json`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`, `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`.
    - Goal requirement at risk: support-library work needs as much upstream/spec-suite evidence as can actually run; fixture-only credit is insufficient unless the broader suite was attempted and honestly bounded.
    - Evidence: Pandoc package formats, rclone WebDAV/XML/gzip/file responses, and markerPDF PDF dictionaries/layout/table needs are currently represented by base-lane fixtures or planning notes. None records a dependency-specific upstream/spec denominator, mapped malformed/corrupt cases, or bounded `sudo -n` install/runner attempts where missing packages block fuller evidence.

## Root Harness Decision

I did not run `php tools/run-tests.php`. The initial exact no-argument process gate was empty, but the tree is not stable enough for a meaningful audit-owned aggregate run: `HEAD` moved during review, every lane manifest/status file is dirty, and live lane statuses describe pending mixed handoffs rather than one accepted frozen batch. Before finish, the exact gate found active root PID `2416152` owned by `claude` (`php tools/run-tests.php`), so starting a duplicate would violate the root-harness rule.

## Next Intervention

Freeze writers/status publishers/dashboard regeneration/test-loop starters for two stable polls; wait for PID `2416152` to finish and record its result without counting it unless it matches a frozen snapshot; select exactly one owner-free reduced batch; run focused verification and `git diff --check`; run one serialized no-argument root harness only from that same frozen snapshot with an empty exact process gate; normalize manifest/status count units; keep support rows inactive unless a real accepted base-lane gate or blocker exists; regenerate dashboard artifacts from the accepted commit; then commit or reject.
