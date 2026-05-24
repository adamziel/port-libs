# Independent Audit - 2026-05-24T21:52Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, `dependency-backlog.json`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`,
recent `audits/integration-status.md`, and recent Git history through
`c7e35a6c Refresh independent audit status`.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T21:51:39Z -> 2026-05-24T21:52:02Z
HEAD: c7e35a6ca6c2
recent history: c7e35a6c Refresh independent audit status; 2ba8794c Record LightningCSS handoff rejection; 952825c8 Refresh independent audit status; 21045a79 Record handoff rejection intake status; e4442a13 Record integration intake status; a42ca8aa Refresh independent audit status
default status rows including untracked: 24300 -> 24314
tracked dirty rows: 229
dirty shortstat moved: 229 files changed, 189616 insertions(+), 23458 deletions(-) -> 229 files changed, 189617 insertions(+), 23458 deletions(-)
targeted status: all 12 lane manifests and all 12 lane-status files are dirty
exact root process gate: first sample matched transient PID 2154787 (`php tools/run-tests.php`); owner `ps` sample found it already exited; final sample returned no rows
root run by this audit: not started; the checkout failed the stability gate and the required pre-run gate had just observed an active root harness
JSON validity: `jq empty` passed for all 12 manifests, all 12 lane-status files, `dependency-backlog.json`, and `porting-summary.json`
dependency backlog: 37 rows, 0 active, 1 blocked, 25 candidate, 11 deferred; every row still has upstreamDenominator null
```

Live dirty manifest/status samples, not accepted progress:

```text
lane          manifest mapped/total     status phpPass/phpFail     latest status
difftastic    1222/1338                 3844/0                     pending Objective-C slice
dolt          613/613                   470/0                      not committed query-diff slice
esbuild       233/2567                  233/0                      uncommitted exports-map slice
gitoxide      1480/2877                 7665/0                     pending identity/discovery stack
libsqlite     227/1589                  227/0                      pending JSON aggregate slice
LightningCSS  3028/3548                 4412/0                     uncommitted @page formatter slice
markerPDF     170/78                    275/0                      pending ToUnicode CMap slice
pandoc        2276/2276                 409/0                      pending HTML reader linebreak slice
quadrable     55/55                     263/0                      pending hex helper slice
rclone        516/1601                  516/0                      pending WebDAV lock mutation slice
Readability   1690/1984                 154/0                      pending CNET cleanup slice
syncthing     658/658                   9265/0                     pending Windows native-model slice
```

## Findings

1. **Critical - the checkout is still moving, so there is no stable aggregate acceptance baseline.**
   - Paths: `tools/run-tests.php`, `progress.md`,
     `audits/integration-status.md`, `lanes/*/lane-status.json`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: periodically run repo-wide tests and commit
     small reviewable slices with passing verification from a stable snapshot.
   - Evidence: after the prior audit commit `c7e35a6c`, default status rows
     moved from `24300` to `24314`, and dirty shortstat moved from
     `229 files changed, 189616 insertions(+), 23458 deletions(-)` to
     `229 files changed, 189617 insertions(+), 23458 deletions(-)`.

2. **Critical - a root harness was already active at the required gate, so this audit correctly did not start a duplicate.**
   - Paths: `tools/run-tests.php`, `audits/latest.md`.
   - Goal requirement at risk: repo-wide root verification must be serialized;
     duplicate no-argument root harnesses make acceptance evidence ambiguous.
   - Evidence: `pgrep -af '^php tools/run-tests\.php$'` matched
     `2154787 php tools/run-tests.php` at `2026-05-24T21:51:39Z`. The process
     exited before `ps -o pid,user,ppid,stat,etime,args -p 2154787` could show
     owner details, and a final exact gate was clear. Because the tree was
     still changing, no audit-owned root run was started after it cleared.

3. **Critical - all live lane status files still describe unaccepted worker output.**
   - Paths: `lanes/difftastic/lane-status.json`,
     `lanes/dolt/lane-status.json`, `lanes/esbuild/lane-status.json`,
     `lanes/gitoxide/lane-status.json`, `lanes/libsqlite/lane-status.json`,
     `lanes/lightningcss/lane-status.json`,
     `lanes/markerpdf/lane-status.json`, `lanes/pandoc/lane-status.json`,
     `lanes/quadrable/lane-status.json`, `lanes/rclone/lane-status.json`,
     `lanes/readability/lane-status.json`,
     `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: dirty worker handoffs must not count as
     accepted native implementation progress.
   - Evidence: every sampled lane has dirty manifest/status metadata and a
     pending, uncommitted, or not-committed current handoff. Recent integration
     history is rejection/status only: `2ba8794c` rejects/deferred
     LightningCSS, `21045a79` rejects/deferred LightningCSS, `e4442a13`
     records no-acceptance intake, `748bc929` rejects markerPDF, and
     `96cb5683` rejects Syncthing.

4. **Critical - root-red blockers remain the first acceptance blocker.**
   - Paths: `audits/integration-status.md`,
     `lanes/difftastic/src/TokenDiffer.php`,
     `lanes/difftastic/tests/TokenDifferTest.php`,
     `lanes/syncthing/tests/BepSessionTest.php`,
     `lanes/syncthing/*`.
   - Goal requirement at risk: focused lane-green evidence is not enough when
     the serialized root harness is red or the root-red fix is mixed into a
     broad dirty pile.
   - Evidence: integration status still identifies the Difftastic
     `TokenDiffer::isDartLanguage()` failure and Syncthing
     `syncthing_session_outbound_frames()` failure as the concrete blockers to
     isolate before retrying other handoffs. Current Difftastic and Syncthing
     dirty scopes remain broad and pending.

5. **High - `porting.html` and `porting-summary.json` remain stale publication artifacts.**
   - Paths: `porting.html`, `porting-summary.json`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: the dashboard must show current accepted
     upstream denominator, mapped tests, PHP pass/fail, phase, audit, blocker,
     and commit.
   - Evidence: `porting.html` still publishes source snapshot
     `main 0fa9ecafcd10` generated at `2026-05-24 21:19:36 UTC`.
     Live dirty manifests now report Difftastic `1222/1338`,
     LightningCSS `3028/3548`, markerPDF `170/78`, Pandoc `2276/2276`,
     rclone `516/1601`, and Syncthing `658/658`.

6. **High - support-library tracking is visible but still not first-class coverage.**
   - Paths: `dependency-backlog.json`, `progress.md`, `porting.html`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: support libraries require lane-equivalent
     granularity: bounded native PHP component, activation gate,
     dependency-specific upstream/spec denominator, mapped fixtures, PHP
     pass/fail evidence, malformed/corrupt cases where relevant, and as much
     upstream/spec-suite evidence as can actually run.
   - Evidence: the backlog has `37` rows, `0` active rows, and
     `upstreamDenominator: null` for every row. No support row has a native PHP
     pass/fail ledger, malformed/corrupt evidence, or bounded install-attempt
     ledger, so the rows remain routing notes rather than accepted support
     ports.

7. **High - Pandoc rich conversion remains overstated by the `2276/2276` mapped claim.**
   - Paths: `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/lane-status.json`, `dependency-backlog.json`,
     `porting.html`.
   - Goal requirement at risk: Pandoc must provide a conversion kernel with
     Markdown, HTML, WXR, EPUB/PDF-oriented intermediate forms, and WordPress
     output backed by meaningful upstream parity and explicit blockers.
   - Evidence: Pandoc reports `2276/2276` mapped while lane status reports
     `409` PHP behavior tests and full Haskell runner parity remains
     unexecuted. DOC, DOCX/OpenXML, PDF input/output handoff, EPUB,
     ODT/OpenDocument, templates, citations, math, tables, package containers,
     XML/HTML, Unicode/charset, JSON/YAML metadata, syntax highlighting, and
     archive/compression are visible as gated rows or reuse paths, but none is
     an active support port with a dependency-specific denominator.

8. **High - current rich lane work crosses inactive support boundaries.**
   - Paths: `dependency-backlog.json`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: dependency work should not count as reusable
     progress unless the bounded row is activated, tested, and evidenced.
   - Evidence: esbuild has package `exports` resolution while
     `js-package-resolution-core` is deferred; rclone WebDAV lock/mutation
     work sits ahead of inactive `webdav-protocol-core` and
     `xml-html5-dom-core`; Dolt and libsqlite JSON work sits ahead of inactive
     `json-json5-document-core` and `sql-expression-semantics-core`;
     Syncthing BEP/session wire work sits ahead of inactive
     `protobuf-wire-core`; Gitoxide protocol/discovery work still sits ahead
     of inactive `git-wire-protocol-core` and URL support rows.

9. **High - markerPDF still has a weak denominator for its claimed PDF breadth.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/lane-status.json`,
     `lanes/markerpdf/src/PdfTextExtractor.php`,
     `dependency-backlog.json`.
   - Goal requirement at risk: markerPDF must become a native
     PDF-to-structured-content extraction pipeline; external runtime planning,
     supplied converter/model callbacks, and benchmark scaffolding cannot
     count as native conversion progress.
   - Evidence: markerPDF maps `170` focused semantics against only `78`
     tracked upstream paths and `0` committed Python unit-test files. The
     current ToUnicode CMap codespacerange slice is pending root/integrator
     acceptance, and the broader PDF page/layout, OCR/result, table, CMap/font,
     and benchmark archive areas remain behind inactive support rows.

10. **Medium - manifest/status count units remain non-comparable.**
    - Paths: `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
      `lanes/*/lane-status.json`, `porting-summary.json`, `porting.html`.
    - Goal requirement at risk: progress must track upstream denominator,
      mapped tests, PHP pass/fail, phase, blocker, and commit in comparable
      units.
    - Evidence: markerPDF maps more items than its denominator (`170/78`);
      Pandoc maps the entire denominator (`2276/2276`) while reporting `409`
      behavior tests; Syncthing and Gitoxide `phpPass` values are assertion-like
      (`9265`, `7665`) while Dolt, rclone, readability, and markerPDF use
      behavior-case counts. Dashboard commit cells still include prose or
      truncations such as `port-es`, `Port rc`, and `uncommi`.

11. **Medium - several handoffs are broad dirty piles instead of reviewable slices.**
    - Paths: `audits/integration-status.md`, `lanes/lightningcss/*`,
      `lanes/dolt/*`, `lanes/gitoxide/*`, `lanes/quadrable/*`,
      `lanes/readability/*`, `lanes/syncthing/*`.
    - Goal requirement at risk: prefer small correct slices over broad shallow
      ports and commit small reviewable slices with passing tests.
    - Evidence: the latest LightningCSS handoff was rejected because
      `CssFormatter.php` and `CssFormatterTest.php` were untracked whole-file
      additions containing prior unaccepted formatter behavior, with `215`
      untracked LightningCSS files. Dolt was skipped because
      `port-dolt-runner` was still active and the dirty scope mixed
      merge-status, REGEXP_REPLACE, JSON_SET, and query-diff evidence. Other
      lane statuses also describe accumulated stacks rather than isolated
      accept/reject batches.

12. **Medium - Syncthing URL/query support is not routed through the shared URL row.**
    - Paths: `dependency-backlog.json`,
      `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`,
      `lanes/syncthing/lane-status.json`.
    - Goal requirement at risk: a missing bounded support row is a blocker once
      a base lane is ready for or blocked by the next essential rich
      capability.
    - Evidence: Syncthing status continues to include discovery and route work
      with URL/query construction needs, but `url-percent-encoding-core` lists
      `rclone`, `gitoxide`, `esbuild`, `lightningcss`, and `readability` only.
      Either add Syncthing as a gated consumer with spec/vector expectations,
      or keep the URL evidence explicitly lane-local and non-reusable.

13. **Medium - missing-package blockers are still not support-row evidence.**
    - Paths: `dependency-backlog.json`,
      `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
      `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
      `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`,
      `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`.
    - Goal requirement at risk: missing packages are not final blockers until
      bounded `sudo -n` installs were attempted or ruled out.
    - Evidence: Pandoc, markerPDF, Difftastic, and Gitoxide all have broad
      runner blockers, but no support row records an actual
      dependency-specific suite attempt or bounded install-attempt ledger.

## Required Next Intervention

Freeze writers, status publishers, dashboard regeneration, and duplicate
focused/root loops until two consecutive polls show unchanged `HEAD`, tracked
status, default status, shortstat, and exact root gate. Treat `9784b10c` as
accepted libsqlite-only evidence, `5e46840f` as accepted markerPDF ASCIIHex-only
evidence, `96cb5683` as a Syncthing rejection, `748bc929` as a markerPDF
rejection, `e4442a13` as no-acceptance intake status, `21045a79` and
`2ba8794c` as LightningCSS rejection/defer records, and this audit as
documentation-only.

The next concrete intervention remains to isolate and accept or reject exactly
one owner-free reduced root-red fix: Difftastic
`TokenDiffer::isDartLanguage()` or Syncthing
`syncthing_session_outbound_frames()`. After that, run one serialized
no-argument `php tools/run-tests.php` only from the frozen snapshot with an
empty exact process gate, normalize manifest/status count units, keep support
rows inactive unless a real accepted gate or blocker opens, regenerate
coordination artifacts from the accepted commit, then commit or reject.
