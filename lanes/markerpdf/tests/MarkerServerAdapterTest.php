<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerServerAdapter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-server-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf server test folder.');
    }

    return $path;
};

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $child = $path . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($child)) {
            $removeTree($child);
        } else {
            unlink($child);
        }
    }

    rmdir($path);
};

return [
    'normalizes marker server params and returns local API response shape' => static function (TestRunner $t): void {
        $adapter = new MarkerServerAdapter();
        $seen = [];

        $response = $adapter->convertPdf(
            [
                'filepath' => '/tmp/uploads/import.pdf',
                'max_pages' => '2',
                'langs' => 'English,Spanish',
                'force_ocr' => 'yes',
            ],
            true,
            static function (string $filepath, array $options) use (&$seen): array {
                $seen = ['filepath' => $filepath, 'options' => $options];

                return [
                    'full_text' => '# Imported PDF',
                    'images' => ['0_image_0.png' => ['bytes' => 'PNG-BYTES']],
                    'out_metadata' => ['pages' => 2],
                ];
            }
        );

        $t->same('/tmp/uploads/import.pdf', $seen['filepath']);
        $t->same(['max_pages' => 2, 'langs' => 'English,Spanish', 'ocr_all_pages' => true], $seen['options']);
        $t->same(true, $response['success']);
        $t->same('# Imported PDF', $response['markdown']);
        $t->same(['0_image_0.png' => 'UE5HLUJZVEVT'], $response['images']);
        $t->same(['pages' => 2], $response['metadata']);
    },
    'enforces upstream local API pagination and image option guard' => static function (TestRunner $t): void {
        $adapter = new MarkerServerAdapter();

        $t->throws(
            InvalidArgumentException::class,
            static fn () => $adapter->convertPdf(
                ['filepath' => '/tmp/import.pdf', 'paginate' => true],
                true,
                static fn (): string => 'unused'
            )
        );

        $t->throws(
            InvalidArgumentException::class,
            static fn () => $adapter->convertPdf(
                ['filepath' => '/tmp/import.pdf', 'extract_images' => false],
                true,
                static fn (): string => 'unused'
            )
        );
    },
    'saves uploaded PDF bytes for conversion and removes the temporary upload' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $uploadRoot = $makeTempDir();
        try {
            $adapter = new MarkerServerAdapter();
            $seenPath = null;
            $response = $adapter->convertPdfFromUpload(
                [
                    'filename' => '../wordpress-report.pdf',
                    'content_type' => 'application/pdf',
                    'bytes' => '%PDF-1.4 uploaded bytes',
                ],
                ['max_pages' => 1],
                $uploadRoot,
                true,
                static function (string $filepath) use (&$seenPath): array {
                    $seenPath = $filepath;

                    return [
                        'markdown' => 'Uploaded import: ' . file_get_contents($filepath),
                        'images' => [],
                        'metadata' => ['source' => basename($filepath)],
                    ];
                }
            );

            $t->same(true, $response['success']);
            $t->contains('%PDF-1.4 uploaded bytes', $response['markdown']);
            $t->same($uploadRoot . DIRECTORY_SEPARATOR . 'wordpress-report.pdf', $seenPath);
            $t->true(!is_file((string) $seenPath), 'Uploaded PDF should be removed after conversion.');
            $t->same(['source' => 'wordpress-report.pdf'], $response['metadata']);
        } finally {
            $removeTree($uploadRoot);
        }
    },
    'routes uploaded local PDFs directly like marker_server.py upload conversion' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $uploadRoot = $makeTempDir();
        try {
            $adapter = new MarkerServerAdapter();
            $seenOptions = null;
            $response = $adapter->convertPdfFromUpload(
                [
                    'filename' => 'upload-local-route.pdf',
                    'content_type' => 'application/pdf',
                    'bytes' => '%PDF local route fixture',
                ],
                [
                    'max_pages' => '4',
                    'langs' => 'English',
                    'force_ocr' => 'true',
                    'paginate' => true,
                    'extract_images' => false,
                ],
                $uploadRoot,
                true,
                static function (string $filepath, array $options) use (&$seenOptions): array {
                    $seenOptions = $options;

                    return [
                        'markdown' => 'Uploaded local route: ' . basename($filepath),
                        'images' => [],
                        'metadata' => ['route' => 'upload-local-direct'],
                    ];
                }
            );

            $t->same(true, $response['success']);
            $t->same('Uploaded local route: upload-local-route.pdf', $response['markdown']);
            $t->same(['max_pages' => 4, 'langs' => 'English', 'ocr_all_pages' => true], $seenOptions);
            $t->same(['route' => 'upload-local-direct'], $response['metadata']);
            $t->true(!is_file($uploadRoot . DIRECTORY_SEPARATOR . 'upload-local-route.pdf'), 'Uploaded local-route PDF should be removed after conversion.');
        } finally {
            $removeTree($uploadRoot);
        }
    },
    'rejects non-pdf uploads before conversion' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $uploadRoot = $makeTempDir();
        try {
            $adapter = new MarkerServerAdapter();

            $t->throws(
                InvalidArgumentException::class,
                static fn () => $adapter->convertPdfFromUpload(
                    ['filename' => 'not-a-pdf.txt', 'content_type' => 'text/plain', 'bytes' => 'plain text'],
                    [],
                    $uploadRoot,
                    true,
                    static fn (): string => 'unused'
                )
            );
        } finally {
            $removeTree($uploadRoot);
        }
    },
    'returns upload success false when remote conversion fails and removes the temporary upload' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $uploadRoot = $makeTempDir();
        try {
            $adapter = new MarkerServerAdapter();
            $response = $adapter->convertPdfFromUpload(
                [
                    'filename' => 'remote-error.pdf',
                    'content_type' => 'application/pdf',
                    'bytes' => '%PDF remote error fixture',
                ],
                ['max_pages' => 1],
                $uploadRoot,
                false,
                static fn (): string => 'unused',
                static fn (): array => ['status' => 'queued-without-check-url'],
                'api-key',
                'https://api.example/marker'
            );

            $t->same(false, $response['success']);
            $t->contains('request_check_url', $response['error']);
            $t->true(!is_file($uploadRoot . DIRECTORY_SEPARATOR . 'remote-error.pdf'), 'Uploaded PDF should be removed after remote conversion failure.');
        } finally {
            $removeTree($uploadRoot);
        }
    },
    'returns upload success false when the upload payload cannot be read' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $uploadRoot = $makeTempDir();
        try {
            $adapter = new MarkerServerAdapter();
            $response = $adapter->convertPdfFromUpload(
                [
                    'filename' => 'missing-bytes.pdf',
                    'content_type' => 'application/pdf',
                ],
                [],
                $uploadRoot,
                true,
                static fn (): string => 'unused'
            );

            $t->same(false, $response['success']);
            $t->contains('Uploaded PDF payload must provide bytes.', $response['error']);
            $t->true(!is_file($uploadRoot . DIRECTORY_SEPARATOR . 'missing-bytes.pdf'), 'Unreadable upload should not leave a temporary PDF.');
        } finally {
            $removeTree($uploadRoot);
        }
    },
    'returns success false when the supplied local converter fails' => static function (TestRunner $t): void {
        $adapter = new MarkerServerAdapter();

        $response = $adapter->convertPdf(
            ['filepath' => '/tmp/broken.pdf'],
            true,
            static function (): never {
                throw new RuntimeException('model boundary unavailable');
            }
        );

        $t->same(false, $response['success']);
        $t->same('model boundary unavailable', $response['error']);
    },
    'posts remote marker API form data and polls request_check_url until complete' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $pdfPath = $root . DIRECTORY_SEPARATOR . 'remote-import.pdf';
            file_put_contents($pdfPath, '%PDF-remote');
            $requests = [];
            $poll = 0;
            $client = static function (string $method, string $url, array $request) use (&$requests, &$poll): array {
                $requests[] = ['method' => $method, 'url' => $url, 'request' => $request];
                if ($method === 'POST') {
                    return ['request_check_url' => 'https://api.example/check/123'];
                }

                $poll++;

                return $poll < 2
                    ? ['status' => 'processing']
                    : ['status' => 'complete', 'markdown' => '# Remote Import', 'success' => true];
            };

            $adapter = new MarkerServerAdapter();
            $response = $adapter->convertPdfRemote(
                [
                    'filepath' => $pdfPath,
                    'max_pages' => 3,
                    'langs' => 'English',
                    'force_ocr' => true,
                    'paginate' => true,
                    'extract_images' => false,
                ],
                $client,
                'secret-key',
                'https://api.example/marker',
                maxPolls: 3
            );

            $t->same(['status' => 'complete', 'markdown' => '# Remote Import', 'success' => true], $response);
            $t->same('POST', $requests[0]['method']);
            $t->same('https://api.example/marker', $requests[0]['url']);
            $t->same(['X-API-Key' => 'secret-key'], $requests[0]['request']['headers']);
            $t->same('remote-import.pdf', $requests[0]['request']['files']['file']['filename']);
            $t->same('%PDF-remote', $requests[0]['request']['files']['file']['bytes']);
            $t->same(3, $requests[0]['request']['files']['max_pages']);
            $t->same('English', $requests[0]['request']['files']['langs']);
            $t->same(true, $requests[0]['request']['files']['force_ocr']);
            $t->same(true, $requests[0]['request']['files']['paginate']);
            $t->same(false, $requests[0]['request']['files']['extract_images']);
            $t->same('GET', $requests[1]['method']);
            $t->same('https://api.example/check/123', $requests[1]['url']);
            $t->same(1, $requests[2]['request']['poll_index']);
        } finally {
            $removeTree($root);
        }
    },
    'rejects remote initial responses without request_check_url before polling' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $pdfPath = $root . DIRECTORY_SEPARATOR . 'remote-missing-check-url.pdf';
            file_put_contents($pdfPath, '%PDF-remote');
            $requests = [];
            $client = static function (string $method, string $url, array $request) use (&$requests): array {
                $requests[] = ['method' => $method, 'url' => $url, 'request' => $request];

                return ['success' => false, 'error' => 'upstream did not return a polling URL'];
            };

            $adapter = new MarkerServerAdapter();
            $t->throws(
                InvalidArgumentException::class,
                static fn () => $adapter->convertPdfRemote(
                    [
                        'filepath' => $pdfPath,
                        'max_pages' => null,
                        'langs' => null,
                        'force_ocr' => false,
                        'paginate' => false,
                        'extract_images' => true,
                    ],
                    $client,
                    'secret-key',
                    'https://api.example/marker',
                    maxPolls: 2
                )
            );

            $t->same(['POST'], array_column($requests, 'method'));
        } finally {
            $removeTree($root);
        }
    },
    'rejects invalid remote JSON before polling request_check_url' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $pdfPath = $root . DIRECTORY_SEPARATOR . 'remote-invalid-json.pdf';
            file_put_contents($pdfPath, '%PDF-remote');
            $requests = [];
            $client = static function (string $method, string $url, array $request) use (&$requests): string {
                $requests[] = ['method' => $method, 'url' => $url, 'request' => $request];

                return 'not a decoded JSON object';
            };

            $adapter = new MarkerServerAdapter();
            $t->throws(
                InvalidArgumentException::class,
                static fn () => $adapter->convertPdfRemote(
                    [
                        'filepath' => $pdfPath,
                        'max_pages' => null,
                        'langs' => null,
                        'force_ocr' => false,
                        'paginate' => false,
                        'extract_images' => true,
                    ],
                    $client,
                    'secret-key',
                    'https://api.example/marker',
                    maxPolls: 2
                )
            );

            $t->same(['POST'], array_column($requests, 'method'));
        } finally {
            $removeTree($root);
        }
    },
    'preserves upstream remote polling exhaustion without inventing timeout errors' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $pdfPath = $root . DIRECTORY_SEPARATOR . 'remote-processing.pdf';
            file_put_contents($pdfPath, '%PDF-remote');
            $requests = [];
            $client = static function (string $method, string $url, array $request) use (&$requests): array {
                $requests[] = ['method' => $method, 'url' => $url, 'request' => $request];
                if ($method === 'POST') {
                    return ['request_check_url' => 'https://api.example/check/processing'];
                }

                return [
                    'status' => 'processing',
                    'attempt' => ((int) ($request['poll_index'] ?? -1)) + 1,
                    'success' => false,
                    'error' => 'still running',
                ];
            };

            $adapter = new MarkerServerAdapter();
            $response = $adapter->convertPdfRemote(
                [
                    'filepath' => $pdfPath,
                    'max_pages' => null,
                    'langs' => null,
                    'force_ocr' => false,
                    'paginate' => false,
                    'extract_images' => true,
                ],
                $client,
                'secret-key',
                'https://api.example/marker',
                maxPolls: 2
            );

            $t->same(['status' => 'processing', 'attempt' => 2, 'success' => false, 'error' => 'still running'], $response);
            $t->same(['POST', 'GET', 'GET'], array_column($requests, 'method'));
            $t->same(1, $requests[2]['request']['poll_index']);
        } finally {
            $removeTree($root);
        }
    },
    'rejects non-positive remote poll limits before posting to the remote API' => static function (TestRunner $t): void {
        $requests = 0;
        $client = static function () use (&$requests): array {
            $requests++;

            return ['request_check_url' => 'https://api.example/check/unused'];
        };

        $adapter = new MarkerServerAdapter();
        $t->throws(
            InvalidArgumentException::class,
            static fn () => $adapter->convertPdfRemote(
                [
                    'filepath' => '/tmp/does-not-need-to-exist.pdf',
                    'max_pages' => null,
                    'langs' => null,
                    'force_ocr' => false,
                    'paginate' => false,
                    'extract_images' => true,
                ],
                $client,
                'secret-key',
                'https://api.example/marker',
                maxPolls: 0
            )
        );
        $t->same(0, $requests);
    },
    'rejects remote poll responses without the upstream status key' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $pdfPath = $root . DIRECTORY_SEPARATOR . 'remote-missing-status.pdf';
            file_put_contents($pdfPath, '%PDF-remote');
            $requests = [];
            $client = static function (string $method, string $url, array $request) use (&$requests): array {
                $requests[] = ['method' => $method, 'url' => $url, 'request' => $request];
                if ($method === 'POST') {
                    return ['request_check_url' => 'https://api.example/check/missing-status'];
                }

                return ['success' => false, 'error' => 'worker failed without status'];
            };

            $adapter = new MarkerServerAdapter();
            $t->throws(
                InvalidArgumentException::class,
                static fn () => $adapter->convertPdfRemote(
                    [
                        'filepath' => $pdfPath,
                        'max_pages' => null,
                        'langs' => null,
                        'force_ocr' => false,
                        'paginate' => false,
                        'extract_images' => true,
                    ],
                    $client,
                    'secret-key',
                    'https://api.example/marker',
                    maxPolls: 2
                )
            );

            $t->same(['POST', 'GET'], array_column($requests, 'method'));
        } finally {
            $removeTree($root);
        }
    },
    'describes marker server remote polling plan without live HTTP' => static function (TestRunner $t): void {
        $plan = (new MarkerServerAdapter())->remotePollingPlan();

        $t->same('POST', $plan['initial_request_method']);
        $t->same('GET', $plan['poll_request_method']);
        $t->same(300, $plan['max_polls']);
        $t->same(2, $plan['poll_interval_seconds']);
        $t->same('request_check_url', $plan['request_check_url_key']);
        $t->same('status', $plan['poll_status_key']);
        $t->same('complete', $plan['completion_status']);
        $t->same(true, $plan['returns_last_poll_response_after_exhaustion']);
        $t->same(false, $plan['invents_timeout_error']);
        $t->same(false, $plan['executes_live_http']);
        $t->same(false, $plan['executes_python_or_models']);
    },
];
