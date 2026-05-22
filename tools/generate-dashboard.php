<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$laneDirs = glob($root . '/lanes/*', GLOB_ONLYDIR) ?: [];
sort($laneDirs);

$rows = [];
$total = 0.0;

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

foreach ($laneDirs as $dir) {
    $manifestPath = $dir . '/UPSTREAM_TEST_MANIFEST.json';
    $statusPath = $dir . '/lane-status.json';
    $manifest = is_file($manifestPath) ? json_decode((string) file_get_contents($manifestPath), true) : [];
    $status = is_file($statusPath) ? json_decode((string) file_get_contents($statusPath), true) : [];
    if (!is_array($manifest) || !is_array($status)) {
        continue;
    }

    $progress = (float) ($status['estimatedProgress'] ?? 0);
    $lane = basename($dir);
    $denominatorTotal = $manifest['benchmarkDenominator']['total'] ?? null;
    $mapped = $manifest['benchmarkDenominator']['mapped'] ?? null;
    $total += $progress;
    $rows[] = [
        'lane' => $lane,
        'library' => $stringValue($status['library'] ?? null, $lane),
        'suite' => $shorten($stringValue($status['suiteProgress'] ?? null, 'unmapped'), 88),
        'manifestStatus' => $shorten($stringValue($manifest['benchmarkDenominator']['status'] ?? null, 'pending'), 72),
        'source' => $stringValue($manifest['upstream']['url'] ?? null),
        'denominator' => $metricSummary($denominatorTotal),
        'mapped' => $metricSummary($mapped),
        'php' => $stringValue($status['phpPass'] ?? 0, '0') . ' pass / ' . $stringValue($status['phpFail'] ?? 0, '0') . ' fail',
        'wp' => $shorten($stringValue($status['wordpressScenarios'] ?? null), 88),
        'phase' => $shorten($stringValue($status['phase'] ?? null, 'planning'), 72),
        'audit' => $shorten($stringValue($status['audit'] ?? null, 'not started'), 72),
        'work' => $shorten($stringValue($status['currentWork'] ?? null, 'none'), 110),
        'blocker' => $shorten($stringValue($status['blocker'] ?? null, 'none'), 110),
        'commit' => $stringValue($status['latestCommit'] ?? null, 'none'),
        'progress' => $progress,
    ];
}

$average = $rows === [] ? 0.0 : $total / count($rows);
$generated = gmdate('Y-m-d H:i:s') . ' UTC';

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$htmlRows = '';
foreach ($rows as $row) {
    $lanePath = rawurlencode($row['lane']);
    $manifestLink = 'lanes/' . $lanePath . '/UPSTREAM_TEST_MANIFEST.json';
    $statusLink = 'lanes/' . $lanePath . '/lane-status.json';
    $source = $row['source'] === 'pending'
        ? $escape($row['source'])
        : '<a href="' . $escape($row['source']) . '">upstream</a>';
    $progress = number_format($row['progress'], 1);
    $htmlRows .= '<tr>'
        . '<th scope="row">' . $escape($row['library']) . '<br><a href="' . $escape($statusLink) . '">status</a></th>'
        . '<td><meter min="0" max="100" value="' . $escape((string) $row['progress']) . '"></meter> <strong>' . $escape($progress) . '%</strong><br>' . $escape($row['suite']) . '</td>'
        . '<td>' . $escape($row['manifestStatus']) . '<br><a href="' . $escape($manifestLink) . '">manifest</a></td>'
        . '<td>' . $escape($row['denominator']) . '<br>' . $source . '</td>'
        . '<td>' . $escape($row['php']) . '<br>' . $escape($row['mapped']) . ' mapped</td>'
        . '<td>' . $escape($row['wp']) . '</td>'
        . '<td>' . $escape($row['phase']) . '</td>'
        . '<td>' . $escape($row['audit']) . '</td>'
        . '<td>' . $escape($row['work']) . '</td>'
        . '<td>' . $escape($row['blocker']) . '</td>'
        . '<td>' . $escape($row['commit']) . '</td>'
        . '</tr>' . "\n";
}

$html = <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Native PHP Porting Dashboard</title>
  <style>
    :root { color-scheme: light dark; font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
    body { margin: 0; padding: 24px; background: Canvas; color: CanvasText; }
    a { color: LinkText; }
    header { display: flex; justify-content: space-between; gap: 24px; align-items: baseline; margin-bottom: 12px; }
    h1 { margin: 0; font-size: 24px; }
    .summary { display: flex; gap: 16px; flex-wrap: wrap; color: color-mix(in srgb, CanvasText 72%, Canvas); }
    .note { margin: 0 0 16px; max-width: 960px; color: color-mix(in srgb, CanvasText 72%, Canvas); font-size: 13px; }
    .table-wrap { overflow-x: auto; }
    table { width: 100%; min-width: 1180px; border-collapse: collapse; font-size: 12px; line-height: 1.35; }
    th, td { border: 1px solid color-mix(in srgb, CanvasText 16%, Canvas); padding: 6px 8px; vertical-align: top; text-align: left; }
    thead th { background: color-mix(in srgb, CanvasText 8%, Canvas); position: sticky; top: 0; z-index: 1; }
    tbody th { font-weight: 650; background: color-mix(in srgb, CanvasText 3%, Canvas); }
    meter { width: 72px; vertical-align: middle; }
    td, th { overflow-wrap: anywhere; }
    td:nth-child(2) { min-width: 160px; }
    td:nth-child(9), td:nth-child(10) { max-width: 190px; }
  </style>
</head>
<body>
  <header>
    <h1>Native PHP Porting Dashboard</h1>
    <div class="summary">
      <span>Average progress: <strong>{$escape(number_format($average, 1))}%</strong></span>
      <span>Lanes: <strong>{$escape((string) count($rows))}</strong></span>
      <span>Generated: <strong>{$escape($generated)}</strong></span>
    </div>
  </header>
  <p class="note">Rows are intentionally compact for low-context review. Full per-lane tracking detail remains in the linked status and manifest JSON files.</p>
  <div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>Library</th>
        <th>Suite Progress</th>
        <th>Benchmark Manifest</th>
        <th>Upstream</th>
        <th>Mapped</th>
        <th>WordPress Scenarios</th>
        <th>Phase</th>
        <th>Audit</th>
        <th>Current Work</th>
        <th>Blocker</th>
        <th>Commit</th>
      </tr>
    </thead>
    <tbody>
{$htmlRows}    </tbody>
  </table>
  </div>
</body>
</html>
HTML;

file_put_contents($root . '/porting.html', $html);
fwrite(STDOUT, "Generated porting.html with " . count($rows) . " lanes\n");
