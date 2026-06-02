<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Throwable;

final class BenchmarkRunner
{
    private BenchmarkReportBuilder $reportBuilder;

    /** @var array<string, mixed>|null */
    private ?array $activeRuntimeFailureContext = null;

    public function __construct(?BenchmarkReportBuilder $reportBuilder = null)
    {
        $this->reportBuilder = $reportBuilder ?? new BenchmarkReportBuilder();
    }

    /**
     * WordPress-safe wrapper for benchmarks/overall.py runtime failures.
     *
     * Upstream fails fast when PDFium page counting or a conversion method
     * raises. This wrapper preserves that default while exposing the active
     * method/document phase as review-only telemetry for import queues.
     *
     * @param array<string, callable(string, string, string, array<string, mixed>): mixed> $methodConverters
     * @param callable(string): int|null $pageCounter
     * @param array<string, int> $chunkLengths
     * @return array{
     *     success: bool,
     *     result: array<string, mixed>|null,
     *     error: string|null,
     *     telemetry: array<string, mixed>|null,
     *     executes_external_tools: false,
     *     executes_python_or_models: false,
     *     error_artifact: array<string, mixed>|null
     * }
     */
    public function runWithErrorTelemetry(
        string $inputFolder,
        string $referenceFolder,
        array $methodConverters,
        ?callable $pageCounter = null,
        ?string $markdownOutputFolder = null,
        array $chunkLengths = [],
        ?string $reportOutputFile = null,
        array $runtimeOptions = [],
        ?string $errorArtifactFile = null
    ): array {
        $this->activeRuntimeFailureContext = [
            'phase' => 'preflight',
            'input_folder' => $inputFolder,
            'reference_folder' => $referenceFolder,
            'markdown_output_folder' => $markdownOutputFolder,
            'report_output' => $reportOutputFile,
        ];

        try {
            $result = $this->run(
                $inputFolder,
                $referenceFolder,
                $methodConverters,
                $pageCounter,
                $markdownOutputFolder,
                $chunkLengths,
                $reportOutputFile,
                $runtimeOptions
            );

            return [
                'success' => true,
                'result' => $result,
                'error' => null,
                'telemetry' => null,
                'executes_external_tools' => false,
                'executes_python_or_models' => false,
                'error_artifact' => null,
            ];
        } catch (Throwable $throwable) {
            $telemetry = $this->runtimeFailureTelemetry($throwable, $this->activeRuntimeFailureContext ?? []);

            return [
                'success' => false,
                'result' => null,
                'error' => $throwable->getMessage(),
                'telemetry' => $telemetry,
                'executes_external_tools' => false,
                'executes_python_or_models' => false,
                'error_artifact' => $errorArtifactFile === null
                    ? null
                    : $this->writeBenchmarkErrorArtifactJson($errorArtifactFile, $telemetry),
            ];
        } finally {
            $this->activeRuntimeFailureContext = null;
        }
    }

    /**
     * Review-only bridge for marker_server.py upload error payloads that need
     * to survive a WordPress benchmark/output artifact roundtrip.
     *
     * @param array<string, mixed> $serverResponse
     * @param array<string, mixed> $context
     * @return array{path: string, filename: string, format: string, success_report_written: false, review_only: true, schema: string, status: string, error: string, size: int, sha256: string}
     */
    public function writeServerBenchmarkErrorArtifactJson(
        string $errorArtifactFile,
        array $serverResponse,
        array $context = []
    ): array {
        $errorArtifactFile = trim($errorArtifactFile);
        if ($errorArtifactFile === '') {
            throw new InvalidArgumentException('Server benchmark error artifact JSON file must not be empty.');
        }

        $serverResponse = $this->serverBenchmarkErrorResponse($serverResponse);
        $artifact = [
            'path' => $errorArtifactFile,
            'filename' => basename($errorArtifactFile),
            'format' => 'json',
            'success_report_written' => false,
            'review_only' => true,
        ];
        $payload = [
            'schema' => 'markerpdf.server_benchmark_error.v1',
            'source' => 'sddai/markerPDF marker_server.py + benchmarks/overall.py + marker/output.py',
            'status' => 'error',
            'success' => false,
            'error' => $serverResponse['error'],
            'message_line' => 'Marker server benchmark output failed: ' . $serverResponse['error'],
            'server_response' => $serverResponse,
            'context' => $this->serverBenchmarkErrorContext($context),
            'artifact' => $artifact,
            'default_server_returns_error_payload' => true,
            'default_benchmark_fails_fast' => true,
            'success_report_written' => false,
            'writes_markdown_after_failure' => false,
            'executes_fastapi' => false,
            'executes_uvicorn' => false,
            'executes_live_http' => false,
            'executes_external_tools' => false,
            'executes_python_or_models' => false,
            'review_only' => true,
        ];

        try {
            $json = json_encode(
                $payload,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                'Unable to encode markerPDF server benchmark error artifact as JSON.',
                previous: $exception
            );
        }

        if (@file_put_contents($errorArtifactFile, $json) === false) {
            throw new RuntimeException('Unable to write markerPDF server benchmark error artifact: ' . $errorArtifactFile);
        }

        return $artifact + [
            'schema' => 'markerpdf.server_benchmark_error.v1',
            'status' => 'error',
            'error' => $serverResponse['error'],
            'size' => $this->artifactSize($errorArtifactFile),
            'sha256' => $this->artifactSha256($errorArtifactFile),
        ];
    }

    /**
     * @return array{path: string, filename: string, schema: string, status: string, error: string, payload: array<string, mixed>, size: int, sha256: string, roundtrip_preserves_server_error: bool, review_only: true, executes_fastapi: false, executes_uvicorn: false, executes_live_http: false, executes_external_tools: false, executes_python_or_models: false}
     */
    public function readServerBenchmarkErrorArtifactJson(string $errorArtifactFile): array
    {
        $errorArtifactFile = trim($errorArtifactFile);
        if ($errorArtifactFile === '') {
            throw new InvalidArgumentException('Server benchmark error artifact JSON file must not be empty.');
        }

        $contents = @file_get_contents($errorArtifactFile);
        if (!is_string($contents)) {
            throw new InvalidArgumentException('Server benchmark error artifact JSON file is not readable: ' . $errorArtifactFile);
        }

        try {
            $payload = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                'Server benchmark error artifact JSON file must contain valid JSON.',
                previous: $exception
            );
        }
        if (!is_array($payload)) {
            throw new InvalidArgumentException('Server benchmark error artifact JSON must decode to an object.');
        }

        $schema = $payload['schema'] ?? null;
        if ($schema !== 'markerpdf.server_benchmark_error.v1') {
            throw new InvalidArgumentException('Server benchmark error artifact JSON has an unexpected schema.');
        }
        if (($payload['success'] ?? null) !== false || ($payload['status'] ?? null) !== 'error') {
            throw new InvalidArgumentException('Server benchmark error artifact JSON must be an error payload.');
        }
        if (($payload['review_only'] ?? null) !== true) {
            throw new InvalidArgumentException('Server benchmark error artifact JSON must be review-only.');
        }

        $error = $payload['error'] ?? null;
        $serverResponse = $payload['server_response'] ?? null;
        if (!is_string($error) || !is_array($serverResponse) || ($serverResponse['error'] ?? null) !== $error) {
            throw new InvalidArgumentException('Server benchmark error artifact JSON did not preserve the server error.');
        }

        return [
            'path' => $errorArtifactFile,
            'filename' => basename($errorArtifactFile),
            'schema' => $schema,
            'status' => 'error',
            'error' => $error,
            'payload' => $payload,
            'size' => $this->artifactSize($errorArtifactFile),
            'sha256' => $this->artifactSha256($errorArtifactFile),
            'roundtrip_preserves_server_error' => true,
            'review_only' => true,
            'executes_fastapi' => false,
            'executes_uvicorn' => false,
            'executes_live_http' => false,
            'executes_external_tools' => false,
            'executes_python_or_models' => false,
        ];
    }

    /**
     * Native success-path bundle across marker_server.py::convert_pdf_local,
     * marker.output::save_markdown, and benchmarks/overall.py markdown output.
     *
     * marker_server.py returns Markdown, base64 PNG images, metadata, and a
     * success flag. The benchmark runner can write Markdown outputs, while
     * marker.output keeps metadata and images in the document output folder.
     * This boundary composes those upstream artifacts without persisting raw
     * server base64 payloads in the review manifest.
     *
     * @param array<string, mixed> $serverResponse
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function writeServerBenchmarkOutputBundle(
        string $outputFolder,
        string $document,
        array $serverResponse,
        array $context = []
    ): array {
        $document = $this->serverBenchmarkOutputDocument($document);
        $serverResponse = $this->serverBenchmarkSuccessResponse($serverResponse);

        $outputArtifacts = (new OutputWriter())->saveMarkdownArtifactBoundary(
            $outputFolder,
            $document,
            $serverResponse['markdown'],
            $this->serverBenchmarkDecodedImages($serverResponse['images']),
            $serverResponse['metadata'],
            includeRuntimePreviewHtml: false
        );

        $bundlePath = $outputArtifacts['subfolder']
            . DIRECTORY_SEPARATOR
            . $this->markdownStem($document)
            . '_benchmark_bundle.json';
        $artifact = [
            'path' => $bundlePath,
            'filename' => basename($bundlePath),
            'format' => 'json',
            'success_report_written' => false,
            'review_only' => true,
        ];
        $successReportWritten = array_key_exists('success_report_written', $context)
            ? (bool) $context['success_report_written']
            : (bool) ($context['final_report_written'] ?? false);

        $payload = [
            'schema' => 'markerpdf.server_benchmark_output_bundle.v1',
            'source' => 'sddai/markerPDF marker_server.py + marker/output.py + benchmarks/overall.py',
            'status' => 'complete',
            'success' => true,
            'document' => $document,
            'server_response' => $this->serverBenchmarkOutputSummary($serverResponse),
            'context' => $this->serverBenchmarkOutputContext($context),
            'output_artifacts' => $this->serverBenchmarkOutputArtifactsForPayload($outputArtifacts),
            'artifact' => $artifact,
            'benchmark_output_bundle_written' => true,
            'writes_markdown_after_success' => true,
            'writes_metadata_after_success' => true,
            'writes_images_after_success' => $serverResponse['images'] !== [],
            'success_report_written' => $successReportWritten,
            'executes_fastapi' => false,
            'executes_uvicorn' => false,
            'executes_live_http' => false,
            'executes_external_tools' => false,
            'executes_python_or_models' => false,
            'review_only' => true,
        ];

        try {
            $json = json_encode(
                $payload,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                'Unable to encode markerPDF server benchmark output bundle as JSON.',
                previous: $exception
            );
        }

        if (@file_put_contents($bundlePath, $json) === false) {
            throw new RuntimeException('Unable to write markerPDF server benchmark output bundle: ' . $bundlePath);
        }

        $payload['bundle_artifact'] = $artifact + [
            'schema' => 'markerpdf.server_benchmark_output_bundle.v1',
            'status' => 'complete',
            'size' => $this->artifactSize($bundlePath),
            'sha256' => $this->artifactSha256($bundlePath),
        ];
        $payload['output_artifacts'] = $outputArtifacts;

        return $payload;
    }

    /**
     * @return array{path: string, filename: string, schema: string, status: string, payload: array<string, mixed>, size: int, sha256: string, roundtrip_preserves_output_bundle: bool, review_only: true, executes_fastapi: false, executes_uvicorn: false, executes_live_http: false, executes_external_tools: false, executes_python_or_models: false}
     */
    public function readServerBenchmarkOutputBundleJson(string $bundleFile): array
    {
        $bundleFile = trim($bundleFile);
        if ($bundleFile === '') {
            throw new InvalidArgumentException('Server benchmark output bundle JSON file must not be empty.');
        }

        $contents = @file_get_contents($bundleFile);
        if (!is_string($contents)) {
            throw new InvalidArgumentException('Server benchmark output bundle JSON file is not readable: ' . $bundleFile);
        }

        try {
            $payload = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                'Server benchmark output bundle JSON file must contain valid JSON.',
                previous: $exception
            );
        }
        if (!is_array($payload)) {
            throw new InvalidArgumentException('Server benchmark output bundle JSON must decode to an object.');
        }
        if (($payload['schema'] ?? null) !== 'markerpdf.server_benchmark_output_bundle.v1') {
            throw new InvalidArgumentException('Server benchmark output bundle JSON has an unexpected schema.');
        }
        if (($payload['success'] ?? null) !== true || ($payload['status'] ?? null) !== 'complete') {
            throw new InvalidArgumentException('Server benchmark output bundle JSON must be a complete success payload.');
        }
        if (($payload['review_only'] ?? null) !== true) {
            throw new InvalidArgumentException('Server benchmark output bundle JSON must be review-only.');
        }
        if (!is_string($payload['document'] ?? null)) {
            throw new InvalidArgumentException('Server benchmark output bundle JSON is missing the document name.');
        }
        if (!is_array($payload['output_artifacts'] ?? null) || !is_array($payload['artifact'] ?? null)) {
            throw new InvalidArgumentException('Server benchmark output bundle JSON is missing artifact metadata.');
        }

        return [
            'path' => $bundleFile,
            'filename' => basename($bundleFile),
            'schema' => 'markerpdf.server_benchmark_output_bundle.v1',
            'status' => 'complete',
            'payload' => $payload,
            'size' => $this->artifactSize($bundleFile),
            'sha256' => $this->artifactSha256($bundleFile),
            'roundtrip_preserves_output_bundle' => true,
            'review_only' => true,
            'executes_fastapi' => false,
            'executes_uvicorn' => false,
            'executes_live_http' => false,
            'executes_external_tools' => false,
            'executes_python_or_models' => false,
        ];
    }

    /**
     * Review-only bridge for successful marker_server.py upload conversion
     * payloads that WordPress benchmark gates need to archive without copying
     * uploaded PDF bytes or image payloads into JSON.
     *
     * @param array<string, mixed> $serverResponse
     * @param array<string, mixed> $context
     * @return array{path: string, filename: string, format: string, success_report_written: bool, review_only: true, schema: string, status: string, markdown_sha256: string, size: int, sha256: string}
     */
    public function writeServerBenchmarkUploadArtifactJson(
        string $artifactFile,
        array $serverResponse,
        array $context = []
    ): array {
        $artifactFile = trim($artifactFile);
        if ($artifactFile === '') {
            throw new InvalidArgumentException('Server benchmark upload artifact JSON file must not be empty.');
        }

        $serverResponse = $this->serverBenchmarkUploadResponse($serverResponse);
        $context = $this->serverBenchmarkUploadContext($context);
        $successReportWritten = $context['success_report_written'] ?? false;
        $artifact = [
            'path' => $artifactFile,
            'filename' => basename($artifactFile),
            'format' => 'json',
            'success_report_written' => $successReportWritten,
            'review_only' => true,
        ];
        $payload = [
            'schema' => 'markerpdf.server_benchmark_upload.v1',
            'source' => 'sddai/markerPDF marker_server.py + benchmarks/overall.py + marker/output.py',
            'status' => 'success',
            'success' => true,
            'message_line' => $this->serverBenchmarkUploadMessageLine($context),
            'server_response' => $serverResponse,
            'context' => $context,
            'artifact' => $artifact,
            'default_server_upload_removes_temp_file' => true,
            'default_benchmark_writes_markdown_on_success' => true,
            'default_benchmark_writes_report_on_success' => $successReportWritten,
            'excludes_uploaded_pdf_bytes' => true,
            'executes_fastapi' => false,
            'executes_uvicorn' => false,
            'executes_live_http' => false,
            'executes_external_tools' => false,
            'executes_python_or_models' => false,
            'review_only' => true,
        ];

        try {
            $json = json_encode(
                $payload,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                'Unable to encode markerPDF server benchmark upload artifact as JSON.',
                previous: $exception
            );
        }

        if (@file_put_contents($artifactFile, $json) === false) {
            throw new RuntimeException('Unable to write markerPDF server benchmark upload artifact: ' . $artifactFile);
        }

        return $artifact + [
            'schema' => 'markerpdf.server_benchmark_upload.v1',
            'status' => 'success',
            'markdown_sha256' => $serverResponse['markdown_sha256'],
            'size' => $this->artifactSize($artifactFile),
            'sha256' => $this->artifactSha256($artifactFile),
        ];
    }

    /**
     * @return array{path: string, filename: string, schema: string, status: string, payload: array<string, mixed>, size: int, sha256: string, roundtrip_preserves_server_success: bool, roundtrip_preserves_markdown_hash: bool, review_only: true, executes_fastapi: false, executes_uvicorn: false, executes_live_http: false, executes_external_tools: false, executes_python_or_models: false}
     */
    public function readServerBenchmarkUploadArtifactJson(string $artifactFile): array
    {
        $artifactFile = trim($artifactFile);
        if ($artifactFile === '') {
            throw new InvalidArgumentException('Server benchmark upload artifact JSON file must not be empty.');
        }

        $contents = @file_get_contents($artifactFile);
        if (!is_string($contents)) {
            throw new InvalidArgumentException('Server benchmark upload artifact JSON file is not readable: ' . $artifactFile);
        }

        try {
            $payload = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException(
                'Server benchmark upload artifact JSON file must contain valid JSON.',
                previous: $exception
            );
        }
        if (!is_array($payload)) {
            throw new InvalidArgumentException('Server benchmark upload artifact JSON must decode to an object.');
        }

        $schema = $payload['schema'] ?? null;
        if ($schema !== 'markerpdf.server_benchmark_upload.v1') {
            throw new InvalidArgumentException('Server benchmark upload artifact JSON has an unexpected schema.');
        }
        if (($payload['success'] ?? null) !== true || ($payload['status'] ?? null) !== 'success') {
            throw new InvalidArgumentException('Server benchmark upload artifact JSON must be a success payload.');
        }
        if (($payload['review_only'] ?? null) !== true) {
            throw new InvalidArgumentException('Server benchmark upload artifact JSON must be review-only.');
        }

        $serverResponse = $payload['server_response'] ?? null;
        if (!is_array($serverResponse) || ($serverResponse['success'] ?? null) !== true) {
            throw new InvalidArgumentException('Server benchmark upload artifact JSON did not preserve server success.');
        }
        $markdown = $serverResponse['markdown'] ?? null;
        $markdownHash = $serverResponse['markdown_sha256'] ?? null;
        if (!is_string($markdown) || !is_string($markdownHash) || hash('sha256', $markdown) !== $markdownHash) {
            throw new InvalidArgumentException('Server benchmark upload artifact JSON did not preserve the markdown hash.');
        }
        if (($serverResponse['images_are_summarized'] ?? null) !== true) {
            throw new InvalidArgumentException('Server benchmark upload artifact JSON must summarize image payloads.');
        }

        return [
            'path' => $artifactFile,
            'filename' => basename($artifactFile),
            'schema' => $schema,
            'status' => 'success',
            'payload' => $payload,
            'size' => $this->artifactSize($artifactFile),
            'sha256' => $this->artifactSha256($artifactFile),
            'roundtrip_preserves_server_success' => true,
            'roundtrip_preserves_markdown_hash' => true,
            'review_only' => true,
            'executes_fastapi' => false,
            'executes_uvicorn' => false,
            'executes_live_http' => false,
            'executes_external_tools' => false,
            'executes_python_or_models' => false,
        ];
    }

    /**
     * Native upload benchmark boundary across marker_server.py upload handling,
     * benchmarks/overall.py fail-fast output behavior, and marker.output artifact
     * persistence. A successful upload conversion becomes a benchmark output
     * bundle; a failed upload response becomes a review-only error artifact.
     *
     * @param array<string, mixed> $serverResponse
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function writeServerUploadBenchmarkResult(
        string $outputFolder,
        string $errorArtifactFile,
        string $document,
        array $serverResponse,
        array $context = []
    ): array {
        $document = $this->serverBenchmarkOutputDocument($document);
        if (!array_key_exists('phase', $context)) {
            $context['phase'] = 'server_upload';
        }
        if (!array_key_exists('method', $context)) {
            $context['method'] = 'marker';
        }
        $context['document'] = $document;

        if (($serverResponse['success'] ?? null) === true) {
            $bundle = $this->writeServerBenchmarkOutputBundle($outputFolder, $document, $serverResponse, $context);
            $roundtrip = $this->readServerBenchmarkOutputBundleJson($bundle['bundle_artifact']['path']);

            return [
                'schema' => 'markerpdf.server_upload_benchmark_result.v1',
                'source' => 'sddai/markerPDF marker_server.py + benchmarks/overall.py + marker/output.py',
                'status' => 'complete',
                'success' => true,
                'result_kind' => 'output_bundle',
                'document' => $document,
                'context' => $bundle['context'],
                'server_response' => $bundle['server_response'],
                'output_bundle' => [
                    'schema' => $bundle['bundle_artifact']['schema'],
                    'status' => $bundle['bundle_artifact']['status'],
                    'path' => $bundle['bundle_artifact']['path'],
                    'filename' => $bundle['bundle_artifact']['filename'],
                    'size' => $bundle['bundle_artifact']['size'],
                    'sha256' => $bundle['bundle_artifact']['sha256'],
                    'roundtrip_preserves_output_bundle' => $roundtrip['roundtrip_preserves_output_bundle'],
                ],
                'output_artifacts' => $bundle['output_artifacts'],
                'error_artifact' => null,
                'benchmark_output_bundle_written' => true,
                'error_artifact_written' => false,
                'success_report_written' => (bool) $bundle['success_report_written'],
                'writes_markdown_after_failure' => false,
                'executes_fastapi' => false,
                'executes_uvicorn' => false,
                'executes_live_http' => false,
                'executes_external_tools' => false,
                'executes_python_or_models' => false,
                'review_only' => true,
            ];
        }

        if (($serverResponse['success'] ?? null) === false) {
            $this->ensureArtifactDirectory($errorArtifactFile, 'server upload benchmark error artifact');
            $artifact = $this->writeServerBenchmarkErrorArtifactJson($errorArtifactFile, $serverResponse, $context);
            $roundtrip = $this->readServerBenchmarkErrorArtifactJson($artifact['path']);

            return [
                'schema' => 'markerpdf.server_upload_benchmark_result.v1',
                'source' => 'sddai/markerPDF marker_server.py + benchmarks/overall.py + marker/output.py',
                'status' => 'error',
                'success' => false,
                'result_kind' => 'error_artifact',
                'document' => $document,
                'context' => $roundtrip['payload']['context'],
                'server_response' => $roundtrip['payload']['server_response'],
                'output_bundle' => null,
                'output_artifacts' => null,
                'error_artifact' => [
                    'schema' => $artifact['schema'],
                    'status' => $artifact['status'],
                    'path' => $artifact['path'],
                    'filename' => $artifact['filename'],
                    'error' => $artifact['error'],
                    'size' => $artifact['size'],
                    'sha256' => $artifact['sha256'],
                    'roundtrip_preserves_server_error' => $roundtrip['roundtrip_preserves_server_error'],
                ],
                'benchmark_output_bundle_written' => false,
                'error_artifact_written' => true,
                'success_report_written' => false,
                'writes_markdown_after_failure' => false,
                'executes_fastapi' => false,
                'executes_uvicorn' => false,
                'executes_live_http' => false,
                'executes_external_tools' => false,
                'executes_python_or_models' => false,
                'review_only' => true,
            ];
        }

        throw new InvalidArgumentException(
            'Server upload benchmark result requires a marker server response with success true or false.'
        );
    }

    /**
     * Native supplied-converter boundary for benchmarks/overall.py::main.
     *
     * @param array<string, callable(string, string, string, array<string, mixed>): mixed> $methodConverters
     * @param callable(string): int|null $pageCounter
     * @param array<string, int> $chunkLengths
     * @return array{
     *     benchmark_files: list<string>,
     *     runs: list<array<string, mixed>>,
     *     report: array<string, mixed>,
     *     report_output: string|null,
     *     output_tables: array<string, mixed>,
     *     written_markdown: list<string>,
     *     runtime: array<string, mixed>
     * }
     */
    public function run(
        string $inputFolder,
        string $referenceFolder,
        array $methodConverters,
        ?callable $pageCounter = null,
        ?string $markdownOutputFolder = null,
        array $chunkLengths = [],
        ?string $reportOutputFile = null,
        array $runtimeOptions = []
    ): array {
        if (!isset($methodConverters['marker'])) {
            throw new InvalidArgumentException('Benchmark runner requires a marker method converter.');
        }
        if (!is_dir($inputFolder)) {
            throw new InvalidArgumentException('Benchmark input folder does not exist: ' . $inputFolder);
        }
        if (!is_dir($referenceFolder)) {
            throw new InvalidArgumentException('Benchmark reference folder does not exist: ' . $referenceFolder);
        }
        if ($markdownOutputFolder !== null && !is_dir($markdownOutputFolder)) {
            throw new InvalidArgumentException('Benchmark markdown output folder does not exist: ' . $markdownOutputFolder);
        }

        foreach ($methodConverters as $method => $converter) {
            if (!is_string($method) || $method === '' || !is_callable($converter)) {
                throw new InvalidArgumentException('Benchmark method converters must be keyed by non-empty method names.');
            }
        }

        $runtime = $this->normalizeRuntimeOptions($runtimeOptions);
        $methodOrder = $runtime['methods'] ?? array_values(array_keys($methodConverters));
        foreach ($methodOrder as $method) {
            if (!isset($methodConverters[$method])) {
                throw new InvalidArgumentException("Benchmark runtime method {$method} requires a supplied converter.");
            }
        }

        $benchmarkFiles = $this->benchmarkFiles($inputFolder);
        if ($benchmarkFiles === []) {
            throw new InvalidArgumentException('Benchmark input folder must contain at least one PDF file.');
        }

        $runs = [];
        $writtenMarkdown = [];
        $runtimeReport = [
            'methods' => $methodOrder,
            'marker_batch_multiplier' => $runtime['marker_batch_multiplier'],
            'nougat_batch_size' => $runtime['nougat_batch_size'],
            'profile_memory' => $runtime['profile_memory'],
            'model_load_snapshot' => $runtime['profile_memory'] ? 'model_load.pickle' : null,
            'conversion_snapshots' => [],
            'memory_snapshot_failures' => [],
            'memory_snapshot_failure_count' => 0,
            'continues_after_memory_snapshot_failure' => true,
            'executes_external_tools' => false,
            'callback_sandbox' => [
                'enabled' => $runtime['callback_sandbox'],
                'watched_inputs' => $markdownOutputFolder === null
                    ? ['pdf', 'reference']
                    : ['pdf', 'reference', 'markdown_output_folder'],
                'runner_writes_markdown_after_callback' => $markdownOutputFolder !== null,
            ],
        ];
        if ($runtime['profile_memory']) {
            $this->appendMemorySnapshotFailure(
                $runtimeReport,
                'model_load.pickle',
                $runtime['memory_snapshot_errors'],
                [
                    'phase' => 'model_load',
                    'method' => null,
                    'document' => null,
                    'benchmark_index' => null,
                ]
            );
        }

        foreach ($benchmarkFiles as $documentIndex => $pdfFilename) {
            $pdfPath = $inputFolder . DIRECTORY_SEPARATOR . $pdfFilename;
            $mdFilename = $this->markdownFilename($pdfFilename);
            $referencePath = $referenceFolder . DIRECTORY_SEPARATOR . $mdFilename;
            $this->activeRuntimeFailureContext = $this->runtimeFailureContext(
                'reference_read',
                null,
                $pdfFilename,
                $pdfPath,
                $referencePath,
                $documentIndex,
                ['callback_sandbox' => $runtime['callback_sandbox']],
                $markdownOutputFolder,
                $reportOutputFile
            );
            $reference = @file_get_contents($referencePath);
            if (!is_string($reference)) {
                throw new InvalidArgumentException('Benchmark reference markdown is not readable: ' . $referencePath);
            }

            $pageCounterSnapshot = $runtime['callback_sandbox'] && $pageCounter !== null
                ? $this->sandboxSnapshot($pdfPath, $referencePath, $markdownOutputFolder)
                : null;
            $this->activeRuntimeFailureContext = $this->runtimeFailureContext(
                'page_counter',
                null,
                $pdfFilename,
                $pdfPath,
                $referencePath,
                $documentIndex,
                ['callback_sandbox' => $runtime['callback_sandbox']],
                $markdownOutputFolder,
                $reportOutputFile
            );
            try {
                $pages = $pageCounter === null ? 1 : (int) $pageCounter($pdfPath);
            } catch (Throwable $throwable) {
                if ($pageCounterSnapshot !== null) {
                    $this->assertSandboxUnchanged($pageCounterSnapshot, "page counter for {$pdfFilename}");
                }
                throw $throwable;
            }
            if ($pageCounterSnapshot !== null) {
                $this->assertSandboxUnchanged($pageCounterSnapshot, "page counter for {$pdfFilename}");
            }
            if ($pages < 1) {
                throw new InvalidArgumentException('Benchmark page counter must return a positive integer for ' . $pdfFilename);
            }

            foreach ($methodOrder as $method) {
                $converter = $methodConverters[$method];
                $context = $this->conversionContext($runtime, $method, $pdfFilename, $documentIndex);
                $converterSnapshot = $runtime['callback_sandbox']
                    ? $this->sandboxSnapshot($pdfPath, $referencePath, $markdownOutputFolder)
                    : null;
                $start = microtime(true);
                $this->activeRuntimeFailureContext = $this->runtimeFailureContext(
                    'converter',
                    $method,
                    $pdfFilename,
                    $pdfPath,
                    $referencePath,
                    $documentIndex,
                    $context,
                    $markdownOutputFolder,
                    $reportOutputFile
                );
                try {
                    $conversion = $this->normalizeConversion($converter($pdfPath, $pdfFilename, $reference, $context));
                } catch (Throwable $throwable) {
                    if ($converterSnapshot !== null) {
                        $this->assertSandboxUnchanged($converterSnapshot, "{$method}/{$pdfFilename}");
                    }
                    throw $throwable;
                }
                if ($converterSnapshot !== null) {
                    $this->assertSandboxUnchanged($converterSnapshot, "{$method}/{$pdfFilename}");
                }
                $elapsed = microtime(true) - $start;

                $runs[] = [
                    'method' => $method,
                    'document' => $pdfFilename,
                    'hypothesis' => $conversion['text'],
                    'reference' => $reference,
                    'time' => $elapsed,
                    'pages' => $pages,
                    'chunkLength' => $chunkLengths[$pdfFilename] ?? 500,
                ];

                if (isset($context['memory_snapshot']) && is_string($context['memory_snapshot'])) {
                    $runtimeReport['conversion_snapshots'][] = [
                        'method' => $method,
                        'document' => $pdfFilename,
                        'snapshot' => $context['memory_snapshot'],
                    ];
                    $this->appendMemorySnapshotFailure(
                        $runtimeReport,
                        $context['memory_snapshot'],
                        $runtime['memory_snapshot_errors'],
                        [
                            'phase' => 'converter',
                            'method' => $method,
                            'document' => $pdfFilename,
                            'benchmark_index' => $documentIndex,
                        ]
                    );
                }

                if ($markdownOutputFolder !== null) {
                    $outPath = $markdownOutputFolder . DIRECTORY_SEPARATOR . $method . '_' . $mdFilename;
                    $this->activeRuntimeFailureContext = $this->runtimeFailureContext(
                        'markdown_write',
                        $method,
                        $pdfFilename,
                        $pdfPath,
                        $referencePath,
                        $documentIndex,
                        $context + ['markdown_output' => $outPath],
                        $markdownOutputFolder,
                        $reportOutputFile
                    );
                    if (file_put_contents($outPath, $conversion['text']) === false) {
                        throw new InvalidArgumentException('Benchmark markdown output is not writable: ' . $outPath);
                    }
                    $writtenMarkdown[] = $outPath;
                }
            }
        }

        $report = $this->reportBuilder->build($runs);
        $outputTables = $this->reportBuilder->outputTables($report);
        if ($reportOutputFile !== null) {
            $this->activeRuntimeFailureContext = [
                'phase' => 'report_write',
                'report_output' => $reportOutputFile,
                'markdown_output_folder' => $markdownOutputFolder,
            ];
            $this->reportBuilder->writeJsonReport($reportOutputFile, $report);
        }

        return [
            'benchmark_files' => $benchmarkFiles,
            'runs' => $runs,
            'report' => $report,
            'report_output' => $reportOutputFile,
            'output_tables' => $outputTables,
            'written_markdown' => $writtenMarkdown,
            'runtime' => $runtimeReport,
        ];
    }

    /**
     * Native non-executing boundary for benchmarks/overall.py::stop_memory_profiling.
     *
     * Upstream logs snapshot dump failures and still disables CUDA memory
     * history. The PHP port records that review metadata without touching CUDA.
     *
     * @return array{snapshot: string, error: string, log_line: string, continues_after_failure: true, recording_disabled_after_error: true, executes_cuda_memory_history: false, review_only: true}
     */
    public function memorySnapshotFailureReport(string $snapshotFile, Throwable|string $error): array
    {
        $snapshotFile = trim($snapshotFile);
        if ($snapshotFile === '') {
            throw new InvalidArgumentException('Benchmark memory snapshot file must be a non-empty string.');
        }

        $message = $error instanceof Throwable ? $error->getMessage() : (string) $error;

        return [
            'snapshot' => $snapshotFile,
            'error' => $message,
            'log_line' => 'Failed to capture memory snapshot ' . $message,
            'continues_after_failure' => true,
            'recording_disabled_after_error' => true,
            'executes_cuda_memory_history' => false,
            'review_only' => true,
        ];
    }

    /**
     * @param array<string, mixed> $runtimeReport
     * @param array<string, Throwable|string> $memorySnapshotErrors
     * @param array{phase: string, method: string|null, document: string|null, benchmark_index: int|null} $context
     */
    private function appendMemorySnapshotFailure(
        array &$runtimeReport,
        string $snapshotFile,
        array $memorySnapshotErrors,
        array $context
    ): void {
        if (!array_key_exists($snapshotFile, $memorySnapshotErrors)) {
            return;
        }

        $runtimeReport['memory_snapshot_failures'][] = $context + $this->memorySnapshotFailureReport(
            $snapshotFile,
            $memorySnapshotErrors[$snapshotFile]
        );
        $runtimeReport['memory_snapshot_failure_count'] = count($runtimeReport['memory_snapshot_failures']);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function runtimeFailureTelemetry(Throwable $throwable, array $context): array
    {
        $phase = isset($context['phase']) && is_string($context['phase'])
            ? $context['phase']
            : 'preflight';
        $method = isset($context['method']) && is_string($context['method'])
            ? $context['method']
            : null;
        $document = isset($context['document']) && is_string($context['document'])
            ? $context['document']
            : null;

        $trace = $throwable->getTraceAsString();
        $traceback = get_class($throwable) . ': ' . $throwable->getMessage();
        if ($trace !== '') {
            $traceback .= "\n" . $trace;
        }

        return [
            'phase' => $phase,
            'method' => $method,
            'document' => $document,
            'benchmark_index' => $context['benchmark_index'] ?? null,
            'input_folder' => $context['input_folder'] ?? null,
            'reference_folder' => $context['reference_folder'] ?? null,
            'pdf_path' => $context['pdf_path'] ?? null,
            'reference_path' => $context['reference_path'] ?? null,
            'markdown_output_folder' => $context['markdown_output_folder'] ?? null,
            'markdown_output' => $context['markdown_output'] ?? null,
            'report_output' => $context['report_output'] ?? null,
            'memory_snapshot' => $context['memory_snapshot'] ?? null,
            'callback_sandbox' => $context['callback_sandbox'] ?? null,
            'error' => $throwable->getMessage(),
            'message_line' => $this->runtimeFailureMessageLine($phase, $method, $document, $throwable),
            'traceback' => $traceback,
            'traceback_available' => $traceback !== '',
            'default_runner_fails_fast' => true,
            'continues_after_failure' => false,
            'writes_markdown_after_failure' => false,
            'executes_external_tools' => false,
            'executes_python_or_models' => false,
            'review_only' => true,
        ];
    }

    /**
     * @param array<string, mixed>|null $context
     * @return array<string, mixed>
     */
    private function runtimeFailureContext(
        string $phase,
        ?string $method,
        string $document,
        string $pdfPath,
        string $referencePath,
        int $documentIndex,
        ?array $context,
        ?string $markdownOutputFolder,
        ?string $reportOutputFile
    ): array {
        return [
            'phase' => $phase,
            'method' => $method,
            'document' => $document,
            'benchmark_index' => $documentIndex,
            'pdf_path' => $pdfPath,
            'reference_path' => $referencePath,
            'markdown_output_folder' => $markdownOutputFolder,
            'markdown_output' => $context['markdown_output'] ?? null,
            'report_output' => $reportOutputFile,
            'memory_snapshot' => $context['memory_snapshot'] ?? null,
            'callback_sandbox' => $context['callback_sandbox'] ?? null,
        ];
    }

    private function runtimeFailureMessageLine(
        string $phase,
        ?string $method,
        ?string $document,
        Throwable $throwable
    ): string {
        if ($phase === 'converter' && $method !== null && $document !== null) {
            return "Benchmark method {$method} failed for {$document}: " . $throwable->getMessage();
        }
        if ($phase === 'page_counter' && $document !== null) {
            return "Benchmark page counter failed for {$document}: " . $throwable->getMessage();
        }
        if ($phase === 'reference_read' && $document !== null) {
            return "Benchmark reference read failed for {$document}: " . $throwable->getMessage();
        }
        if ($phase === 'markdown_write' && $method !== null && $document !== null) {
            return "Benchmark markdown write failed for {$method}/{$document}: " . $throwable->getMessage();
        }
        if ($phase === 'report_write') {
            return 'Benchmark report write failed: ' . $throwable->getMessage();
        }

        return 'Benchmark runner failed during ' . $phase . ': ' . $throwable->getMessage();
    }

    /**
     * @param array<string, mixed> $serverResponse
     * @return array<string, mixed>
     */
    private function serverBenchmarkErrorResponse(array $serverResponse): array
    {
        if (($serverResponse['success'] ?? null) !== false) {
            throw new InvalidArgumentException('Server benchmark error artifact requires a failed marker server response.');
        }

        $error = $serverResponse['error'] ?? null;
        if (!is_string($error) || trim($error) === '') {
            throw new InvalidArgumentException('Server benchmark error artifact requires a non-empty server error string.');
        }

        $serverResponse['success'] = false;
        $serverResponse['error'] = $error;

        return $serverResponse;
    }

    private function serverBenchmarkOutputDocument(string $document): string
    {
        $document = basename(str_replace('\\', '/', trim($document)));
        if ($document === '' || $document === '.' || $document === '..') {
            throw new InvalidArgumentException('Server benchmark output bundle document must not be empty.');
        }

        return $document;
    }

    /**
     * @param array<string, mixed> $serverResponse
     * @return array{success: true, markdown: string, images: array<string, mixed>, metadata: array<string, mixed>}
     */
    private function serverBenchmarkSuccessResponse(array $serverResponse): array
    {
        if (($serverResponse['success'] ?? null) !== true) {
            throw new InvalidArgumentException('Server benchmark output bundle requires a successful marker server response.');
        }

        $markdown = $serverResponse['markdown']
            ?? $serverResponse['full_text']
            ?? $serverResponse['text']
            ?? null;
        if (!is_string($markdown)) {
            throw new InvalidArgumentException('Server benchmark output bundle requires markdown text.');
        }

        $images = $serverResponse['images'] ?? [];
        $metadata = $serverResponse['metadata'] ?? $serverResponse['out_metadata'] ?? [];
        if (!is_array($images) || !is_array($metadata)) {
            throw new InvalidArgumentException('Server benchmark output bundle images and metadata must be arrays.');
        }

        return [
            'success' => true,
            'markdown' => $markdown,
            'images' => $images,
            'metadata' => $metadata,
        ];
    }

    /**
     * @param array<string, mixed> $images
     * @return array<string, string>
     */
    private function serverBenchmarkDecodedImages(array $images): array
    {
        $decoded = [];
        foreach ($images as $filename => $encoded) {
            if (!is_string($filename) || trim($filename) === '') {
                throw new InvalidArgumentException('Server benchmark output bundle image names must be non-empty strings.');
            }
            if (!is_string($encoded)) {
                throw new InvalidArgumentException('Server benchmark output bundle images must be base64 PNG strings.');
            }

            $bytes = base64_decode($encoded, strict: true);
            if (!is_string($bytes) || $bytes === '') {
                throw new InvalidArgumentException('Server benchmark output bundle images must be valid non-empty base64 PNG strings.');
            }

            $decoded[$filename] = $bytes;
        }

        return $decoded;
    }

    /**
     * @param array{markdown: string, images: array<string, mixed>, metadata: array<string, mixed>} $serverResponse
     * @return array{success: true, markdown_size: int, image_count: int, metadata_keys: list<string>, preserves_base64_images_in_manifest: false}
     */
    private function serverBenchmarkOutputSummary(array $serverResponse): array
    {
        $metadataKeys = array_map('strval', array_keys($serverResponse['metadata']));
        sort($metadataKeys, SORT_STRING);

        return [
            'success' => true,
            'markdown_size' => strlen($serverResponse['markdown']),
            'image_count' => count($serverResponse['images']),
            'metadata_keys' => $metadataKeys,
            'preserves_base64_images_in_manifest' => false,
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function serverBenchmarkOutputContext(array $context): array
    {
        $normalized = [
            'phase' => $this->optionalContextString($context['phase'] ?? 'server_convert', 'phase') ?? 'server_convert',
            'method' => $this->optionalContextString($context['method'] ?? null, 'method'),
            'document' => $this->optionalContextString($context['document'] ?? null, 'document'),
            'benchmark_index' => $this->optionalContextInt($context['benchmark_index'] ?? null, 'benchmark_index'),
            'markdown_output_folder' => $this->optionalContextString($context['markdown_output_folder'] ?? null, 'markdown_output_folder'),
            'report_output' => $this->optionalContextString($context['report_output'] ?? null, 'report_output'),
            'upload_removed' => array_key_exists('upload_removed', $context) ? (bool) $context['upload_removed'] : null,
            'request_count' => $this->optionalContextInt($context['request_count'] ?? null, 'request_count'),
            'success_report_written' => array_key_exists('success_report_written', $context)
                ? (bool) $context['success_report_written']
                : (array_key_exists('final_report_written', $context) ? (bool) $context['final_report_written'] : false),
        ];

        foreach ($context as $key => $value) {
            if (is_string($key) && !array_key_exists($key, $normalized)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $outputArtifacts
     * @return array<string, mixed>
     */
    private function serverBenchmarkOutputArtifactsForPayload(array $outputArtifacts): array
    {
        $imageArtifacts = [];
        foreach ($outputArtifacts['image_artifacts'] ?? [] as $artifact) {
            if (!is_array($artifact)) {
                continue;
            }
            $imageArtifacts[] = [
                'filename' => $artifact['filename'] ?? null,
                'path' => $artifact['path'] ?? null,
                'format' => $artifact['format'] ?? null,
                'mime_type' => $artifact['mime_type'] ?? null,
                'size' => $artifact['size'] ?? null,
                'sha256' => $artifact['sha256'] ?? null,
                'persisted_to_output_folder' => $artifact['persisted_to_output_folder'] ?? null,
                'runtime_preview_embeddable' => $artifact['runtime_preview_embeddable'] ?? null,
            ];
        }

        $runtimePreview = $outputArtifacts['runtime_preview'] ?? [];
        if (is_array($runtimePreview)) {
            unset($runtimePreview['html']);
        }

        return [
            'source' => $outputArtifacts['source'] ?? null,
            'upstream_boundary' => $outputArtifacts['upstream_boundary'] ?? null,
            'filename' => $outputArtifacts['filename'] ?? null,
            'output_folder' => $outputArtifacts['output_folder'] ?? null,
            'subfolder' => $outputArtifacts['subfolder'] ?? null,
            'markdown_artifact' => $outputArtifacts['markdown_artifact'] ?? null,
            'metadata_artifact' => $outputArtifacts['metadata_artifact'] ?? null,
            'image_artifacts' => $imageArtifacts,
            'image_count' => count($imageArtifacts),
            'runtime_preview' => $runtimePreview,
        ];
    }

    private function markdownStem(string $filename): string
    {
        $lastDot = strrpos($filename, '.');
        if ($lastDot === false) {
            return $filename;
        }

        return substr($filename, 0, $lastDot);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function serverBenchmarkErrorContext(array $context): array
    {
        $normalized = [
            'phase' => $this->optionalContextString($context['phase'] ?? 'server_upload', 'phase') ?? 'server_upload',
            'method' => $this->optionalContextString($context['method'] ?? null, 'method'),
            'document' => $this->optionalContextString($context['document'] ?? null, 'document'),
            'benchmark_index' => $this->optionalContextInt($context['benchmark_index'] ?? null, 'benchmark_index'),
            'markdown_output_folder' => $this->optionalContextString($context['markdown_output_folder'] ?? null, 'markdown_output_folder'),
            'report_output' => $this->optionalContextString($context['report_output'] ?? null, 'report_output'),
            'upload_removed' => array_key_exists('upload_removed', $context) ? (bool) $context['upload_removed'] : null,
            'request_count' => $this->optionalContextInt($context['request_count'] ?? null, 'request_count'),
        ];

        foreach ($context as $key => $value) {
            if (is_string($key) && !array_key_exists($key, $normalized)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $serverResponse
     * @return array<string, mixed>
     */
    private function serverBenchmarkUploadResponse(array $serverResponse): array
    {
        if (($serverResponse['success'] ?? null) !== true) {
            throw new InvalidArgumentException('Server benchmark upload artifact requires a successful marker server response.');
        }

        $markdown = $serverResponse['markdown']
            ?? $serverResponse['full_text']
            ?? $serverResponse['text']
            ?? null;
        if (!is_string($markdown)) {
            throw new InvalidArgumentException('Server benchmark upload artifact requires server markdown text.');
        }

        $metadata = $serverResponse['metadata'] ?? [];
        if (!is_array($metadata)) {
            throw new InvalidArgumentException('Server benchmark upload artifact metadata must be an object.');
        }

        $images = $serverResponse['images'] ?? [];
        if (!is_array($images)) {
            throw new InvalidArgumentException('Server benchmark upload artifact images must be an object.');
        }

        $metadataKeys = array_values(array_filter(array_keys($metadata), 'is_string'));
        sort($metadataKeys, SORT_STRING);

        return [
            'success' => true,
            'markdown' => $markdown,
            'markdown_sha256' => hash('sha256', $markdown),
            'markdown_byte_length' => strlen($markdown),
            'metadata' => $metadata,
            'metadata_keys' => $metadataKeys,
            'image_count' => count($images),
            'images' => $this->serverBenchmarkImageSummaries($images),
            'images_are_summarized' => true,
        ];
    }

    /**
     * @param array<string, mixed> $images
     * @return list<array<string, mixed>>
     */
    private function serverBenchmarkImageSummaries(array $images): array
    {
        $summaries = [];
        foreach ($images as $filename => $imagePayload) {
            if (!is_string($filename) || $filename === '') {
                throw new InvalidArgumentException('Server benchmark upload artifact image filenames must be non-empty strings.');
            }
            if (!is_string($imagePayload)) {
                throw new InvalidArgumentException('Server benchmark upload artifact images must be base64 strings.');
            }

            $decoded = base64_decode($imagePayload, true);
            $summaries[] = [
                'filename' => $filename,
                'base64_length' => strlen($imagePayload),
                'base64_sha256' => hash('sha256', $imagePayload),
                'decoded_size' => is_string($decoded) ? strlen($decoded) : null,
                'decoded_sha256' => is_string($decoded) ? hash('sha256', $decoded) : null,
                'base64_valid' => is_string($decoded),
            ];
        }

        usort(
            $summaries,
            static fn (array $left, array $right): int => strcmp((string) $left['filename'], (string) $right['filename'])
        );

        return $summaries;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function serverBenchmarkUploadContext(array $context): array
    {
        $normalized = [
            'phase' => $this->optionalContextString($context['phase'] ?? 'server_upload_success', 'phase') ?? 'server_upload_success',
            'method' => $this->optionalContextString($context['method'] ?? null, 'method'),
            'document' => $this->optionalContextString($context['document'] ?? null, 'document'),
            'benchmark_index' => $this->optionalContextInt($context['benchmark_index'] ?? null, 'benchmark_index'),
            'markdown_output_folder' => $this->optionalContextString($context['markdown_output_folder'] ?? null, 'markdown_output_folder'),
            'markdown_output' => $this->optionalContextString($context['markdown_output'] ?? null, 'markdown_output'),
            'report_output' => $this->optionalContextString($context['report_output'] ?? null, 'report_output'),
            'uploaded_filename' => $this->optionalContextString($context['uploaded_filename'] ?? null, 'uploaded_filename'),
            'server_route' => $this->optionalContextString($context['server_route'] ?? null, 'server_route'),
            'upload_removed' => array_key_exists('upload_removed', $context) ? (bool) $context['upload_removed'] : null,
            'request_count' => $this->optionalContextInt($context['request_count'] ?? null, 'request_count'),
            'pages' => $this->optionalContextInt($context['pages'] ?? null, 'pages'),
            'score' => $this->optionalContextFloat($context['score'] ?? null, 'score'),
            'time' => $this->optionalContextFloat($context['time'] ?? null, 'time'),
            'success_report_written' => array_key_exists('success_report_written', $context) ? (bool) $context['success_report_written'] : false,
        ];

        foreach ($context as $key => $value) {
            if (is_string($key) && !array_key_exists($key, $normalized)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function serverBenchmarkUploadMessageLine(array $context): string
    {
        $method = isset($context['method']) && is_string($context['method']) && $context['method'] !== ''
            ? $context['method']
            : 'marker';
        $document = isset($context['document']) && is_string($context['document']) && $context['document'] !== ''
            ? $context['document']
            : 'uploaded PDF';

        return "Marker server benchmark upload completed for {$method}/{$document}";
    }

    private function optionalContextString(mixed $value, string $name): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_scalar($value)) {
            throw new InvalidArgumentException("Server benchmark context {$name} must be scalar when provided.");
        }

        return (string) $value;
    }

    private function optionalContextInt(mixed $value, string $name): ?int
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new InvalidArgumentException("Server benchmark context {$name} must be an integer when provided.");
    }

    private function optionalContextFloat(mixed $value, string $name): ?float
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        throw new InvalidArgumentException("Server benchmark context {$name} must be numeric when provided.");
    }

    private function artifactSize(string $path): int
    {
        clearstatcache(true, $path);

        $size = filesize($path);

        return $size === false ? 0 : $size;
    }

    private function artifactSha256(string $path): string
    {
        $hash = hash_file('sha256', $path);
        if (!is_string($hash)) {
            throw new RuntimeException('Unable to fingerprint markerPDF artifact: ' . $path);
        }

        return $hash;
    }

    private function ensureArtifactDirectory(string $path, string $label): void
    {
        $directory = dirname($path);
        if ($directory === '' || $directory === '.') {
            return;
        }
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create markerPDF ' . $label . ' folder: ' . $directory);
        }
    }

    /**
     * @param array<string, mixed> $telemetry
     * @return array{path: string, filename: string, format: string, success_report_written: false, review_only: true, size: int, sha256: string}
     */
    private function writeBenchmarkErrorArtifactJson(string $errorArtifactFile, array $telemetry): array
    {
        $errorArtifactFile = trim($errorArtifactFile);
        if ($errorArtifactFile === '') {
            throw new InvalidArgumentException('Benchmark error artifact JSON file must not be empty.');
        }

        $artifact = [
            'path' => $errorArtifactFile,
            'filename' => basename($errorArtifactFile),
            'format' => 'json',
            'success_report_written' => false,
            'review_only' => true,
        ];
        $payload = [
            'schema' => 'markerpdf.benchmark_error.v1',
            'source' => 'sddai/markerPDF benchmarks/overall.py',
            'status' => 'error',
            'success' => false,
            'error' => $telemetry['error'] ?? null,
            'message_line' => $telemetry['message_line'] ?? null,
            'telemetry' => $telemetry,
            'artifact' => $artifact,
            'default_runner_fails_fast' => true,
            'success_report_written' => false,
            'executes_external_tools' => false,
            'executes_python_or_models' => false,
            'review_only' => true,
        ];

        try {
            $json = json_encode(
                $payload,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Unable to encode markerPDF benchmark error artifact as JSON.', previous: $exception);
        }

        if (@file_put_contents($errorArtifactFile, $json) === false) {
            throw new RuntimeException('Unable to write markerPDF benchmark error artifact: ' . $errorArtifactFile);
        }

        clearstatcache(true, $errorArtifactFile);
        $hash = hash_file('sha256', $errorArtifactFile);
        if (!is_string($hash)) {
            throw new RuntimeException('Unable to fingerprint markerPDF benchmark error artifact: ' . $errorArtifactFile);
        }

        return $artifact + [
            'size' => filesize($errorArtifactFile) ?: 0,
            'sha256' => $hash,
        ];
    }

    /**
     * @return list<string>
     */
    private function benchmarkFiles(string $inputFolder): array
    {
        $files = [];
        foreach (scandir($inputFolder) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || !str_ends_with($entry, '.pdf')) {
                continue;
            }

            $path = $inputFolder . DIRECTORY_SEPARATOR . $entry;
            if (is_file($path)) {
                $files[] = $entry;
            }
        }
        sort($files, SORT_STRING);

        return $files;
    }

    private function markdownFilename(string $pdfFilename): string
    {
        $withoutExtension = preg_replace('/\.[^.]*$/', '', $pdfFilename) ?? $pdfFilename;

        return $withoutExtension . '.md';
    }

    /**
     * @return array{text: string}
     */
    private function normalizeConversion(mixed $conversion): array
    {
        if (is_string($conversion)) {
            return ['text' => $conversion];
        }
        if (!is_array($conversion)) {
            throw new InvalidArgumentException('Benchmark converter must return text or a conversion array.');
        }

        $text = $conversion['text']
            ?? $conversion['full_text']
            ?? $conversion['markdown']
            ?? $conversion[0]
            ?? null;
        if (!is_string($text)) {
            throw new InvalidArgumentException('Benchmark converter result must contain text.');
        }

        return ['text' => $text];
    }

    /**
     * @param array<string, mixed> $runtimeOptions
     * @return array{
     *     methods: list<string>|null,
     *     marker_batch_multiplier: int,
     *     nougat_batch_size: int,
     *     profile_memory: bool,
     *     callback_sandbox: bool,
     *     memory_snapshot_errors: array<string, Throwable|string>
     * }
     */
    private function normalizeRuntimeOptions(array $runtimeOptions): array
    {
        $methods = null;
        if (array_key_exists('methods', $runtimeOptions)) {
            $methods = $this->methodList($runtimeOptions['methods']);
        } elseif ($this->boolOption($runtimeOptions['nougat'] ?? $runtimeOptions['include_nougat'] ?? false)) {
            $methods = ['marker', 'nougat'];
        }
        $profileMemory = $this->boolOption($runtimeOptions['profile_memory'] ?? $runtimeOptions['profileMemory'] ?? false);
        $memorySnapshotErrors = $this->memorySnapshotErrors(
            $runtimeOptions['memory_snapshot_errors'] ?? $runtimeOptions['memorySnapshotErrors'] ?? []
        );
        if ($memorySnapshotErrors !== [] && !$profileMemory) {
            throw new InvalidArgumentException('Benchmark memory snapshot errors require profile_memory.');
        }

        return [
            'methods' => $methods,
            'marker_batch_multiplier' => $this->positiveIntOption($runtimeOptions['marker_batch_multiplier'] ?? $runtimeOptions['markerBatchMultiplier'] ?? 1, 'marker_batch_multiplier'),
            'nougat_batch_size' => $this->positiveIntOption($runtimeOptions['nougat_batch_size'] ?? $runtimeOptions['nougatBatchSize'] ?? 1, 'nougat_batch_size'),
            'profile_memory' => $profileMemory,
            'callback_sandbox' => $this->boolOption($runtimeOptions['sandbox_callbacks'] ?? $runtimeOptions['sandboxCallbacks'] ?? true),
            'memory_snapshot_errors' => $memorySnapshotErrors,
        ];
    }

    /**
     * @return array<string, Throwable|string>
     */
    private function memorySnapshotErrors(mixed $value): array
    {
        if ($value === null || $value === []) {
            return [];
        }
        if (!is_array($value)) {
            throw new InvalidArgumentException('Benchmark memory snapshot errors must be keyed by snapshot filename.');
        }

        $errors = [];
        foreach ($value as $snapshot => $error) {
            if (!is_string($snapshot) || trim($snapshot) === '') {
                throw new InvalidArgumentException('Benchmark memory snapshot error keys must be non-empty snapshot filenames.');
            }
            if (!$error instanceof Throwable && !is_string($error)) {
                throw new InvalidArgumentException('Benchmark memory snapshot errors must be strings or Throwable instances.');
            }

            $errors[trim($snapshot)] = $error;
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function methodList(mixed $methods): array
    {
        if (!is_array($methods) || $methods === []) {
            throw new InvalidArgumentException('Benchmark runtime methods must be a non-empty list.');
        }

        $normalized = [];
        foreach ($methods as $method) {
            if (!is_string($method) || $method === '') {
                throw new InvalidArgumentException('Benchmark runtime methods must contain non-empty method names.');
            }
            if (in_array($method, $normalized, true)) {
                throw new InvalidArgumentException('Benchmark runtime methods must not contain duplicates.');
            }
            $normalized[] = $method;
        }

        return $normalized;
    }

    private function positiveIntOption(mixed $value, string $name): int
    {
        if (is_int($value)) {
            $number = $value;
        } elseif (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            $number = (int) $value;
        } else {
            throw new InvalidArgumentException("Benchmark runtime {$name} must be a positive integer.");
        }

        if ($number < 1) {
            throw new InvalidArgumentException("Benchmark runtime {$name} must be a positive integer.");
        }

        return $number;
    }

    private function boolOption(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value !== 0;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'off', ''], true)) {
                return false;
            }
        }

        return (bool) $value;
    }

    /**
     * @param array{marker_batch_multiplier: int, nougat_batch_size: int, profile_memory: bool, callback_sandbox: bool} $runtime
     * @return array<string, mixed>
     */
    private function conversionContext(array $runtime, string $method, string $document, int $documentIndex): array
    {
        $context = [
            'method' => $method,
            'document' => $document,
            'benchmark_index' => $documentIndex,
            'profile_memory' => $runtime['profile_memory'],
            'callback_sandbox' => $runtime['callback_sandbox'],
            'executes_external_tools' => false,
        ];

        if ($method === 'marker') {
            $context['batch_multiplier'] = $runtime['marker_batch_multiplier'];
            if ($runtime['profile_memory']) {
                $context['memory_snapshot'] = "marker_memory_{$documentIndex}.pickle";
            }
        }
        if ($method === 'nougat') {
            $context['batch_size'] = $runtime['nougat_batch_size'];
        }

        return $context;
    }

    /**
     * @return array{
     *     pdf_path: string,
     *     reference_path: string,
     *     markdown_output_folder: string|null,
     *     pdf: array<string, mixed>,
     *     reference: array<string, mixed>,
     *     markdown_output: array<string, array<string, mixed>>|null
     * }
     */
    private function sandboxSnapshot(string $pdfPath, string $referencePath, ?string $markdownOutputFolder): array
    {
        return [
            'pdf_path' => $pdfPath,
            'reference_path' => $referencePath,
            'markdown_output_folder' => $markdownOutputFolder,
            'pdf' => $this->fileFingerprint($pdfPath),
            'reference' => $this->fileFingerprint($referencePath),
            'markdown_output' => $markdownOutputFolder === null
                ? null
                : $this->directoryFingerprint($markdownOutputFolder),
        ];
    }

    /**
     * @param array{
     *     pdf_path: string,
     *     reference_path: string,
     *     markdown_output_folder: string|null,
     *     pdf: array<string, mixed>,
     *     reference: array<string, mixed>,
     *     markdown_output: array<string, array<string, mixed>>|null
     * } $before
     */
    private function assertSandboxUnchanged(array $before, string $label): void
    {
        $after = $this->sandboxSnapshot(
            $before['pdf_path'],
            $before['reference_path'],
            $before['markdown_output_folder']
        );

        $violations = [];
        if ($before['pdf'] !== $after['pdf']) {
            $violations[] = 'pdf';
        }
        if ($before['reference'] !== $after['reference']) {
            $violations[] = 'reference';
        }
        if ($before['markdown_output'] !== $after['markdown_output']) {
            $violations[] = 'markdown_output_folder';
        }

        if ($violations !== []) {
            throw new RuntimeException(
                'Benchmark callback sandbox violation for ' . $label . ': modified ' . implode(', ', $violations) . '.'
            );
        }
    }

    /**
     * @return array{exists: bool, size?: int, sha256?: string}
     */
    private function fileFingerprint(string $path): array
    {
        clearstatcache(true, $path);
        if (!is_file($path)) {
            return ['exists' => false];
        }

        $hash = hash_file('sha256', $path);
        if (!is_string($hash)) {
            throw new RuntimeException('Unable to fingerprint benchmark sandbox file: ' . $path);
        }

        return [
            'exists' => true,
            'size' => filesize($path) ?: 0,
            'sha256' => $hash,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function directoryFingerprint(string $path): array
    {
        clearstatcache(true, $path);
        if (!is_dir($path)) {
            return [];
        }

        $entries = [];
        $this->appendDirectoryFingerprints($entries, $path, $path);
        ksort($entries, SORT_STRING);

        return $entries;
    }

    /**
     * @param array<string, array<string, mixed>> $entries
     */
    private function appendDirectoryFingerprints(array &$entries, string $root, string $directory): void
    {
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root) + 1));
            clearstatcache(true, $path);
            if (is_dir($path)) {
                $entries[$relative] = ['type' => 'dir'];
                $this->appendDirectoryFingerprints($entries, $root, $path);
                continue;
            }
            if (is_file($path)) {
                $hash = hash_file('sha256', $path);
                if (!is_string($hash)) {
                    throw new RuntimeException('Unable to fingerprint benchmark sandbox output file: ' . $path);
                }
                $entries[$relative] = [
                    'type' => 'file',
                    'size' => filesize($path) ?: 0,
                    'sha256' => $hash,
                ];
            }
        }
    }
}
