<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerServerAdapter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$adapter = new MarkerServerAdapter();
$pdfPath = sys_get_temp_dir() . '/markerpdf-remote-polling-' . bin2hex(random_bytes(4)) . '.pdf';
if (file_put_contents($pdfPath, '%PDF-remote-polling') === false) {
    throw new RuntimeException('Unable to write markerPDF remote polling smoke fixture.');
}

$baseParams = [
    'filepath' => $pdfPath,
    'max_pages' => 1,
    'langs' => 'English',
    'force_ocr' => false,
    'paginate' => false,
    'extract_images' => true,
];

$missingStatusRejected = false;
$missingStatusRequests = [];
$missingStatusClient = static function (string $method, string $url, array $request) use (&$missingStatusRequests): array {
    $missingStatusRequests[] = ['method' => $method, 'url' => $url, 'request' => $request];
    if ($method === 'POST') {
        return ['request_check_url' => 'https://api.example/marker/check/missing-status'];
    }

    return ['success' => false, 'error' => 'remote worker omitted status'];
};

$exhaustionRequests = [];
$exhaustionClient = static function (string $method, string $url, array $request) use (&$exhaustionRequests): array {
    $exhaustionRequests[] = ['method' => $method, 'url' => $url, 'request' => $request];
    if ($method === 'POST') {
        return ['request_check_url' => 'https://api.example/marker/check/processing'];
    }

    return [
        'status' => 'processing',
        'attempt' => ((int) ($request['poll_index'] ?? -1)) + 1,
        'success' => false,
        'error' => 'still running',
    ];
};

try {
    try {
        $adapter->convertPdfRemote(
            $baseParams,
            $missingStatusClient,
            'demo-api-key',
            'https://api.example/marker',
            maxPolls: 2
        );
    } catch (InvalidArgumentException $exception) {
        $missingStatusRejected = str_contains($exception->getMessage(), 'missing status');
    }

    $exhausted = $adapter->convertPdfRemote(
        $baseParams,
        $exhaustionClient,
        'demo-api-key',
        'https://api.example/marker',
        maxPolls: 2
    );

    $plan = $adapter->remotePollingPlan();
    $payload = [
        'scenario' => 'wordpress-marker-runtime-server-remote-polling-error-currentbase',
        'purpose' => 'Record marker_server.py remote Datalab polling error boundaries for a WordPress PDF import queue without live HTTP, FastAPI, Uvicorn, Python models, or external PDF tools.',
        'source' => 'sddai/markerPDF marker_server.py::convert_pdf_remote',
        'missing_status_rejected' => $missingStatusRejected,
        'missing_status_request_methods' => array_column($missingStatusRequests, 'method'),
        'exhaustion_returns_last_poll_response' => $exhausted === [
            'status' => 'processing',
            'attempt' => 2,
            'success' => false,
            'error' => 'still running',
        ],
        'exhaustion_request_methods' => array_column($exhaustionRequests, 'method'),
        'remote_polling_plan' => $plan,
        'executes_live_http' => false,
        'executes_python_or_models' => false,
        'executes_external_pdf_tools' => false,
    ];

    if (!$payload['missing_status_rejected']) {
        throw new RuntimeException('Expected missing remote poll status to be rejected.');
    }
    if (!$payload['exhaustion_returns_last_poll_response']) {
        throw new RuntimeException('Expected polling exhaustion to return the last upstream payload.');
    }
    if ($payload['missing_status_request_methods'] !== ['POST', 'GET']) {
        throw new RuntimeException('Expected missing-status smoke to stop after first poll.');
    }
    if ($payload['exhaustion_request_methods'] !== ['POST', 'GET', 'GET']) {
        throw new RuntimeException('Expected exhaustion smoke to poll exactly twice.');
    }

    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    if (is_file($pdfPath)) {
        unlink($pdfPath);
    }
}
