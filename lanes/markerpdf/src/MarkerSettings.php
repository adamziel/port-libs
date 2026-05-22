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
