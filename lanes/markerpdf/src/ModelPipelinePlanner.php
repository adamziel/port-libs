<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class ModelPipelinePlanner
{
    private MarkerSettings $settings;

    public function __construct(?MarkerSettings $settings = null)
    {
        $this->settings = $settings ?? new MarkerSettings();
    }

    /**
     * Native boundary for marker/models.py's module import side effect.
     *
     * @return array{PYTORCH_ENABLE_MPS_FALLBACK: string}
     */
    public function mpsFallbackEnvironment(): array
    {
        return ['PYTORCH_ENABLE_MPS_FALLBACK' => '1'];
    }

    /**
     * Native planning boundary for marker.models::load_all_models.
     *
     * The upstream function loads models in one order, then returns the
     * model_lst tuple in the order consumed by marker.convert::convert_single_pdf.
     * This method records both orders without importing Python model stacks.
     *
     * @return array{
     *     environment: array{PYTORCH_ENABLE_MPS_FALLBACK: string},
     *     load_sequence: list<string>,
     *     model_list_order: list<string>,
     *     models: array<string, array<string, mixed>>
     * }
     */
    public function loadAllModelsPlan(?string $device = null, ?string $dtype = null): array
    {
        if ($device !== null && $dtype === null) {
            throw new InvalidArgumentException('Must provide dtype if device is provided.');
        }

        return [
            'environment' => $this->mpsFallbackEnvironment(),
            'load_sequence' => ['detection', 'layout', 'order', 'ocr', 'texify', 'table_recognition'],
            'model_list_order' => ['texify', 'layout', 'order', 'detection', 'ocr', 'table_recognition'],
            'models' => [
                'detection' => $this->setupDetectionModel($device, $dtype),
                'layout' => $this->setupLayoutModel($device, $dtype),
                'order' => $this->setupOrderModel($device, $dtype),
                'ocr' => $this->setupRecognitionModel($device, $dtype),
                'texify' => $this->setupTexifyModel($device, $dtype),
                'table_recognition' => $this->setupTableRecognitionModel($device, $dtype),
            ],
        ];
    }

    /**
     * Native boundary for marker.models::setup_table_rec_model.
     *
     * @return array<string, mixed>
     */
    public function setupTableRecognitionModel(?string $device = null, ?string $dtype = null): array
    {
        return $this->modelPlan(
            'table_recognition',
            'setup_table_rec_model',
            'surya.model.table_rec.model.load_model',
            'surya.model.table_rec.processor.load_processor',
            [],
            [],
            $device,
            $dtype
        );
    }

    /**
     * Native boundary for marker.models::setup_recognition_model.
     *
     * @return array<string, mixed>
     */
    public function setupRecognitionModel(?string $device = null, ?string $dtype = null): array
    {
        return $this->modelPlan(
            'ocr',
            'setup_recognition_model',
            'surya.model.recognition.model.load_model',
            'surya.model.recognition.processor.load_processor',
            [],
            [],
            $device,
            $dtype
        );
    }

    /**
     * Native boundary for marker.models::setup_detection_model.
     *
     * @return array<string, mixed>
     */
    public function setupDetectionModel(?string $device = null, ?string $dtype = null): array
    {
        return $this->modelPlan(
            'detection',
            'setup_detection_model',
            'surya.model.detection.model.load_model',
            'surya.model.detection.processor.load_processor',
            [],
            [],
            $device,
            $dtype
        );
    }

    /**
     * Native boundary for marker.models::setup_texify_model.
     *
     * @return array<string, mixed>
     */
    public function setupTexifyModel(?string $device = null, ?string $dtype = null): array
    {
        $modelArguments = [
            'checkpoint' => $this->settings->get('TEXIFY_MODEL_NAME'),
            'device' => $device ?? $this->settings->torchDeviceModel(),
            'dtype' => $device !== null ? $dtype : $this->settings->texifyDtype(),
        ];

        return $this->modelPlan(
            'texify',
            'setup_texify_model',
            'texify.model.model.load_model',
            'texify.model.processor.load_processor',
            $modelArguments,
            [],
            null,
            null
        );
    }

    /**
     * Native boundary for marker.models::setup_layout_model.
     *
     * @return array<string, mixed>
     */
    public function setupLayoutModel(?string $device = null, ?string $dtype = null): array
    {
        $checkpoint = $this->settings->get('LAYOUT_MODEL_CHECKPOINT');

        return $this->modelPlan(
            'layout',
            'setup_layout_model',
            'surya.model.detection.model.load_model',
            'surya.model.detection.processor.load_processor',
            ['checkpoint' => $checkpoint],
            ['checkpoint' => $checkpoint],
            $device,
            $dtype
        );
    }

    /**
     * Native boundary for marker.models::setup_order_model.
     *
     * @return array<string, mixed>
     */
    public function setupOrderModel(?string $device = null, ?string $dtype = null): array
    {
        return $this->modelPlan(
            'order',
            'setup_order_model',
            'surya.model.ordering.model.load_model',
            'surya.model.ordering.processor.load_processor',
            [],
            [],
            $device,
            $dtype
        );
    }

    /**
     * Native boundary for marker.utils::flush_cuda_memory.
     *
     * @return array{torch_device_model: string, calls_empty_cache: bool, upstream_call: string|null}
     */
    public function flushCudaMemoryPlan(): array
    {
        $device = $this->settings->torchDeviceModel();

        return [
            'torch_device_model' => $device,
            'calls_empty_cache' => $device === 'cuda',
            'upstream_call' => $device === 'cuda' ? 'torch.cuda.empty_cache' : null,
        ];
    }

    /**
     * @param array<string, mixed> $modelArguments
     * @param array<string, mixed> $processorArguments
     * @return array<string, mixed>
     */
    private function modelPlan(
        string $name,
        string $upstreamFunction,
        string $modelLoader,
        string $processorLoader,
        array $modelArguments,
        array $processorArguments,
        ?string $device,
        ?string $dtype
    ): array {
        if ($device !== null) {
            $modelArguments['device'] = $device;
            $modelArguments['dtype'] = $dtype;
        }

        return [
            'name' => $name,
            'upstream_function' => $upstreamFunction,
            'model_loader' => $modelLoader,
            'model_arguments' => $modelArguments,
            'processor_loader' => $processorLoader,
            'processor_arguments' => $processorArguments,
            'processor_attached' => true,
        ];
    }
}
