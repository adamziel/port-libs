# Independent Audit - 2026-05-24T21:48Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, `dependency-backlog.json`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`,
recent `audits/integration-status.md`, and recent Git history through
`952825c8 Refresh independent audit status`.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T21:45:58Z -> 2026-05-24T21:47Z
HEAD: 952825c8404b
recent history: 952825c8 Refresh independent audit status; 21045a79 Record handoff rejection intake status; e4442a13 Record integration intake status; a42ca8aa Refresh independent audit status; 748bc929 Record markerPDF handoff rejection; 96cb5683 Record Syncthing handoff rejection
default status rows including untracked: 24139 -> 24156
tracked dirty rows: 227 files changed
dirty shortstat moved: 227 files changed, 188874 insertions(+), 23196 deletions(-) -> 227 files changed, 188960 insertions(+), 23188 deletions(-)
targeted status: all 12 lane manifests and all 12 lane-status files are dirty
exact root process gate: pgrep -af '^php tools/run-tests\.php$' returned no rows in both audit samples
root run by this audit: not started; the checkout failed the stability gate despite the exact root gate being clear
dependency backlog: 37 rows, 0 active, 1 blocked, 25 candidate, 11 deferred; every row still has upstreamDenominator null
```

Live dirty manifest/status samples, not accepted progress:

```text
lane          manifest mapped/total     status phpPass/phpFail     latest status
difftastic    1222/1338                 3844/0                     pending in shared dirty worktree
dolt          613/613                   468/0                      not committed
esbuild       233/2567                  233/0                      uncommitted
gitoxide      1480/2877                 7665/0                     pending
libsqlite     227/1589                  227/0                      pending; previous accepted 9784b10c
LightningCSS  3026/3548                 4412/0                     uncommitted
markerPDF     170/78                    275/0                      pending; last accepted 5e46840f
pandoc        2276/2276                 409/0                      pending
quadrable     55/55                     261/0                      pending
rclone        513/1601                  513/0                      pending
Readability   1690/1984                 153/0                      uncommitted mixed dirty pile
syncthing     658/658                   9265/0                     pending
```

## Findings

1. **Critical - the checkout is still moving, so there is no stable aggregate acceptance baseline.**
   - Paths: `tools/run-tests.php`, `progress.md`,
     `audits/integration-status.md`, `lanes/*/lane-status.json`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: periodically run repo-wide tests and commit
     small reviewable slices with passing verification from a stable snapshot.
   - Evidence: after the previous `952825c8` audit commit, default status rows
     still moved from `24139` to `24156`, and dirty shortstat moved from
     `227 files changed, 188874 insertions(+), 23196 deletions(-)` to
     `227 files changed, 188960 insertions(+), 23188 deletions(-)`. The exact
     no-argument root gate was clear in both samples, but a root run from this
     state would not be tied to a frozen acceptance snapshot.

2. **Critical - all live lane status files still describe unaccepted worker output.**
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
   - Evidence: every sampled lane is `pending`, `uncommitted`, or
     `not committed`, except that libsqlite and markerPDF point back to prior
     accepted commits while their new local slices are pending. The latest
     integration records are rejections or skips, not acceptance:
     `21045a79` rejects/deferred LightningCSS, `e4442a13` records no-acceptance
     intake, `748bc929` rejects markerPDF, and `96cb5683` rejects Syncthing.

3. **Critical - root-red blockers remain the first acceptance blocker.**
   - Paths: `audits/integration-status.md`,
     `lanes/difftastic/src/TokenDiffer.php`,
     `lanes/difftastic/tests/TokenDifferTest.php`,
     `lanes/syncthing/tests/BepSessionTest.php`,
     `lanes/syncthing/*`.
   - Goal requirement at risk: focused lane-green evidence is not enough when
     the serialized root harness is red or the root-red fix is mixed into a
     broad dirty pile.
   - Evidence: integration status still identifies the Difftastic
     `TokenDiffer::isDartLanguage()` root-red fix and Syncthing
     `syncthing_session_outbound_frames()` as the concrete blockers to isolate
     before retrying other handoffs. The current Difftastic and Syncthing lane
     statuses both remain pending and broad.

4. **High - `porting.html` and `porting-summary.json` are stale publication artifacts.**
   - Paths: `porting.html`, `porting-summary.json`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: the dashboard must show current accepted
     upstream denominator, mapped tests, PHP pass/fail, phase, audit, blocker,
     and commit.
   - Evidence: `porting.html` still publishes source snapshot
     `main 0fa9ecafcd10` generated at `2026-05-24 21:19:36 UTC`.
     `porting-summary.json` also reports `sourceCommit:
     0fa9ecafcd10bc084504a95437e5c31360903929`, while live dirty manifests
     now report Difftastic `1222/1338`, Pandoc `2276/2276`, rclone
     `513/1601`, Readability `1690/1984`, and Syncthing `658/658`.

5. **High - support-library tracking is visible but still not first-class coverage.**
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

6. **High - Pandoc rich conversion remains overstated by the `2276/2276` mapped claim.**
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

7. **High - current rich lane work crosses inactive support boundaries.**
   - Paths: `dependency-backlog.json`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/libsqlite/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: dependency work should not count as reusable
     progress unless the bounded row is activated, tested, and evidenced.
   - Evidence: esbuild has package/node_modules resolver work while
     `js-package-resolution-core` is deferred; rclone WebDAV slices sit ahead
     of inactive `webdav-protocol-core` and `xml-html5-dom-core`; Dolt and
     libsqlite JSON work sits ahead of inactive `json-json5-document-core` and
     `sql-expression-semantics-core`; Syncthing BEP/session wire work sits
     ahead of inactive `protobuf-wire-core`; Gitoxide protocol work sits ahead
     of inactive `git-wire-protocol-core`.

8. **High - markerPDF still has a weak denominator for its claimed PDF breadth.**
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

9. **Medium - manifest/status count units remain non-comparable.**
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

10. **Medium - several handoffs are broad dirty piles instead of reviewable slices.**
    - Paths: `audits/integration-status.md`, `lanes/lightningcss/*`,
      `lanes/dolt/*`, `lanes/gitoxide/*`, `lanes/quadrable/*`,
      `lanes/readability/*`, `lanes/syncthing/*`.
    - Goal requirement at risk: prefer small correct slices over broad shallow
      ports and commit small reviewable slices with passing tests.
    - Evidence: the latest LightningCSS handoff was rejected because
      `CssFormatter.php` and `CssFormatterTest.php` were untracked whole-file
      additions containing prior unaccepted formatter behavior, with `215`
      untracked LightningCSS files. Dolt was skipped because `port-dolt-runner`
      was still active and the dirty scope mixed merge-status, REGEXP_REPLACE,
      and JSON_SET evidence. Readability, Quadrable, Gitoxide, and Syncthing
      lane statuses also explicitly describe interleaved dirty slices.

11. **Medium - Syncthing URL/query support is not routed through the shared URL row.**
    - Paths: `dependency-backlog.json`,
      `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`,
      `lanes/syncthing/lane-status.json`.
    - Goal requirement at risk: a missing bounded support row is a blocker once
      a base lane is ready for or blocked by the next essential rich
      capability.
    - Evidence: Syncthing status claims global discovery lookup URL
      construction and Go-style query encoding, but `url-percent-encoding-core`
      lists `rclone`, `gitoxide`, `esbuild`, `lightningcss`, and
      `readability` only. Either add Syncthing as a gated consumer with
      spec/vector expectations, or keep the URL evidence explicitly lane-local
      and non-reusable.

12. **Medium - missing-package blockers are still not support-row evidence.**
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
rejection, `e4442a13` as no-acceptance intake status, and `21045a79` as a
LightningCSS rejection/defer.

The next concrete intervention is still to isolate and accept or reject exactly
one owner-free reduced root-red fix: Difftastic `TokenDiffer::isDartLanguage()`
or Syncthing `syncthing_session_outbound_frames()`. After that, run one
serialized no-argument `php tools/run-tests.php` only from the frozen snapshot
with an empty exact process gate, normalize manifest/status count units, keep
support rows inactive unless a real accepted gate or blocker opens, regenerate
coordination artifacts from the accepted commit, then commit or reject.
