<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;
use JsonException;

final class MarkerSettings
{
    private const DEFAULTS = [
        'TORCH_DEVICE' => null,
        'IMAGE_DPI' => 96,
        'EXTRACT_IMAGES' => true,
        'PAGINATE_OUTPUT' => false,
        'BASE_DIR' => '',
        'FLATTEN_PDF' => true,
        'DEFAULT_LANG' => 'English',
        'SUPPORTED_FILETYPES' => [
            'application/pdf' => 'pdf',
        ],
        'PDFTEXT_CPU_WORKERS' => 4,
        'DETECTOR_BATCH_SIZE' => null,
        'SURYA_DETECTOR_DPI' => 96,
        'DETECTOR_POSTPROCESSING_CPU_WORKERS' => 4,
        'INVALID_CHARS' => ["\u{FFFD}", "\u{FFFD}"],
        'OCR_ENGINE' => 'surya',
        'OCR_ALL_PAGES' => false,
        'SURYA_OCR_DPI' => 192,
        'RECOGNITION_BATCH_SIZE' => null,
        'OCR_PARALLEL_WORKERS' => 2,
        'TESSERACT_TIMEOUT' => 20,
        'TESSDATA_PREFIX' => '',
        'TEXIFY_MODEL_MAX' => 384,
        'TEXIFY_TOKEN_BUFFER' => 256,
        'TEXIFY_DPI' => 96,
        'TEXIFY_BATCH_SIZE' => null,
        'TEXIFY_MODEL_NAME' => 'vikp/texify',
        'SURYA_LAYOUT_DPI' => 96,
        'BAD_SPAN_TYPES' => ['Page-footer', 'Page-header', 'Picture'],
        'LAYOUT_MODEL_CHECKPOINT' => 'vikp/surya_layout3',
        'BBOX_INTERSECTION_THRESH' => 0.7,
        'TABLE_INTERSECTION_THRESH' => 0.7,
        'LAYOUT_BATCH_SIZE' => null,
        'DEFAULT_BLOCK_TYPE' => 'Text',
        'SURYA_ORDER_DPI' => 96,
        'ORDER_BATCH_SIZE' => null,
        'ORDER_MAX_BBOXES' => 255,
        'SURYA_TABLE_DPI' => 192,
        'TABLE_REC_BATCH_SIZE' => null,
        'HEADING_LEVEL_COUNT' => 4,
        'HEADING_MERGE_THRESHOLD' => 0.25,
        'HEADING_DEFAULT_LEVEL' => 2,
        'PAGE_SEPARATOR' => "------------------------------------------------\n\n",
        'DEBUG_DATA_FOLDER' => '',
        'DEBUG' => false,
        'FONT_DIR' => '',
        'DEBUG_RENDER_FONT' => '',
        'FONT_DL_BASE' => 'https://github.com/satbyy/go-noto-universal/releases/download/v7.0',
    ];

    private const OPTIONAL_STRING_KEYS = [
        'TORCH_DEVICE' => true,
        'OCR_ENGINE' => true,
    ];

    private const OPTIONAL_INT_KEYS = [
        'DETECTOR_BATCH_SIZE' => true,
        'RECOGNITION_BATCH_SIZE' => true,
        'TEXIFY_BATCH_SIZE' => true,
        'LAYOUT_BATCH_SIZE' => true,
        'ORDER_BATCH_SIZE' => true,
        'TABLE_REC_BATCH_SIZE' => true,
    ];

    /** @var array<string, mixed> */
    private array $values;

    /**
     * @param array<string, mixed> $overrides
     */
    public function __construct(array $overrides = [])
    {
        $this->values = self::DEFAULTS;
        $this->seedDynamicPathDefaults();

        foreach ($overrides as $key => $value) {
            if (!array_key_exists($key, self::DEFAULTS)) {
                continue;
            }
            $this->values[$key] = $this->coerceValue($key, $value);
        }

        $this->validate();
    }

    /**
     * Mirrors pydantic-settings' environment override boundary for the subset this lane needs.
     *
     * @param array<string, string|int|float|bool|null> $environment
     */
    public static function fromEnvironment(array $environment): self
    {
        return new self($environment);
    }

    /**
     * Reviews upstream settings.py computed runtime settings without importing torch.
     *
     * @param array<string, string|int|float|bool|null> $environment
     * @return array<string, mixed>
     */
    public function runtimeDevicePreflightPlan(array $environment = []): array
    {
        $settings = $environment === [] ? $this : self::fromEnvironment($environment);
        $torchDevice = $settings->values['TORCH_DEVICE'];
        $torchDeviceModel = $settings->torchDeviceModel();
        $providedEnvironmentKeys = array_map('strval', array_keys($environment));
        $knownEnvironmentKeys = array_values(array_filter(
            $providedEnvironmentKeys,
            static fn (string $key): bool => array_key_exists($key, self::DEFAULTS)
        ));
        $ignoredEnvironmentKeys = array_values(array_filter(
            $providedEnvironmentKeys,
            static fn (string $key): bool => !array_key_exists($key, self::DEFAULTS)
        ));

        return [
            'schema' => 'markerpdf.settings_runtime_device_preflight.v1',
            'source' => 'sddai/markerPDF marker/settings.py Settings computed TORCH_DEVICE_MODEL/CUDA/MODEL_DTYPE/TEXIFY_DTYPE',
            'settings' => [
                'TORCH_DEVICE' => $torchDevice,
                'TORCH_DEVICE_MODEL' => $torchDeviceModel,
                'CUDA' => $settings->cuda(),
                'MODEL_DTYPE' => $settings->modelDtype(),
                'TEXIFY_DTYPE' => $settings->texifyDtype(),
                'PDFTEXT_CPU_WORKERS' => $settings->values['PDFTEXT_CPU_WORKERS'],
                'OCR_ENGINE' => $settings->values['OCR_ENGINE'],
                'OCR_ALL_PAGES' => $settings->values['OCR_ALL_PAGES'],
                'IMAGE_DPI' => $settings->values['IMAGE_DPI'],
                'EXTRACT_IMAGES' => $settings->values['EXTRACT_IMAGES'],
                'PAGINATE_OUTPUT' => $settings->values['PAGINATE_OUTPUT'],
                'FLATTEN_PDF' => $settings->values['FLATTEN_PDF'],
            ],
            'environment_review' => [
                'provided_environment_keys' => $providedEnvironmentKeys,
                'known_environment_keys' => $knownEnvironmentKeys,
                'ignored_environment_keys' => $ignoredEnvironmentKeys,
                'extra_policy' => 'ignore',
                'upstream_env_file_lookup' => 'find_dotenv("local.env")',
                'upstream_env_file_name' => 'local.env',
                'native_reads_env_file' => false,
                'native_reads_process_environment' => false,
                'explicit_environment_argument_only' => true,
                'extra_unknown_settings_ignored' => true,
            ],
            'computed_fields' => [
                'explicit_torch_device_preserved' => $torchDevice !== null,
                'torch_device_model_source' => $torchDevice !== null
                    ? 'explicit_TORCH_DEVICE'
                    : 'native_cpu_fallback_without_torch_probe',
                'native_torch_backend_probe_executed' => false,
                'torch_cuda_is_available_probe_reached' => false,
                'torch_mps_is_available_probe_reached' => false,
                'cuda_membership_expression' => '"cuda" in self.TORCH_DEVICE_MODEL',
                'cuda_membership_is_case_sensitive' => true,
                'model_dtype_expression' => 'torch.bfloat16 if TORCH_DEVICE_MODEL == "cuda" else torch.float32',
                'texify_dtype_expression' => 'torch.float32 if TORCH_DEVICE_MODEL == "cpu" else torch.float16',
                'model_dtype_cuda_requires_exact_cuda' => true,
                'texify_dtype_cpu_requires_exact_cpu' => true,
            ],
            'runtime_consumers' => [
                'convert_py_environment' => [
                    'PYTORCH_ENABLE_MPS_FALLBACK' => '1',
                    'IN_STREAMLIT' => 'true',
                    'PDFTEXT_CPU_WORKERS' => '1',
                ],
                'convert_py_mps_branch_expression' => 'settings.TORCH_DEVICE == "mps" or settings.TORCH_DEVICE_MODEL == "mps"',
                'convert_py_mps_branch' => $torchDevice === 'mps' || $torchDeviceModel === 'mps',
                'convert_py_parent_share_memory_branch' => !($torchDevice === 'mps' || $torchDeviceModel === 'mps'),
            ],
            'review_only' => true,
            'executes_python_or_models' => false,
            'executes_torch_backend_probe' => false,
            'executes_cuda_probe' => false,
            'executes_mps_probe' => false,
            'executes_multiprocessing' => false,
            'executes_streamlit' => false,
            'executes_fastapi' => false,
            'executes_external_pdf_tools' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->values + [
            'TORCH_DEVICE_MODEL' => $this->torchDeviceModel(),
            'CUDA' => $this->cuda(),
            'MODEL_DTYPE' => $this->modelDtype(),
            'TEXIFY_DTYPE' => $this->texifyDtype(),
        ];
    }

    public function get(string $key): mixed
    {
        if ($key === 'TORCH_DEVICE_MODEL') {
            return $this->torchDeviceModel();
        }
        if ($key === 'CUDA') {
            return $this->cuda();
        }
        if ($key === 'MODEL_DTYPE') {
            return $this->modelDtype();
        }
        if ($key === 'TEXIFY_DTYPE') {
            return $this->texifyDtype();
        }
        if (!array_key_exists($key, $this->values)) {
            throw new InvalidArgumentException('Unknown markerPDF setting: ' . $key);
        }

        return $this->values[$key];
    }

    public function torchDeviceModel(): string
    {
        $device = $this->values['TORCH_DEVICE'];
        if ($device !== null) {
            return (string) $device;
        }

        // Native PHP does not probe torch backends; upstream falls through to CPU when none are available.
        return 'cpu';
    }

    public function cuda(): bool
    {
        return str_contains($this->torchDeviceModel(), 'cuda');
    }

    public function modelDtype(): string
    {
        return $this->torchDeviceModel() === 'cuda' ? 'bfloat16' : 'float32';
    }

    public function texifyDtype(): string
    {
        return $this->torchDeviceModel() === 'cpu' ? 'float32' : 'float16';
    }

    public function supportsFiletype(string $mimeType): bool
    {
        return array_key_exists($mimeType, $this->values['SUPPORTED_FILETYPES']);
    }

    public function extensionForFiletype(string $mimeType): ?string
    {
        return $this->values['SUPPORTED_FILETYPES'][$mimeType] ?? null;
    }

    public function extractImages(): bool
    {
        return (bool) $this->values['EXTRACT_IMAGES'];
    }

    public function paginateOutput(): bool
    {
        return (bool) $this->values['PAGINATE_OUTPUT'];
    }

    public function pageSeparator(): string
    {
        return (string) $this->values['PAGE_SEPARATOR'];
    }

    /**
     * @return list<string>
     */
    public function badSpanTypes(): array
    {
        return array_values($this->values['BAD_SPAN_TYPES']);
    }

    private function coerceValue(string $key, mixed $value): mixed
    {
        $default = self::DEFAULTS[$key];

        if ($value === null || $value === '') {
            if ($default === null || isset(self::OPTIONAL_STRING_KEYS[$key]) || isset(self::OPTIONAL_INT_KEYS[$key])) {
                return null;
            }
        }

        if (is_bool($default)) {
            return $this->coerceBool($key, $value);
        }
        if (is_int($default) || isset(self::OPTIONAL_INT_KEYS[$key])) {
            return $this->coerceInt($key, $value);
        }
        if (is_float($default)) {
            return $this->coerceFloat($key, $value);
        }
        if (is_array($default)) {
            return $this->coerceArray($key, $value);
        }

        return (string) $value;
    }

    private function coerceBool(string $key, mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        throw new InvalidArgumentException("Invalid boolean markerPDF setting for {$key}.");
    }

    private function coerceInt(string $key, mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', trim($value)) === 1) {
            return (int) trim($value);
        }

        throw new InvalidArgumentException("Invalid integer markerPDF setting for {$key}.");
    }

    private function coerceFloat(string $key, mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (is_string($value) && is_numeric(trim($value))) {
            return (float) trim($value);
        }

        throw new InvalidArgumentException("Invalid float markerPDF setting for {$key}.");
    }

    /**
     * @return array<mixed>
     */
    private function coerceArray(string $key, mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return [];
        }

        if ($text[0] === '[' || $text[0] === '{') {
            try {
                $decoded = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new InvalidArgumentException("Invalid JSON markerPDF setting for {$key}.", previous: $exception);
            }
            if (!is_array($decoded)) {
                throw new InvalidArgumentException("JSON markerPDF setting for {$key} must decode to an array.");
            }

            return $decoded;
        }

        return array_values(array_filter(
            array_map(static fn (string $item): string => trim($item), explode(',', $text)),
            static fn (string $item): bool => $item !== ''
        ));
    }

    private function validate(): void
    {
        $ocrEngine = $this->values['OCR_ENGINE'];
        if ($ocrEngine !== null && !in_array($ocrEngine, ['surya', 'ocrmypdf'], true)) {
            throw new InvalidArgumentException('OCR_ENGINE must be either surya or ocrmypdf.');
        }

        if (!is_array($this->values['SUPPORTED_FILETYPES'])) {
            throw new InvalidArgumentException('SUPPORTED_FILETYPES must be a map.');
        }
    }

    private function seedDynamicPathDefaults(): void
    {
        $baseDir = dirname(__DIR__);
        $fontDir = $baseDir . DIRECTORY_SEPARATOR . 'static' . DIRECTORY_SEPARATOR . 'fonts';

        $this->values['BASE_DIR'] = $baseDir;
        $this->values['DEBUG_DATA_FOLDER'] = $baseDir . DIRECTORY_SEPARATOR . 'debug_data';
        $this->values['FONT_DIR'] = $fontDir;
        $this->values['DEBUG_RENDER_FONT'] = $fontDir . DIRECTORY_SEPARATOR . 'GoNotoCurrent-Regular.ttf';
    }
}
