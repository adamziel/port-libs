<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use InvalidArgumentException;

final class PdfImageRenderer
{
    private BboxGeometry $geometry;

    public function __construct(?BboxGeometry $geometry = null)
    {
        $this->geometry = $geometry ?? new BboxGeometry();
    }

    /**
     * Native boundary for marker.pdf.images::render_image scale calculation.
     */
    public function renderScale(float $dpi): float
    {
        if ($dpi <= 0.0) {
            throw new InvalidArgumentException('DPI must be greater than zero.');
        }

        return $dpi / 72.0;
    }

    /**
     * Derives rendered page pixel dimensions from PDF visible-box geometry.
     * Rotation follows page dictionary geometry, and UserUnit compensates for
     * the PDF 1.6 scale factor that PDFium cannot expose through pypdfium.
     *
     * @param list<float|int> $pageBbox
     * @return array{width: int, height: int}
     */
    public function renderedImageSize(array $pageBbox, float $dpi, int $rotation = 0, float $userUnit = 1.0): array
    {
        $bbox = $this->bbox($pageBbox);
        if ($userUnit <= 0.0) {
            throw new InvalidArgumentException('UserUnit must be greater than zero.');
        }

        $scale = $this->renderScale($dpi) * $userUnit;
        $width = max(0.0, $bbox[2] - $bbox[0]);
        $height = max(0.0, $bbox[3] - $bbox[1]);
        if (in_array($this->normalizedRotation($rotation), [90, 270], true)) {
            [$width, $height] = [$height, $width];
        }

        return [
            'width' => (int) round($width * $scale),
            'height' => (int) round($height * $scale),
        ];
    }

    /**
     * Native boundary for marker.pdf.images::render_bbox_image crop scaling.
     *
     * @param list<float|int> $pageBbox
     * @param array{width?: int|float, height?: int|float}|list<int|float> $renderedImageSize
     * @param list<float|int> $bbox
     * @return list<float>
     */
    public function cropBboxForRenderedImage(array $pageBbox, array $renderedImageSize, array $bbox): array
    {
        $pageBbox = $this->bbox($pageBbox);
        $imageBbox = $this->renderedImageBbox($renderedImageSize);

        return $this->geometry->rescaleBbox($pageBbox, $imageBbox, $bbox);
    }

    /**
     * Plans the metadata around marker.pdf.images::render_bbox_image without
     * rasterizing through pypdfium2/PIL.
     *
     * @param list<float|int> $pageBbox
     * @param list<float|int> $bbox
     * @param array{width?: int|float, height?: int|float}|list<int|float>|null $renderedImageSize
     * @return array{dpi: float, scale: float, draw_annots: false, color_mode: string, rendered_image_bbox: list<float>, crop_bbox: list<float>, crop_width: float, crop_height: float}
     */
    public function renderBboxImagePlan(
        array $pageBbox,
        array $bbox,
        float $dpi = 96.0,
        ?array $renderedImageSize = null
    ): array {
        $size = $renderedImageSize ?? $this->renderedImageSize($pageBbox, $dpi);
        $imageBbox = $this->renderedImageBbox($size);
        $cropBbox = $this->cropBboxForRenderedImage($pageBbox, $size, $bbox);

        return [
            'dpi' => $dpi,
            'scale' => $this->renderScale($dpi),
            'draw_annots' => false,
            'color_mode' => 'RGB',
            'rendered_image_bbox' => $imageBbox,
            'crop_bbox' => $cropBbox,
            'crop_width' => max(0.0, $cropBbox[2] - $cropBbox[0]),
            'crop_height' => max(0.0, $cropBbox[3] - $cropBbox[1]),
        ];
    }

    /**
     * Native metadata boundary for PDF DCTDecode image color handling.
     *
     * Upstream renders through pypdfium/PIL and converts the final image to
     * RGB. This keeps the same RGB preview target while making CMYK/YCCK DCT
     * decisions deterministic without rasterizing the whole JPEG stream.
     *
     * @return array{filter: string, source_color_space: string, components: int|null, bits_per_component: int, adobe_app14_transform: int|null, decode_parms_color_transform: int|null, effective_color_transform: int|null, adobe_marker_overrides_decode_parms: bool, needs_cmyk_to_rgb: bool, uses_ycck_transform: bool, output_color_mode: string, notes: list<string>}
     */
    public function dctDecodeImageColorPlan(string $imageDictionary, string $jpegBytes): array
    {
        $colorSpace = $this->imageColorSpace($imageDictionary) ?? 'DeviceRGB';
        $components = $this->jpegComponentCount($jpegBytes) ?? $this->componentCountForColorSpace($colorSpace);
        $adobeTransform = $this->jpegAdobeApp14Transform($jpegBytes);
        $decodeParmsTransform = $this->dctDecodeParmsColorTransform($imageDictionary);
        $effectiveTransform = $adobeTransform ?? $decodeParmsTransform ?? ($components === 3 ? 1 : 0);
        $needsCmykToRgb = $colorSpace === 'DeviceCMYK' || $components === 4;
        $notes = [];

        if ($adobeTransform !== null && $decodeParmsTransform !== null) {
            $notes[] = 'adobe_app14_transform_overrides_decodeparms';
        }
        if ($needsCmykToRgb) {
            $notes[] = 'render_rgb_preview_from_cmyk';
        }
        if ($needsCmykToRgb && $effectiveTransform !== null && $effectiveTransform !== 0) {
            $notes[] = 'apply_ycck_to_cmyk_before_rgb';
        }

        return [
            'filter' => $this->imageFilterName($imageDictionary) ?? 'DCTDecode',
            'source_color_space' => $colorSpace,
            'components' => $components,
            'bits_per_component' => $this->imageBitsPerComponent($imageDictionary) ?? 8,
            'adobe_app14_transform' => $adobeTransform,
            'decode_parms_color_transform' => $decodeParmsTransform,
            'effective_color_transform' => $effectiveTransform,
            'adobe_marker_overrides_decode_parms' => $adobeTransform !== null && $decodeParmsTransform !== null,
            'needs_cmyk_to_rgb' => $needsCmykToRgb,
            'uses_ycck_transform' => $needsCmykToRgb && $effectiveTransform !== null && $effectiveTransform !== 0,
            'output_color_mode' => 'RGB',
            'notes' => $notes,
        ];
    }

    /**
     * Converts one decoded DCT sample into the RGB preview space used by Marker
     * image extraction. For `uses_ycck_transform`, the four components are
     * expected as Y, Cb, Cr, K and are first converted to CMYK.
     *
     * @param list<int|float> $sample
     * @param array{uses_ycck_transform?: bool, adobe_app14_transform?: int|null} $plan
     * @return array{red: int, green: int, blue: int}
     */
    public function dctDecodeSampleToRgb(array $sample, array $plan = []): array
    {
        $values = array_map(fn (int|float $value): int => $this->byteValue($value), array_values($sample));
        if (count($values) !== 4) {
            throw new InvalidArgumentException('DCT CMYK sample must contain exactly 4 components.');
        }

        if (($plan['uses_ycck_transform'] ?? false) === true) {
            $values = $this->ycckToCmyk($values);
        } elseif (($plan['adobe_app14_transform'] ?? null) === 0) {
            $values = array_map(static fn (int $value): int => 255 - $value, $values);
        }

        return $this->cmykToRgb($values);
    }

    /**
     * Maps decoded image samples through a PDF image /Decode array before the
     * sample is interpreted in its color space.
     *
     * @param list<int|float> $sample
     * @param array{ranges?: list<array{min: float, max: float}>, valid_for_components?: bool} $decodePlan
     * @return list<float>
     */
    public function imageSampleDecodeValues(array $sample, array $decodePlan, int $bitsPerComponent = 8): array
    {
        if (($decodePlan['valid_for_components'] ?? false) !== true || !isset($decodePlan['ranges']) || !is_array($decodePlan['ranges'])) {
            throw new InvalidArgumentException('Image Decode array does not match the image component count.');
        }
        if ($bitsPerComponent <= 0) {
            throw new InvalidArgumentException('BitsPerComponent must be greater than zero.');
        }

        $values = array_values($sample);
        $ranges = array_values($decodePlan['ranges']);
        if (count($values) !== count($ranges)) {
            throw new InvalidArgumentException('Image sample component count does not match Decode ranges.');
        }

        $maxSample = (2 ** min($bitsPerComponent, 30)) - 1;
        $decoded = [];
        foreach ($values as $index => $value) {
            if (!is_int($value) && !is_float($value)) {
                throw new InvalidArgumentException('Image sample values must be numeric.');
            }

            $range = $ranges[$index];
            $min = (float) ($range['min'] ?? 0.0);
            $max = (float) ($range['max'] ?? 1.0);
            $ratio = max(0.0, min(1.0, (float) $value / $maxSample));
            $decoded[] = $min + (($max - $min) * $ratio);
        }

        return $decoded;
    }

    /**
     * Maps a one-bit /ImageMask sample through the stencil Decode array into
     * the opacity that a future RGB preview compositor should apply.
     *
     * @param array{present?: bool, decode?: array{ranges?: list<array{min: float, max: float}>, valid_for_components?: bool}, bits_per_component?: int|null} $imageMaskPlan
     */
    public function imageMaskSampleOpacity(int|float $sample, array $imageMaskPlan): float
    {
        if (($imageMaskPlan['present'] ?? false) !== true || !isset($imageMaskPlan['decode']) || !is_array($imageMaskPlan['decode'])) {
            throw new InvalidArgumentException('ImageMask plan must describe a present stencil mask.');
        }

        $decoded = $this->imageSampleDecodeValues(
            [$sample],
            $imageMaskPlan['decode'],
            max(1, (int) ($imageMaskPlan['bits_per_component'] ?? 1))
        );

        return max(0.0, min(1.0, $decoded[0]));
    }

    /**
     * Maps a soft-mask sample through the mask image /Decode array into the
     * alpha value that the RGB preview compositor should apply.
     *
     * @param array{present?: bool, decode?: array{ranges?: list<array{min: float, max: float}>, valid_for_components?: bool}, bits_per_component?: int|null} $softMaskPlan
     */
    public function softMaskSampleOpacity(int|float $sample, array $softMaskPlan): float
    {
        if (($softMaskPlan['present'] ?? false) !== true || !isset($softMaskPlan['decode']) || !is_array($softMaskPlan['decode'])) {
            throw new InvalidArgumentException('Soft mask plan must describe a present image mask.');
        }

        $decoded = $this->imageSampleDecodeValues(
            [$sample],
            $softMaskPlan['decode'],
            max(1, (int) ($softMaskPlan['bits_per_component'] ?? 8))
        );

        return max(0.0, min(1.0, $decoded[0]));
    }

    /**
     * Native metadata boundary for PDF image ColorSpace and soft-mask handling.
     *
     * Upstream rasterizes through pypdfium/PIL and always returns an RGB image.
     * This does not rasterize pixels, but records the ICC profile, soft mask,
     * and matte decisions a review UI needs before handing the crop to a future
     * raster backend.
     *
     * @param array<int, string> $objects
     * @return array{
     *     image_filters: list<string>,
     *     image_filter_details: list<array{filter: string, preview_only: bool, decode_parms: array<string, int|bool|string|null>|null}>,
     *     image_filter_boundary: array{preview_only_filters: list<string>, jbig2_globals_present: bool, native_raster_decode: bool},
     *     source_color_space: string,
     *     components: int|null,
     *     bits_per_component: int,
     *     uses_icc_profile: bool,
     *     icc_profile: array{components: int|null, alternate_color_space: string|null, range: list<float>, length: int|null}|null,
     *     uses_alternate_color_space: bool,
     *     alternate_color_space: array{family: string, colorant_names: list<string>, alternate_color_space: string|null, alternate_components: int|null, alternate_uses_icc_profile: bool, tint_transform_source: string|null, tint_transform_object: int|null, tint_transform_function_type: int|null, attributes_present: bool}|null,
     *     uses_indexed_color_space: bool,
     *     indexed_color_space: array{base_color_space: string|null, base_components: int|null, base_uses_icc_profile: bool, base_icc_profile: array{components: int|null, alternate_color_space: string|null, range: list<float>, length: int|null}|null, high_value: int|null, lookup_source: string|null, lookup_length: int|null, expected_lookup_length: int|null, lookup_length_matches: bool, lookup_entry_count: int|null, lookup_preview_hex: string, lookup_bytes: list<int>}|null,
     *     soft_mask: array{present: bool, subtype: string|null, width: int|null, height: int|null, color_space: string|null, components: int|null, bits_per_component: int|null, decode: array{ranges: list<array{min: float, max: float}>, component_count: int, expected_components: int|null, valid_for_components: bool, identity: bool, inverted_components: list<int>, source: string}|null, opacity_for_zero: float|null, opacity_for_max: float|null, decode_inverted: bool, decode_component_mismatch: bool, matte: list<float>|null, interpolate: bool|null}|null,
     *     image_decode: array{ranges: list<array{min: float, max: float}>, component_count: int, expected_components: int|null, valid_for_components: bool, identity: bool, inverted_components: list<int>, source: string}|null,
     *     image_decode_applied_before_rgb: bool,
     *     image_decode_component_mismatch: bool,
     *     image_mask: array{present: bool, width: int|null, height: int|null, bits_per_component: int, decode: array{ranges: list<array{min: float, max: float}>, component_count: int, expected_components: int|null, valid_for_components: bool, identity: bool, inverted_components: list<int>, source: string}, opacity_for_zero: float, opacity_for_one: float, inverted: bool}|null,
     *     image_mask_applied_before_rgb: bool,
     *     soft_mask_applied_before_rgb: bool,
     *     soft_mask_decode_applied_before_rgb: bool,
     *     soft_mask_decode_component_mismatch: bool,
     *     matte_unblending_required: bool,
     *     output_color_mode: string,
     *     alpha_output_mode: string,
     *     notes: list<string>
     * }
     */
    public function imageColorSpaceSoftMaskPlan(string $imageDictionary, array $objects = []): array
    {
        $colorSpace = $this->imageColorSpaceDetails($imageDictionary, $objects);
        $imageFilterDetails = $this->imageFilterDetails($imageDictionary, $objects);
        $imageFilters = array_map(
            static fn (array $filter): string => $filter['filter'],
            $imageFilterDetails
        );
        $previewOnlyFilters = array_values(array_filter(
            $imageFilters,
            fn (string $filter): bool => $this->isPreviewOnlyImageFilter($filter)
        ));
        $imageMask = $this->imageMaskDetails($imageDictionary, $objects);
        $imageMaskPresent = $imageMask !== null && $imageMask['present'] === true;
        $bitsPerComponent = $imageMaskPresent ? ($this->imageBitsPerComponent($imageDictionary) ?? 1) : ($this->imageBitsPerComponent($imageDictionary) ?? 8);
        $expectedDecodeComponents = $imageMaskPresent ? 1 : $colorSpace['components'];
        $imageDecode = $this->imageDecodeDetails($imageDictionary, $objects, $expectedDecodeComponents, $imageMaskPresent);
        if ($imageMaskPresent && $imageDecode !== null) {
            $imageMask = $this->imageMaskDetails($imageDictionary, $objects, $imageDecode);
        }
        $softMask = $this->imageSoftMaskDetails($imageDictionary, $objects);
        $softMaskPresent = $softMask !== null && $softMask['present'] === true;
        $matteUnblendingRequired = $softMaskPresent && $softMask['matte'] !== null;
        $imageDecodeValid = $imageDecode !== null && $imageDecode['valid_for_components'];
        $imageDecodeMismatch = $imageDecode !== null && !$imageDecode['valid_for_components'];
        $notes = [];

        if ($colorSpace['uses_indexed_color_space']) {
            $notes[] = 'indexed_color_space_palette_before_rgb_conversion';
            if (($colorSpace['indexed_color_space']['base_uses_icc_profile'] ?? false) === true) {
                $notes[] = 'indexed_base_icc_profile_color_space';
            }
            if (($colorSpace['indexed_color_space']['lookup_length_matches'] ?? true) === false) {
                $notes[] = 'indexed_lookup_length_mismatch';
            }
        }
        if (($colorSpace['uses_alternate_color_space'] ?? false) === true && is_array($colorSpace['alternate_color_space'] ?? null)) {
            $family = strtolower((string) $colorSpace['alternate_color_space']['family']);
            $notes[] = $family . '_tint_transform_review_before_rgb_conversion';
            if (($colorSpace['alternate_color_space']['alternate_uses_icc_profile'] ?? false) === true) {
                $notes[] = 'alternate_icc_profile_color_space';
            }
        }
        if ($colorSpace['uses_icc_profile']) {
            $notes[] = 'icc_profile_color_space';
        }
        if ($imageDecodeValid) {
            $notes[] = 'image_decode_applied_before_rgb_conversion';
            if ($imageDecode['inverted_components'] !== []) {
                $notes[] = 'image_decode_inverts_components_before_rgb';
            }
        } elseif ($imageDecodeMismatch) {
            $notes[] = 'image_decode_component_mismatch';
        }
        foreach ($previewOnlyFilters as $filter) {
            $notes[] = match ($filter) {
                'JBIG2Decode' => 'jbig2_image_filter_review_only',
                'JPXDecode' => 'jpx_image_filter_review_only',
                'CCITTFaxDecode', 'CCF' => 'ccitt_fax_image_filter_review_only',
                default => 'image_filter_review_only',
            };
        }
        if ($imageMaskPresent) {
            $notes[] = 'image_mask_stencil_applied_before_rgb_conversion';
            if (($imageMask['inverted'] ?? false) === true) {
                $notes[] = 'image_mask_decode_inverts_stencil';
            }
        }
        if ($softMaskPresent) {
            $notes[] = 'soft_mask_applied_before_rgb_conversion';
            if (($softMask['decode'] ?? null) !== null && ($softMask['decode']['valid_for_components'] ?? false) === true) {
                $notes[] = 'soft_mask_decode_applied_before_rgb_conversion';
                if (($softMask['decode_inverted'] ?? false) === true) {
                    $notes[] = 'soft_mask_decode_inverts_alpha';
                }
            } elseif (($softMask['decode_component_mismatch'] ?? false) === true) {
                $notes[] = 'soft_mask_decode_component_mismatch';
            }
        } elseif ($softMask !== null) {
            $notes[] = 'soft_mask_none';
        }
        if ($matteUnblendingRequired) {
            $notes[] = 'soft_mask_matte_unblend_before_rgb';
        }

        return [
            'image_filters' => $imageFilters,
            'image_filter_details' => $imageFilterDetails,
            'image_filter_boundary' => [
                'preview_only_filters' => $previewOnlyFilters,
                'jbig2_globals_present' => $this->jbig2GlobalsPresent($imageDictionary, $objects),
                'native_raster_decode' => $previewOnlyFilters === [],
            ],
            'source_color_space' => $imageMaskPresent ? 'ImageMask' : $colorSpace['source_color_space'],
            'components' => $imageMaskPresent ? 1 : $colorSpace['components'],
            'bits_per_component' => $bitsPerComponent,
            'uses_icc_profile' => $colorSpace['uses_icc_profile'],
            'icc_profile' => $colorSpace['icc_profile'],
            'uses_alternate_color_space' => $colorSpace['uses_alternate_color_space'],
            'alternate_color_space' => $colorSpace['alternate_color_space'],
            'uses_indexed_color_space' => $colorSpace['uses_indexed_color_space'],
            'indexed_color_space' => $colorSpace['indexed_color_space'],
            'image_decode' => $imageDecode,
            'image_decode_applied_before_rgb' => $imageDecodeValid,
            'image_decode_component_mismatch' => $imageDecodeMismatch,
            'image_mask' => $imageMask,
            'image_mask_applied_before_rgb' => $imageMaskPresent,
            'soft_mask' => $softMask,
            'soft_mask_applied_before_rgb' => $softMaskPresent,
            'soft_mask_decode_applied_before_rgb' => $softMaskPresent
                && ($softMask['decode'] ?? null) !== null
                && ($softMask['decode']['valid_for_components'] ?? false) === true,
            'soft_mask_decode_component_mismatch' => $softMaskPresent
                && (($softMask['decode_component_mismatch'] ?? false) === true),
            'matte_unblending_required' => $matteUnblendingRequired,
            'output_color_mode' => 'RGB',
            'alpha_output_mode' => $softMaskPresent
                ? 'soft_mask_composited_to_rgb_preview'
                : ($imageMaskPresent ? 'image_mask_composited_to_rgb_preview' : 'opaque_rgb_preview'),
            'notes' => $notes,
        ];
    }

    /**
     * Expands an Indexed color-space sample index into normalized base color
     * components. This mirrors the PDF parser side of the RGB preview boundary
     * without rasterizing pixels.
     *
     * @param array{base_components?: int|null, high_value?: int|null, lookup_length_matches?: bool, lookup_bytes?: list<int>} $indexedPlan
     * @return list<float>
     */
    public function indexedSampleToBaseComponents(int|float $sample, array $indexedPlan): array
    {
        $baseComponents = $indexedPlan['base_components'] ?? null;
        $highValue = $indexedPlan['high_value'] ?? null;
        $lookupBytes = $indexedPlan['lookup_bytes'] ?? null;

        if (!is_int($baseComponents) || $baseComponents <= 0 || !is_int($highValue) || !is_array($lookupBytes)) {
            throw new InvalidArgumentException('Indexed color-space plan must include base components, high value, and lookup bytes.');
        }
        if (($indexedPlan['lookup_length_matches'] ?? false) !== true) {
            throw new InvalidArgumentException('Indexed color-space lookup length does not match the declared high value and base components.');
        }

        $index = (int) round((float) $sample);
        if ($index < 0 || $index > $highValue) {
            throw new InvalidArgumentException('Indexed color-space sample is outside the declared high-value range.');
        }

        $offset = $index * $baseComponents;
        $components = [];
        for ($component = 0; $component < $baseComponents; $component++) {
            $byte = $lookupBytes[$offset + $component] ?? null;
            if (!is_int($byte)) {
                throw new InvalidArgumentException('Indexed color-space lookup table is incomplete.');
            }

            $components[] = max(0.0, min(1.0, $byte / 255));
        }

        return $components;
    }

    /**
     * @param array{width?: int|float, height?: int|float}|list<int|float> $renderedImageSize
     * @return list<float>
     */
    private function renderedImageBbox(array $renderedImageSize): array
    {
        $width = $renderedImageSize['width'] ?? $renderedImageSize[0] ?? null;
        $height = $renderedImageSize['height'] ?? $renderedImageSize[1] ?? null;

        if ((!is_int($width) && !is_float($width)) || (!is_int($height) && !is_float($height))) {
            throw new InvalidArgumentException('Rendered image size must include numeric width and height.');
        }
        if ($width <= 0 || $height <= 0) {
            throw new InvalidArgumentException('Rendered image width and height must be greater than zero.');
        }

        return [0.0, 0.0, (float) $width, (float) $height];
    }

    private function imageFilterName(string $dictionary): ?string
    {
        if (preg_match('/\/Filter\s+(?:\/([^\s\[\]()<>{}\/%]+)|\[\s*\/([^\s\[\]()<>{}\/%]+))/s', $dictionary, $match) !== 1) {
            return null;
        }

        return $this->decodePdfName($match[1] !== '' ? $match[1] : $match[2]);
    }

    /**
     * @param array<int, string> $objects
     * @return list<string>
     */
    private function imageFilterNames(string $dictionary, array $objects): array
    {
        $value = $this->extractPdfNameValue($dictionary, 'Filter');
        if ($value === null) {
            return [];
        }

        $resolved = trim($this->resolvePdfValue($value, $objects));
        if (str_starts_with($resolved, '[')) {
            $filters = [];
            foreach ($this->pdfArrayValues($resolved) as $entry) {
                $entry = trim($this->resolvePdfValue($entry, $objects));
                $name = $this->pdfNameValue($entry);
                if ($name !== null) {
                    $filters[] = $name;
                }
            }

            return $filters;
        }

        $name = $this->pdfNameValue($resolved);

        return $name === null ? [] : [$name];
    }

    /**
     * @param array<int, string> $objects
     * @return list<array{filter: string, preview_only: bool, decode_parms: array<string, int|bool|string|null>|null}>
     */
    private function imageFilterDetails(string $dictionary, array $objects): array
    {
        $filters = $this->imageFilterNames($dictionary, $objects);
        $decodeParms = $this->imageDecodeParmsValues($dictionary, $objects);
        $details = [];

        foreach ($filters as $index => $filter) {
            $decodeParmsValue = $decodeParms[$index] ?? (count($filters) === 1 ? ($decodeParms[0] ?? null) : null);
            $details[] = [
                'filter' => $filter,
                'preview_only' => $this->isPreviewOnlyImageFilter($filter),
                'decode_parms' => $this->imageFilterDecodeParms($filter, $decodeParmsValue, $objects),
            ];
        }

        return $details;
    }

    private function isPreviewOnlyImageFilter(string $filter): bool
    {
        return in_array($filter, ['JPXDecode', 'JBIG2Decode', 'CCITTFaxDecode', 'CCF'], true);
    }

    /**
     * @param array<int, string> $objects
     * @return list<string|null>
     */
    private function imageDecodeParmsValues(string $dictionary, array $objects): array
    {
        $value = $this->extractPdfNameValue($dictionary, 'DecodeParms');
        if ($value === null) {
            return [];
        }

        $resolved = trim($this->resolvePdfValue($value, $objects));
        if (str_starts_with($resolved, '[')) {
            return array_map(
                static fn (string $entry): ?string => trim($entry) === 'null' ? null : $entry,
                $this->pdfArrayValues($resolved)
            );
        }

        return [$value];
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, int|bool|string|null>|null
     */
    private function imageFilterDecodeParms(string $filter, ?string $value, array $objects): ?array
    {
        if ($value === null) {
            return null;
        }

        $resolved = trim($this->resolvePdfValue($value, $objects));
        if ($resolved === 'null' || !str_starts_with($resolved, '<<')) {
            return null;
        }

        if ($filter === 'CCITTFaxDecode' || $filter === 'CCF') {
            return [
                'type' => 'CCITTFaxDecode',
                'k' => $this->integerNameValue($resolved, 'K'),
                'columns' => $this->integerNameValue($resolved, 'Columns'),
                'rows' => $this->integerNameValue($resolved, 'Rows'),
                'black_is_1' => $this->booleanNameValue($resolved, 'BlackIs1'),
                'encoded_byte_align' => $this->booleanNameValue($resolved, 'EncodedByteAlign'),
                'end_of_line' => $this->booleanNameValue($resolved, 'EndOfLine'),
                'end_of_block' => $this->booleanNameValue($resolved, 'EndOfBlock'),
                'damaged_rows_before_error' => $this->integerNameValue($resolved, 'DamagedRowsBeforeError'),
            ];
        }

        if ($filter === 'JBIG2Decode') {
            return [
                'type' => 'JBIG2Decode',
                'jbig2_globals_present' => $this->pdfValueContainsName($resolved, 'JBIG2Globals', $objects),
            ];
        }

        return ['type' => $filter];
    }

    private function imageColorSpace(string $dictionary): ?string
    {
        if (preg_match('/\/ColorSpace\s+(?:\/([^\s\[\]()<>{}\/%]+)|\[\s*\/([^\s\[\]()<>{}\/%]+))/s', $dictionary, $match) !== 1) {
            return null;
        }

        return $this->decodePdfName($match[1] !== '' ? $match[1] : $match[2]);
    }

    private function imageBitsPerComponent(string $dictionary): ?int
    {
        if (preg_match('/\/(?:BitsPerComponent|BPC)\s+(\d+)/', $dictionary, $match) !== 1) {
            return null;
        }

        return max(1, (int) $match[1]);
    }

    /**
     * @param array<int, string> $objects
     * @return array{ranges: list<array{min: float, max: float}>, component_count: int, expected_components: int|null, valid_for_components: bool, identity: bool, inverted_components: list<int>, source: string}|null
     */
    private function imageDecodeDetails(string $dictionary, array $objects, ?int $expectedComponents, bool $defaultIfMissing = false): ?array
    {
        $value = $this->extractPdfNameValue($dictionary, 'Decode');
        if ($value === null) {
            if (!$defaultIfMissing) {
                return null;
            }

            return $this->buildImageDecodeDetails([0.0, 1.0], $expectedComponents, 'default');
        }

        $resolved = trim($this->resolvePdfValue($value, $objects));
        if (!str_starts_with($resolved, '[')) {
            return $this->buildImageDecodeDetails([], $expectedComponents, 'invalid');
        }

        return $this->buildImageDecodeDetails($this->numericArrayValue($resolved), $expectedComponents, 'explicit');
    }

    /**
     * @param list<float> $values
     * @return array{ranges: list<array{min: float, max: float}>, component_count: int, expected_components: int|null, valid_for_components: bool, identity: bool, inverted_components: list<int>, source: string}
     */
    private function buildImageDecodeDetails(array $values, ?int $expectedComponents, string $source): array
    {
        $ranges = [];
        $inverted = [];
        $pairCount = intdiv(count($values), 2);
        for ($index = 0; $index < $pairCount; $index++) {
            $min = (float) $values[$index * 2];
            $max = (float) $values[($index * 2) + 1];
            $ranges[] = ['min' => $min, 'max' => $max];
            if ($min > $max) {
                $inverted[] = $index;
            }
        }

        $validPairs = count($values) > 0 && count($values) % 2 === 0;
        $validComponents = $expectedComponents === null || $pairCount === $expectedComponents;
        $identity = $ranges !== [];
        foreach ($ranges as $range) {
            if (abs($range['min']) > 0.000001 || abs($range['max'] - 1.0) > 0.000001) {
                $identity = false;
                break;
            }
        }

        return [
            'ranges' => $ranges,
            'component_count' => $pairCount,
            'expected_components' => $expectedComponents,
            'valid_for_components' => $validPairs && $validComponents,
            'identity' => $identity,
            'inverted_components' => $inverted,
            'source' => $source,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @param array{ranges: list<array{min: float, max: float}>, component_count: int, expected_components: int|null, valid_for_components: bool, identity: bool, inverted_components: list<int>, source: string}|null $decodePlan
     * @return array{present: bool, width: int|null, height: int|null, bits_per_component: int, decode: array{ranges: list<array{min: float, max: float}>, component_count: int, expected_components: int|null, valid_for_components: bool, identity: bool, inverted_components: list<int>, source: string}, opacity_for_zero: float, opacity_for_one: float, inverted: bool}|null
     */
    private function imageMaskDetails(string $dictionary, array $objects, ?array $decodePlan = null): ?array
    {
        $imageMask = $this->booleanNameValue($dictionary, 'ImageMask');
        if ($imageMask !== true) {
            return null;
        }

        $bitsPerComponent = $this->imageBitsPerComponent($dictionary) ?? 1;
        $decode = $decodePlan ?? $this->imageDecodeDetails($dictionary, $objects, 1, true);
        if ($decode === null) {
            $decode = $this->buildImageDecodeDetails([0.0, 1.0], 1, 'default');
        }

        $opacityForZero = 0.0;
        $opacityForOne = 1.0;
        if ($decode['valid_for_components']) {
            $opacityForZero = $this->imageSampleDecodeValues([0], $decode, $bitsPerComponent)[0];
            $opacityForOne = $this->imageSampleDecodeValues([(2 ** min($bitsPerComponent, 30)) - 1], $decode, $bitsPerComponent)[0];
        }

        return [
            'present' => true,
            'width' => $this->integerNameValue($dictionary, 'Width'),
            'height' => $this->integerNameValue($dictionary, 'Height'),
            'bits_per_component' => $bitsPerComponent,
            'decode' => $decode,
            'opacity_for_zero' => max(0.0, min(1.0, $opacityForZero)),
            'opacity_for_one' => max(0.0, min(1.0, $opacityForOne)),
            'inverted' => $decode['valid_for_components'] && $decode['inverted_components'] !== [],
        ];
    }

    private function dctDecodeParmsColorTransform(string $dictionary): ?int
    {
        $decodeParms = null;
        if (preg_match('/\/DecodeParms\s*<<(.*?)>>/s', $dictionary, $match) === 1) {
            $decodeParms = $match[1];
        } elseif (preg_match('/\/DecodeParms\s*\[\s*<<(.*?)>>/s', $dictionary, $match) === 1) {
            $decodeParms = $match[1];
        }

        if ($decodeParms === null || preg_match('/\/ColorTransform\s+(-?\d+)/', $decodeParms, $colorTransform) !== 1) {
            return null;
        }

        return max(0, min(2, (int) $colorTransform[1]));
    }

    private function componentCountForColorSpace(string $colorSpace): ?int
    {
        return match ($colorSpace) {
            'DeviceGray', 'CalGray' => 1,
            'DeviceRGB', 'CalRGB', 'Lab' => 3,
            'DeviceCMYK' => 4,
            default => null,
        };
    }

    /**
     * @param array<int, string> $objects
     * @return array{source_color_space: string, components: int|null, uses_icc_profile: bool, icc_profile: array{components: int|null, alternate_color_space: string|null, range: list<float>, length: int|null}|null, uses_indexed_color_space: bool, indexed_color_space: array{base_color_space: string|null, base_components: int|null, base_uses_icc_profile: bool, base_icc_profile: array{components: int|null, alternate_color_space: string|null, range: list<float>, length: int|null}|null, high_value: int|null, lookup_source: string|null, lookup_length: int|null, expected_lookup_length: int|null, lookup_length_matches: bool, lookup_entry_count: int|null, lookup_preview_hex: string, lookup_bytes: list<int>}|null}
     */
    private function imageColorSpaceDetails(string $dictionary, array $objects): array
    {
        $value = $this->extractPdfNameValue($dictionary, 'ColorSpace')
            ?? $this->extractPdfNameValue($dictionary, 'CS');

        if ($value === null) {
            return [
                'source_color_space' => 'DeviceRGB',
                'components' => 3,
                'uses_icc_profile' => false,
                'icc_profile' => null,
                'uses_alternate_color_space' => false,
                'alternate_color_space' => null,
                'uses_indexed_color_space' => false,
                'indexed_color_space' => null,
            ];
        }

        return $this->colorSpaceDetailsFromValue($value, $objects);
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seenObjects
     * @return array{source_color_space: string, components: int|null, uses_icc_profile: bool, icc_profile: array{components: int|null, alternate_color_space: string|null, range: list<float>, length: int|null}|null, uses_indexed_color_space: bool, indexed_color_space: array{base_color_space: string|null, base_components: int|null, base_uses_icc_profile: bool, base_icc_profile: array{components: int|null, alternate_color_space: string|null, range: list<float>, length: int|null}|null, high_value: int|null, lookup_source: string|null, lookup_length: int|null, expected_lookup_length: int|null, lookup_length_matches: bool, lookup_entry_count: int|null, lookup_preview_hex: string, lookup_bytes: list<int>}|null}
     */
    private function colorSpaceDetailsFromValue(string $value, array $objects, array $seenObjects = []): array
    {
        $resolvedValue = $this->resolvePdfValueWithSeen($value, $objects, $seenObjects);
        $resolved = $resolvedValue['value'];
        $seenObjects = $resolvedValue['seen'];

        if (str_starts_with($resolved, '[')) {
            $values = $this->pdfArrayValues($resolved);
            $family = isset($values[0]) ? $this->pdfNameValue($values[0]) : null;
            $family = $family === null ? 'DeviceRGB' : $this->normalizeColorSpaceName($family);

            if ($family === 'ICCBased') {
                $profile = isset($values[1]) ? trim($this->resolvePdfValue($values[1], $objects, $seenObjects)) : '';
                $components = $this->integerNameValue($profile, 'N');
                $alternateValue = $this->extractPdfNameValue($profile, 'Alternate');
                $alternate = $alternateValue === null ? null : $this->colorSpaceNameFromValue($alternateValue, $objects, $seenObjects);
                $rangeValue = $this->extractPdfNameValue($profile, 'Range');

                return [
                    'source_color_space' => 'ICCBased',
                    'components' => $components ?? ($alternate === null ? null : $this->componentCountForColorSpace($alternate)),
                    'uses_icc_profile' => true,
                    'icc_profile' => [
                        'components' => $components,
                        'alternate_color_space' => $alternate,
                        'range' => $this->numericArrayValue($rangeValue),
                        'length' => $this->integerNameValue($profile, 'Length'),
                    ],
                    'uses_alternate_color_space' => false,
                    'alternate_color_space' => null,
                    'uses_indexed_color_space' => false,
                    'indexed_color_space' => null,
                ];
            }

            if ($family === 'Separation' || $family === 'DeviceN') {
                return $this->alternateColorSpaceDetails($family, $values, $objects, $seenObjects);
            }

            if ($family === 'Indexed') {
                return $this->indexedColorSpaceDetails($values, $objects, $seenObjects);
            }

            return [
                'source_color_space' => $family,
                'components' => $this->componentCountForColorSpace($family),
                'uses_icc_profile' => false,
                'icc_profile' => null,
                'uses_alternate_color_space' => false,
                'alternate_color_space' => null,
                'uses_indexed_color_space' => false,
                'indexed_color_space' => null,
            ];
        }

        $name = $this->pdfNameValue($resolved);
        $colorSpace = $name === null ? 'DeviceRGB' : $this->normalizeColorSpaceName($name);

        return [
            'source_color_space' => $colorSpace,
            'components' => $this->componentCountForColorSpace($colorSpace),
            'uses_icc_profile' => false,
            'icc_profile' => null,
            'uses_alternate_color_space' => false,
            'alternate_color_space' => null,
            'uses_indexed_color_space' => false,
            'indexed_color_space' => null,
        ];
    }

    /**
     * @param list<string> $values
     * @param array<int, string> $objects
     * @param array<int, true> $seenObjects
     * @return array{source_color_space: string, components: int, uses_icc_profile: bool, icc_profile: array{components: int|null, alternate_color_space: string|null, range: list<float>, length: int|null}|null, uses_alternate_color_space: bool, alternate_color_space: array{family: string, colorant_names: list<string>, alternate_color_space: string|null, alternate_components: int|null, alternate_uses_icc_profile: bool, tint_transform_source: string|null, tint_transform_object: int|null, tint_transform_function_type: int|null, attributes_present: bool}, uses_indexed_color_space: bool, indexed_color_space: null}
     */
    private function alternateColorSpaceDetails(string $family, array $values, array $objects, array $seenObjects): array
    {
        $colorantNames = isset($values[1]) ? $this->colorantNamesFromValue($values[1], $objects, $seenObjects) : [];
        $alternateIndex = 2;
        $tintTransformIndex = 3;
        $attributesIndex = $family === 'DeviceN' ? 4 : null;
        $alternate = isset($values[$alternateIndex])
            ? $this->colorSpaceDetailsFromValue($values[$alternateIndex], $objects, $seenObjects)
            : [
                'source_color_space' => null,
                'components' => null,
                'uses_icc_profile' => false,
                'icc_profile' => null,
                'uses_alternate_color_space' => false,
                'alternate_color_space' => null,
                'uses_indexed_color_space' => false,
                'indexed_color_space' => null,
            ];
        $tintTransform = $values[$tintTransformIndex] ?? null;
        $resolvedTintTransform = $tintTransform === null ? '' : trim($this->resolvePdfValue($tintTransform, $objects, $seenObjects));

        return [
            'source_color_space' => $family,
            'components' => $family === 'Separation' ? 1 : count($colorantNames),
            'uses_icc_profile' => (bool) $alternate['uses_icc_profile'],
            'icc_profile' => $alternate['icc_profile'],
            'uses_alternate_color_space' => true,
            'alternate_color_space' => [
                'family' => $family,
                'colorant_names' => $colorantNames,
                'alternate_color_space' => $alternate['source_color_space'],
                'alternate_components' => $alternate['components'],
                'alternate_uses_icc_profile' => (bool) $alternate['uses_icc_profile'],
                'tint_transform_source' => $this->pdfValueSource($tintTransform),
                'tint_transform_object' => $tintTransform === null ? null : $this->objectReferenceNumber($tintTransform),
                'tint_transform_function_type' => $resolvedTintTransform === '' ? null : $this->integerNameValue($resolvedTintTransform, 'FunctionType'),
                'attributes_present' => $attributesIndex !== null && isset($values[$attributesIndex]) && trim($values[$attributesIndex]) !== 'null',
            ],
            'uses_indexed_color_space' => false,
            'indexed_color_space' => null,
        ];
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seenObjects
     * @return list<string>
     */
    private function colorantNamesFromValue(string $value, array $objects, array $seenObjects): array
    {
        $resolved = trim($this->resolvePdfValue($value, $objects, $seenObjects));
        if (str_starts_with($resolved, '[')) {
            $names = [];
            foreach ($this->pdfArrayValues($resolved) as $entry) {
                $name = $this->pdfNameValue($this->resolvePdfValue($entry, $objects, $seenObjects));
                if ($name !== null) {
                    $names[] = $name;
                }
            }

            return $names;
        }

        $name = $this->pdfNameValue($resolved);

        return $name === null ? [] : [$name];
    }

    private function objectReferenceNumber(string $value): ?int
    {
        return preg_match('/^(\d+)\s+\d+\s+R$/', trim($value), $match) === 1 ? (int) $match[1] : null;
    }

    private function pdfValueSource(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($this->objectReferenceNumber($trimmed) !== null) {
            return 'object_ref';
        }
        if (str_starts_with($trimmed, '<<')) {
            return 'dictionary';
        }
        if (str_starts_with($trimmed, '[')) {
            return 'array';
        }

        return 'direct';
    }

    /**
     * @param list<string> $values
     * @param array<int, string> $objects
     * @param array<int, true> $seenObjects
     * @return array{source_color_space: string, components: int, uses_icc_profile: bool, icc_profile: array{components: int|null, alternate_color_space: string|null, range: list<float>, length: int|null}|null, uses_indexed_color_space: bool, indexed_color_space: array{base_color_space: string|null, base_components: int|null, base_uses_icc_profile: bool, base_icc_profile: array{components: int|null, alternate_color_space: string|null, range: list<float>, length: int|null}|null, high_value: int|null, lookup_source: string|null, lookup_length: int|null, expected_lookup_length: int|null, lookup_length_matches: bool, lookup_entry_count: int|null, lookup_preview_hex: string, lookup_bytes: list<int>}}
     */
    private function indexedColorSpaceDetails(array $values, array $objects, array $seenObjects): array
    {
        $base = isset($values[1])
            ? $this->colorSpaceDetailsFromValue($values[1], $objects, $seenObjects)
            : [
                'source_color_space' => null,
                'components' => null,
                'uses_icc_profile' => false,
                'icc_profile' => null,
                'uses_alternate_color_space' => false,
                'alternate_color_space' => null,
                'uses_indexed_color_space' => false,
                'indexed_color_space' => null,
            ];
        $highValue = isset($values[2]) ? $this->integerFromPdfValue($values[2], $objects, $seenObjects) : null;
        $lookup = isset($values[3]) ? $this->pdfBytesFromValue($values[3], $objects, $seenObjects) : null;
        $baseComponents = $base['components'];
        $lookupBytes = $lookup['bytes'] ?? '';
        $lookupLength = $lookup === null ? null : strlen($lookupBytes);
        $expectedLength = is_int($highValue) && is_int($baseComponents) && $baseComponents > 0
            ? ($highValue + 1) * $baseComponents
            : null;
        $lookupLengthMatches = $lookupLength !== null && $expectedLength !== null && $lookupLength === $expectedLength;

        return [
            'source_color_space' => 'Indexed',
            'components' => 1,
            'uses_icc_profile' => $base['uses_icc_profile'],
            'icc_profile' => $base['icc_profile'],
            'uses_alternate_color_space' => false,
            'alternate_color_space' => null,
            'uses_indexed_color_space' => true,
            'indexed_color_space' => [
                'base_color_space' => $base['source_color_space'],
                'base_components' => $baseComponents,
                'base_uses_icc_profile' => $base['uses_icc_profile'],
                'base_icc_profile' => $base['icc_profile'],
                'high_value' => $highValue,
                'lookup_source' => $lookup['source'] ?? null,
                'lookup_length' => $lookupLength,
                'expected_lookup_length' => $expectedLength,
                'lookup_length_matches' => $lookupLengthMatches,
                'lookup_entry_count' => $lookupLength !== null && is_int($baseComponents) && $baseComponents > 0
                    ? intdiv($lookupLength, $baseComponents)
                    : null,
                'lookup_preview_hex' => strtoupper(bin2hex(substr($lookupBytes, 0, 24))),
                'lookup_bytes' => $this->byteList($lookupBytes),
            ],
        ];
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seenObjects
     */
    private function colorSpaceNameFromValue(string $value, array $objects, array $seenObjects = []): ?string
    {
        $resolvedValue = $this->resolvePdfValueWithSeen($value, $objects, $seenObjects);
        $resolved = $resolvedValue['value'];
        $seenObjects = $resolvedValue['seen'];
        if (str_starts_with($resolved, '[')) {
            $values = $this->pdfArrayValues($resolved);
            $name = isset($values[0]) ? $this->pdfNameValue($values[0]) : null;

            return $name === null ? null : $this->normalizeColorSpaceName($name);
        }

        $name = $this->pdfNameValue($resolved);

        return $name === null ? null : $this->normalizeColorSpaceName($name);
    }

    private function normalizeColorSpaceName(string $name): string
    {
        return match ($name) {
            'G' => 'DeviceGray',
            'RGB' => 'DeviceRGB',
            'CMYK' => 'DeviceCMYK',
            'I' => 'Indexed',
            default => $name,
        };
    }

    /**
     * @param array<int, string> $objects
     * @return array{present: bool, subtype: string|null, width: int|null, height: int|null, color_space: string|null, components: int|null, bits_per_component: int|null, decode: array{ranges: list<array{min: float, max: float}>, component_count: int, expected_components: int|null, valid_for_components: bool, identity: bool, inverted_components: list<int>, source: string}|null, opacity_for_zero: float|null, opacity_for_max: float|null, decode_inverted: bool, decode_component_mismatch: bool, matte: list<float>|null, interpolate: bool|null}|null
     */
    private function imageSoftMaskDetails(string $dictionary, array $objects): ?array
    {
        $value = $this->extractPdfNameValue($dictionary, 'SMask');
        if ($value === null) {
            return null;
        }

        if ($this->pdfNameValue($value) === 'None') {
            return [
                'present' => false,
                'subtype' => null,
                'width' => null,
                'height' => null,
                'color_space' => null,
                'components' => null,
                'bits_per_component' => null,
                'decode' => null,
                'opacity_for_zero' => null,
                'opacity_for_max' => null,
                'decode_inverted' => false,
                'decode_component_mismatch' => false,
                'matte' => null,
                'interpolate' => null,
            ];
        }

        $maskDictionary = trim($this->resolvePdfValue($value, $objects));
        $colorSpace = $this->imageColorSpaceDetails($maskDictionary, $objects);
        $bitsPerComponent = $this->imageBitsPerComponent($maskDictionary);
        $decode = $this->imageDecodeDetails($maskDictionary, $objects, $colorSpace['components'], true);
        $matte = $this->numericArrayValue($this->extractPdfNameValue($maskDictionary, 'Matte'));
        $opacityForZero = null;
        $opacityForMax = null;
        if ($decode !== null && $decode['valid_for_components']) {
            $maskBits = max(1, $bitsPerComponent ?? 8);
            $opacityForZero = $this->imageSampleDecodeValues([0], $decode, $maskBits)[0];
            $opacityForMax = $this->imageSampleDecodeValues([(2 ** min($maskBits, 30)) - 1], $decode, $maskBits)[0];
        }

        return [
            'present' => true,
            'subtype' => $this->dictionaryNameValue($maskDictionary, 'Subtype'),
            'width' => $this->integerNameValue($maskDictionary, 'Width'),
            'height' => $this->integerNameValue($maskDictionary, 'Height'),
            'color_space' => $colorSpace['source_color_space'],
            'components' => $colorSpace['components'],
            'bits_per_component' => $bitsPerComponent,
            'decode' => $decode,
            'opacity_for_zero' => $opacityForZero === null ? null : max(0.0, min(1.0, $opacityForZero)),
            'opacity_for_max' => $opacityForMax === null ? null : max(0.0, min(1.0, $opacityForMax)),
            'decode_inverted' => $decode !== null && $decode['valid_for_components'] && $decode['inverted_components'] !== [],
            'decode_component_mismatch' => $decode !== null && !$decode['valid_for_components'],
            'matte' => $matte === [] ? null : $matte,
            'interpolate' => $this->booleanNameValue($maskDictionary, 'Interpolate'),
        ];
    }

    private function dictionaryNameValue(string $dictionary, string $name): ?string
    {
        $value = $this->extractPdfNameValue($dictionary, $name);

        return $value === null ? null : $this->pdfNameValue($value);
    }

    private function integerNameValue(string $dictionary, string $name): ?int
    {
        $value = $this->extractPdfNameValue($dictionary, $name);
        if ($value === null || preg_match('/^[+-]?\d+/', trim($value), $match) !== 1) {
            return null;
        }

        return (int) $match[0];
    }

    private function booleanNameValue(string $dictionary, string $name): ?bool
    {
        $value = $this->extractPdfNameValue($dictionary, $name);
        if ($value === null) {
            return null;
        }

        return match (trim($value)) {
            'true' => true,
            'false' => false,
            default => null,
        };
    }

    /**
     * @return list<float>
     */
    private function numericArrayValue(?string $value): array
    {
        if ($value === null || preg_match_all('/[+-]?(?:\d+(?:\.\d*)?|\.\d+)/', $value, $matches) === 0) {
            return [];
        }

        return array_map(static fn (string $number): float => (float) $number, $matches[0]);
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seenObjects
     */
    private function integerFromPdfValue(string $value, array $objects, array $seenObjects = []): ?int
    {
        $resolved = trim($this->resolvePdfValue($value, $objects, $seenObjects));
        if (preg_match('/^[+-]?\d+/', $resolved, $match) !== 1) {
            return null;
        }

        return (int) $match[0];
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seenObjects
     * @return array{bytes: string, source: string}|null
     */
    private function pdfBytesFromValue(string $value, array $objects, array $seenObjects = []): ?array
    {
        $resolved = trim($this->resolvePdfValue($value, $objects, $seenObjects));
        $stream = $this->streamPayloadBytes($resolved);
        if ($stream !== null) {
            return ['bytes' => $stream, 'source' => 'stream'];
        }

        if (preg_match('/^<([0-9A-Fa-f\s]*)>$/s', $resolved, $match) === 1) {
            $hex = preg_replace('/\s+/', '', $match[1]) ?? '';
            if ($hex === '') {
                return ['bytes' => '', 'source' => 'hex_string'];
            }
            if (strlen($hex) % 2 === 1) {
                $hex .= '0';
            }

            $bytes = hex2bin($hex);

            return ['bytes' => $bytes === false ? '' : $bytes, 'source' => 'hex_string'];
        }

        if (str_starts_with($resolved, '(')) {
            return ['bytes' => $this->literalStringBytes($resolved), 'source' => 'literal_string'];
        }

        return null;
    }

    private function streamPayloadBytes(string $resolved): ?string
    {
        if (preg_match('/stream(?:\r\n|\r|\n)(.*?)(?:\r\n|\r|\n)?endstream/s', $resolved, $match) === 1) {
            return $match[1];
        }
        if (preg_match('/stream(.*?)endstream/s', $resolved, $match) === 1) {
            return ltrim($match[1], "\r\n");
        }

        return null;
    }

    private function literalStringBytes(string $literal): string
    {
        if (!str_starts_with($literal, '(')) {
            return '';
        }

        $out = '';
        $length = strlen($literal);
        $depth = 0;
        for ($index = 0; $index < $length; $index++) {
            $char = $literal[$index];
            if ($char === '(') {
                if ($depth > 0) {
                    $out .= $char;
                }
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                if ($depth <= 0) {
                    break;
                }
                $out .= $char;
                continue;
            }
            if ($char !== '\\') {
                $out .= $char;
                continue;
            }

            $next = $literal[$index + 1] ?? '';
            if ($next === "\r" || $next === "\n") {
                $index++;
                if ($next === "\r" && ($literal[$index + 1] ?? '') === "\n") {
                    $index++;
                }
                continue;
            }
            if ($next !== '' && preg_match('/[0-7]/', $next) === 1) {
                $octal = $next;
                for ($extra = 0; $extra < 2; $extra++) {
                    $candidate = $literal[$index + 2 + $extra] ?? '';
                    if ($candidate === '' || preg_match('/[0-7]/', $candidate) !== 1) {
                        break;
                    }
                    $octal .= $candidate;
                }
                $out .= chr(octdec($octal) & 0xff);
                $index += strlen($octal);
                continue;
            }

            $out .= match ($next) {
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'b' => "\x08",
                'f' => "\x0c",
                '(', ')', '\\' => $next,
                default => $next,
            };
            $index++;
        }

        return $out;
    }

    /**
     * @return list<int>
     */
    private function byteList(string $bytes): array
    {
        if ($bytes === '') {
            return [];
        }

        return array_values(unpack('C*', $bytes));
    }

    /**
     * @param array<int, string> $objects
     */
    private function jbig2GlobalsPresent(string $dictionary, array $objects): bool
    {
        $value = $this->extractPdfNameValue($dictionary, 'DecodeParms');
        if ($value === null) {
            return false;
        }

        return $this->pdfValueContainsName($value, 'JBIG2Globals', $objects);
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seenObjects
     */
    private function pdfValueContainsName(string $value, string $name, array $objects, array $seenObjects = []): bool
    {
        $resolvedValue = $this->resolvePdfValueWithSeen($value, $objects, $seenObjects);
        $resolved = $resolvedValue['value'];
        $seenObjects = $resolvedValue['seen'];
        if (preg_match('/\/' . preg_quote($name, '/') . '\b/', $resolved) === 1) {
            return true;
        }
        if (!str_starts_with($resolved, '[')) {
            return false;
        }

        foreach ($this->pdfArrayValues($resolved) as $entry) {
            if ($this->pdfValueContainsName($entry, $name, $objects, $seenObjects)) {
                return true;
            }
        }

        return false;
    }

    private function extractPdfNameValue(string $dictionary, string $name): ?string
    {
        if (preg_match('/\/' . preg_quote($name, '/') . '\b/s', $dictionary, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        return $this->readPdfValueAt($dictionary, $match[0][1] + strlen($match[0][0]));
    }

    private function readPdfValueAt(string $source, int $offset): ?string
    {
        $read = $this->readPdfValueWithOffset($source, $offset);

        return $read['value'] ?? null;
    }

    /**
     * @return array{value: string, next: int}|null
     */
    private function readPdfValueWithOffset(string $source, int $offset): ?array
    {
        $offset = $this->skipPdfWhitespace($source, $offset);
        $length = strlen($source);
        if ($offset >= $length) {
            return null;
        }

        if (substr($source, $offset, 2) === '<<') {
            return $this->readBalancedDictionary($source, $offset);
        }
        if ($source[$offset] === '[') {
            return $this->readBalancedArray($source, $offset);
        }
        if ($source[$offset] === '/') {
            if (preg_match('/\G\/[^\s\[\]()<>{}\/%]+/s', $source, $match, 0, $offset) !== 1) {
                return null;
            }

            return ['value' => $match[0], 'next' => $offset + strlen($match[0])];
        }
        if ($source[$offset] === '(') {
            $next = $this->skipPdfLiteralString($source, $offset);

            return ['value' => substr($source, $offset, $next - $offset), 'next' => $next];
        }
        if ($source[$offset] === '<') {
            $next = strpos($source, '>', $offset + 1);
            if ($next === false) {
                return null;
            }

            return ['value' => substr($source, $offset, $next - $offset + 1), 'next' => $next + 1];
        }
        if (preg_match('/\G\d+\s+\d+\s+R\b/s', $source, $match, 0, $offset) === 1) {
            return ['value' => $match[0], 'next' => $offset + strlen($match[0])];
        }
        if (preg_match('/\G(?:true|false|null)\b/s', $source, $match, 0, $offset) === 1) {
            return ['value' => $match[0], 'next' => $offset + strlen($match[0])];
        }
        if (preg_match('/\G[+-]?(?:\d+(?:\.\d*)?|\.\d+)/s', $source, $match, 0, $offset) === 1) {
            return ['value' => $match[0], 'next' => $offset + strlen($match[0])];
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function pdfArrayValues(string $array): array
    {
        $body = trim($array);
        if (!str_starts_with($body, '[') || !str_ends_with($body, ']')) {
            return [];
        }

        $values = [];
        $offset = 1;
        $end = strlen($body) - 1;
        while ($offset < $end) {
            $read = $this->readPdfValueWithOffset($body, $offset);
            if ($read === null || $read['next'] <= $offset) {
                break;
            }

            $values[] = $read['value'];
            $offset = $read['next'];
        }

        return $values;
    }

    private function pdfNameValue(string $value): ?string
    {
        $value = trim($value);
        if (!str_starts_with($value, '/')) {
            return null;
        }

        return $this->decodePdfName(substr($value, 1));
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seenObjects
     */
    private function resolvePdfValue(string $value, array $objects, array $seenObjects = []): string
    {
        return $this->resolvePdfValueWithSeen($value, $objects, $seenObjects)['value'];
    }

    /**
     * @param array<int, string> $objects
     * @param array<int, true> $seenObjects
     * @return array{value: string, seen: array<int, true>}
     */
    private function resolvePdfValueWithSeen(string $value, array $objects, array $seenObjects = []): array
    {
        $trimmed = trim($value);
        if (preg_match('/^(\d+)\s+\d+\s+R$/', $trimmed, $match) !== 1) {
            return ['value' => $trimmed, 'seen' => $seenObjects];
        }

        $objectNumber = (int) $match[1];
        if (isset($seenObjects[$objectNumber]) || !isset($objects[$objectNumber])) {
            return ['value' => $trimmed, 'seen' => $seenObjects];
        }

        $seenObjects[$objectNumber] = true;

        return ['value' => trim($objects[$objectNumber]), 'seen' => $seenObjects];
    }

    /**
     * @return array{value: string, next: int}|null
     */
    private function readBalancedArray(string $source, int $offset): ?array
    {
        $length = strlen($source);
        $depth = 0;
        for ($index = $offset; $index < $length; $index++) {
            $char = $source[$index];
            if ($char === '(') {
                $index = $this->skipPdfLiteralString($source, $index) - 1;
                continue;
            }
            if ($char === '<' && substr($source, $index, 2) !== '<<') {
                $end = strpos($source, '>', $index + 1);
                if ($end === false) {
                    return null;
                }
                $index = $end;
                continue;
            }
            if ($char === '[') {
                $depth++;
                continue;
            }
            if ($char !== ']') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                return ['value' => substr($source, $offset, $index - $offset + 1), 'next' => $index + 1];
            }
        }

        return null;
    }

    /**
     * @return array{value: string, next: int}|null
     */
    private function readBalancedDictionary(string $source, int $offset): ?array
    {
        $length = strlen($source);
        $depth = 0;
        for ($index = $offset; $index < $length; $index++) {
            if ($source[$index] === '(') {
                $index = $this->skipPdfLiteralString($source, $index) - 1;
                continue;
            }
            if ($source[$index] === '<' && substr($source, $index, 2) === '<<') {
                $depth++;
                $index++;
                continue;
            }
            if ($source[$index] === '<') {
                $end = strpos($source, '>', $index + 1);
                if ($end === false) {
                    return null;
                }
                $index = $end;
                continue;
            }
            if ($source[$index] !== '>' || substr($source, $index, 2) !== '>>') {
                continue;
            }

            $depth--;
            $index++;
            if ($depth === 0) {
                return ['value' => substr($source, $offset, $index - $offset + 1), 'next' => $index + 1];
            }
        }

        return null;
    }

    private function skipPdfLiteralString(string $source, int $offset): int
    {
        $length = strlen($source);
        $depth = 0;
        for ($index = $offset; $index < $length; $index++) {
            $char = $source[$index];
            if ($char === '\\') {
                $index++;
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char !== ')') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                return $index + 1;
            }
        }

        return $length;
    }

    private function skipPdfWhitespace(string $source, int $offset): int
    {
        $length = strlen($source);
        while ($offset < $length) {
            $char = $source[$offset];
            if ($char === '%') {
                $lineEnd = strcspn($source, "\r\n", $offset);
                $offset += $lineEnd;
                continue;
            }
            if (!str_contains(" \t\r\n\f\0", $char)) {
                break;
            }
            $offset++;
        }

        return $offset;
    }

    private function jpegComponentCount(string $jpegBytes): ?int
    {
        foreach ($this->jpegMarkerSegments($jpegBytes) as $segment) {
            $marker = $segment['marker'];
            if (
                in_array($marker, [0xc0, 0xc1, 0xc2, 0xc3, 0xc5, 0xc6, 0xc7, 0xc9, 0xca, 0xcb, 0xcd, 0xce, 0xcf], true)
                && strlen($segment['payload']) >= 6
            ) {
                return ord($segment['payload'][5]);
            }
        }

        return null;
    }

    private function jpegAdobeApp14Transform(string $jpegBytes): ?int
    {
        foreach ($this->jpegMarkerSegments($jpegBytes) as $segment) {
            if ($segment['marker'] === 0xee && strlen($segment['payload']) >= 12 && str_starts_with($segment['payload'], 'Adobe')) {
                return ord($segment['payload'][11]);
            }
        }

        return null;
    }

    /**
     * @return list<array{marker: int, payload: string}>
     */
    private function jpegMarkerSegments(string $jpegBytes): array
    {
        $segments = [];
        $length = strlen($jpegBytes);
        $offset = str_starts_with($jpegBytes, "\xff\xd8") ? 2 : 0;

        while ($offset + 4 <= $length) {
            if ($jpegBytes[$offset] !== "\xff") {
                $offset++;
                continue;
            }

            while ($offset < $length && $jpegBytes[$offset] === "\xff") {
                $offset++;
            }
            if ($offset >= $length) {
                break;
            }

            $marker = ord($jpegBytes[$offset]);
            $offset++;
            if ($marker === 0xda || $marker === 0xd9) {
                break;
            }
            if ($marker === 0x01 || ($marker >= 0xd0 && $marker <= 0xd7)) {
                continue;
            }
            if ($offset + 2 > $length) {
                break;
            }

            $segmentLength = unpack('n', substr($jpegBytes, $offset, 2))[1];
            if (!is_int($segmentLength) || $segmentLength < 2 || $offset + $segmentLength > $length) {
                break;
            }

            $segments[] = [
                'marker' => $marker,
                'payload' => substr($jpegBytes, $offset + 2, $segmentLength - 2),
            ];
            $offset += $segmentLength;
        }

        return $segments;
    }

    /**
     * @param list<int> $ycck
     * @return list<int>
     */
    private function ycckToCmyk(array $ycck): array
    {
        [$y, $cb, $cr, $k] = $ycck;
        $red = $this->byteValue($y + (1.402 * ($cr - 128)));
        $green = $this->byteValue($y - (0.34414 * ($cb - 128)) - (0.71414 * ($cr - 128)));
        $blue = $this->byteValue($y + (1.772 * ($cb - 128)));

        return [255 - $red, 255 - $green, 255 - $blue, $k];
    }

    /**
     * @param list<int> $cmyk
     * @return array{red: int, green: int, blue: int}
     */
    private function cmykToRgb(array $cmyk): array
    {
        [$cyan, $magenta, $yellow, $black] = $cmyk;
        $blackFactor = (255 - $black) / 255;

        return [
            'red' => $this->byteValue((255 - $cyan) * $blackFactor),
            'green' => $this->byteValue((255 - $magenta) * $blackFactor),
            'blue' => $this->byteValue((255 - $yellow) * $blackFactor),
        ];
    }

    private function byteValue(int|float $value): int
    {
        return max(0, min(255, (int) round($value)));
    }

    private function normalizedRotation(int $rotation): int
    {
        $rotation %= 360;
        if ($rotation < 0) {
            $rotation += 360;
        }

        return in_array($rotation, [0, 90, 180, 270], true) ? $rotation : 0;
    }

    private function decodePdfName(string $name): string
    {
        return preg_replace_callback(
            '/#([0-9a-fA-F]{2})/',
            static fn (array $match): string => chr(hexdec($match[1])),
            $name
        ) ?? $name;
    }

    /**
     * @param array<mixed> $bbox
     * @return list<float>
     */
    private function bbox(array $bbox): array
    {
        $values = array_values($bbox);
        if (count($values) !== 4) {
            throw new InvalidArgumentException('bbox must have 4 elements');
        }

        foreach ($values as $value) {
            if (!is_float($value) && !is_int($value)) {
                throw new InvalidArgumentException('bbox values must be numeric');
            }
        }

        return array_map(static fn (float|int $value): float => (float) $value, $values);
    }
}
