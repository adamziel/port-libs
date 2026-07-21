# Native PHP Port Libraries

This workspace tracks native standard-PHP ports of selected systems libraries for WordPress, Playground, Data Liberation, shared-hosting, and migration workflows.

The durable coordination files are:

- `goal.md`: source objective.
- `progress.md`: human-readable status and next work per lane.
- `porting.html`: generated compact dashboard.
- `porting-summary.json`: generated low-context dashboard summary for agents.
- `lanes/*/UPSTREAM_TEST_MANIFEST.json`: upstream source and benchmark denominator mapping.
- `lanes/*/lane-status.json`: current implementation/audit status consumed by the dashboard.

Published progress:

- Repository: <https://github.com/adamziel/port-libs>
- Dashboard: <https://adamziel.github.io/port-libs/>

Package documentation:

- [Pandoc conversion package](lanes/pandoc/README.md): public API, architecture,
  design decisions, integration boundaries, and focused evidence.

Run the current PHP checks with:

```sh
php tools/run-tests.php
```

Regenerate the dashboard with:

```sh
php tools/generate-dashboard.php
```

GitHub Pages is configured to publish from the `main` branch root. `index.html` redirects to the generated dashboard.
