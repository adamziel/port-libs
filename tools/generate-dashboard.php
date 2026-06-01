<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$laneDirs = glob($root . '/lanes/*', GLOB_ONLYDIR) ?: [];
sort($laneDirs);

$stringValue = static function (mixed $value, string $fallback = 'pending'): string {
    if ($value === null || $value === '') {
        return $fallback;
    }

    if (is_scalar($value)) {
        return (string) $value;
    }

    return $fallback;
};

$shorten = static function (string $value, int $maxLength = 96): string {
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    if ($value === '') {
        return 'none';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($value, 'UTF-8') <= $maxLength) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $maxLength - 3, 'UTF-8')) . '...';
    }

    if (strlen($value) <= $maxLength) {
        return $value;
    }

    return rtrim(substr($value, 0, $maxLength - 3)) . '...';
};

$firstSentence = static function (string $value): string {
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    if ($value === '') {
        return 'none';
    }

    if (preg_match('/^(.+?[.!?])(?:\s|$)/', $value, $matches) === 1) {
        return $matches[1];
    }

    return $value;
};

$metricSummary = static function (mixed $value): string {
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    if (!is_string($value)) {
        return 'pending';
    }

    if (preg_match('/^\s*([\d,]+(?:\.\d+)?)\b/', $value, $matches) === 1) {
        return $matches[0];
    }

    return 'inventory';
};

$formatCount = static function (mixed $value): string {
    if (is_int($value)) {
        return number_format($value);
    }

    if (is_float($value)) {
        return number_format($value, 0);
    }

    if (!is_string($value)) {
        return '0';
    }

    $value = trim($value);
    if (preg_match('/^\d+$/', $value) === 1) {
        return number_format((int) $value);
    }

    return $value;
};

$shortCommit = static function (string $value): string {
    $value = trim($value);
    if ($value === '' || $value === 'none') {
        return 'none';
    }

    return substr($value, 0, 7);
};

$blockerSummary = static function (string $value) use ($firstSentence, $shorten): string {
    $normalized = strtolower($value);
    if (str_contains($normalized, 'full cargo workspace')) {
        return 'Cargo workspace not run';
    }
    if (str_contains($normalized, 'rust/node/wasm')) {
        return 'Rust/Node/WASM upstream runners not run';
    }
    if (str_contains($normalized, 'seven known failures')) {
        return 'Broad release/all still has 7 known failures';
    }
    if (str_contains($normalized, 'upstream runner parity')) {
        return 'Upstream runner parity unavailable';
    }

    return $shorten($firstSentence($value), 88);
};

$scenarioSummary = static function (mixed $manifestScenario, mixed $statusScenario): string {
    if (is_array($manifestScenario)) {
        $count = count($manifestScenario);

        return $count . ' / ' . $count;
    }

    if (is_string($manifestScenario) && trim($manifestScenario) !== '') {
        return '1 / 1';
    }

    if (is_array($statusScenario)) {
        $count = count($statusScenario);

        return $count . ' / ' . $count;
    }

    if (is_string($statusScenario) && trim($statusScenario) !== '') {
        return 'tracked';
    }

    return 'pending';
};

$rows = [];
$totalProgress = 0.0;
foreach ($laneDirs as $dir) {
    $manifestPath = $dir . '/UPSTREAM_TEST_MANIFEST.json';
    $statusPath = $dir . '/lane-status.json';
    $manifest = is_file($manifestPath) ? json_decode((string) file_get_contents($manifestPath), true) : [];
    $status = is_file($statusPath) ? json_decode((string) file_get_contents($statusPath), true) : [];
    if (!is_array($manifest) || !is_array($status)) {
        continue;
    }

    $lane = basename($dir);
    $progress = (float) ($status['estimatedProgress'] ?? 0);
    $totalProgress += $progress;
    $mapped = $metricSummary($manifest['benchmarkDenominator']['mapped'] ?? null);
    $denominator = $metricSummary($manifest['benchmarkDenominator']['total'] ?? null);
    $library = $stringValue($status['library'] ?? null, $lane);
    $currentWork = $stringValue($status['currentWork'] ?? $manifest['nativeImplementation']['currentSlice'] ?? null, 'none');
    $nextTask = $stringValue($status['nextTask'] ?? $manifest['nextTask'] ?? null, 'none');
    $blocker = $stringValue($status['blocker'] ?? null, 'none');
    $manifestStatus = $stringValue($manifest['benchmarkDenominator']['status'] ?? null, 'pending');

    $rows[] = [
        'lane' => $lane,
        'library' => $library,
        'progressPercent' => number_format($progress, 1),
        'suite' => $shorten($manifestStatus, 72),
        'benchmark' => $shorten($manifestStatus, 72),
        'denominator' => $denominator,
        'mapped' => $mapped,
        'coverage' => $formatCount($mapped) . ' / ' . $formatCount($denominator),
        'php' => $formatCount($status['phpPass'] ?? 0) . ' pass / ' . $formatCount($status['phpFail'] ?? 0) . ' fail',
        'wordpressScenarios' => $scenarioSummary($manifest['wordpressScenario'] ?? null, $status['wordpressScenarios'] ?? null),
        'phase' => $shorten($stringValue($status['phase'] ?? null, 'planning'), 72),
        'audit' => $shorten($stringValue($status['audit'] ?? null, 'not started'), 72),
        'currentWork' => $shorten($currentWork, 84),
        'nextTarget' => $shorten($nextTask, 84),
        'blocker' => $blockerSummary($blocker),
        'commit' => $shortCommit($stringValue($status['latestCommit'] ?? null, 'none')),
        'statusPath' => 'lanes/' . rawurlencode($lane) . '/lane-status.json',
        'manifestPath' => 'lanes/' . rawurlencode($lane) . '/UPSTREAM_TEST_MANIFEST.json',
    ];
}

$average = $rows === [] ? 0.0 : $totalProgress / count($rows);
$generated = gmdate('Y-m-d H:i:s') . ' UTC';
$gitValue = static function (string $command): string {
    return trim((string) shell_exec($command . ' 2>/dev/null')) ?: 'unknown';
};
$dashboardCommit = $gitValue('git rev-parse HEAD');
$dashboardCommitShort = $dashboardCommit === 'unknown' ? 'unknown' : substr($dashboardCommit, 0, 12);
$sourceCommit = trim((string) getenv('PORT_LIBS_SOURCE_COMMIT'));
if ($sourceCommit === '') {
    $parents = preg_split('/\s+/', $gitValue('git show -s --format=%P HEAD')) ?: [];
    $sourceCommit = count($parents) >= 2 ? $parents[0] : $dashboardCommit;
}
$sourceCommitShort = $sourceCommit === 'unknown' ? 'unknown' : substr($sourceCommit, 0, 12);
$sourceBranch = trim((string) getenv('PORT_LIBS_SOURCE_BRANCH')) ?: $gitValue('git branch --show-current');

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$htmlRows = '';
foreach ($rows as $row) {
    $htmlRows .= '<tr>'
        . '<th scope="row"><a href="' . $escape($row['statusPath']) . '">' . $escape($row['library']) . '</a></th>'
        . '<td class="num">' . $escape($row['progressPercent']) . '%</td>'
        . '<td class="num">' . $escape($row['php']) . '</td>'
        . '<td class="num"><a href="' . $escape($row['manifestPath']) . '">' . $escape($row['coverage']) . '</a></td>'
        . '<td>' . $escape($row['currentWork']) . '</td>'
        . '<td>' . $escape($row['nextTarget']) . '</td>'
        . '<td>' . $escape($row['blocker']) . '</td>'
        . '<td class="commit">' . $escape($row['commit']) . '</td>'
        . '</tr>' . "\n";
}

$html = <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Native PHP Porting Progress</title>
  <style>
    :root { color-scheme: light dark; font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
    body { margin: 0; padding: 20px; background: Canvas; color: CanvasText; }
    a { color: LinkText; }
    h1 { margin: 0 0 6px; font-size: 22px; }
    .meta { margin: 0 0 14px; color: color-mix(in srgb, CanvasText 68%, Canvas); font-size: 13px; }
    .table-wrap { overflow-x: auto; }
    table { width: 100%; min-width: 980px; border-collapse: collapse; font-size: 13px; line-height: 1.35; }
    th, td { border: 1px solid color-mix(in srgb, CanvasText 16%, Canvas); padding: 7px 8px; text-align: left; vertical-align: top; }
    thead th { background: color-mix(in srgb, CanvasText 8%, Canvas); position: sticky; top: 0; }
    tbody th { background: color-mix(in srgb, CanvasText 3%, Canvas); white-space: nowrap; }
    .num { white-space: nowrap; text-align: right; }
    .commit { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; white-space: nowrap; }
    td { overflow-wrap: anywhere; }
  </style>
</head>
<body>
  <h1>Native PHP Porting Progress</h1>
  <p class="meta">Generated {$escape($generated)} from source {$escape($sourceCommitShort)}. Average progress {$escape(number_format($average, 1))}%.</p>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Project</th>
          <th>Progress</th>
          <th>PHP Tests</th>
          <th>Mapped</th>
          <th>State</th>
          <th>Next</th>
          <th>Gap</th>
          <th>Commit</th>
        </tr>
      </thead>
      <tbody>
{$htmlRows}      </tbody>
    </table>
  </div>
</body>
</html>
HTML;

$markdownCell = static function (string $value): string {
    $value = str_replace(["\r", "\n"], ' ', $value);
    $value = str_replace('|', '\\|', $value);

    return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
};

$progressRows = '';
foreach ($rows as $row) {
    $progressRows .= '| '
        . '[' . $markdownCell($row['library']) . '](' . $row['statusPath'] . ') | '
        . $markdownCell($row['progressPercent'] . '%') . ' | '
        . $markdownCell($row['php']) . ' | '
        . '[' . $markdownCell($row['coverage']) . '](' . $row['manifestPath'] . ') | '
        . $markdownCell($row['currentWork']) . ' | '
        . $markdownCell($row['nextTarget']) . ' | '
        . $markdownCell($row['blocker']) . ' | '
        . $markdownCell($row['commit']) . " |\n";
}

$progressMd = <<<MD
# Native PHP Porting Progress

Generated: {$generated}
Source snapshot: `{$sourceCommitShort}`
Average progress: `{$markdownCell(number_format($average, 1))}%`

| Project | Progress | PHP Tests | Mapped | State | Next | Gap | Commit |
| --- | ---: | ---: | ---: | --- | --- | --- | --- |
{$progressRows}
MD;

file_put_contents($root . '/progress.md', $progressMd);
file_put_contents($root . '/porting.html', $html);
file_put_contents($root . '/porting-summary.json', json_encode([
    'generated' => $generated,
    'sourceCommit' => $sourceCommit,
    'sourceCommitShort' => $sourceCommitShort,
    'sourceBranch' => $sourceBranch,
    'dashboardCommit' => $dashboardCommit,
    'dashboardCommitShort' => $dashboardCommitShort,
    'averageProgressPercent' => number_format($average, 1),
    'lanes' => array_map(static function (array $row): array {
        unset($row['statusPath'], $row['manifestPath']);

        return $row;
    }, $rows),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

fwrite(STDOUT, 'Generated table-only progress.md, porting.html, and porting-summary.json with ' . count($rows) . " lanes\n");
