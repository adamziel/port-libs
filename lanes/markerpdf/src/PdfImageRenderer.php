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

    private function imageColorSpace(string $dictionary): ?string
    {
        if (preg_match('/\/ColorSpace\s+(?:\/([^\s\[\]()<>{}\/%]+)|\[\s*\/([^\s\[\]()<>{}\/%]+))/s', $dictionary, $match) !== 1) {
            return null;
        }

        return $this->decodePdfName($match[1] !== '' ? $match[1] : $match[2]);
    }

    private function imageBitsPerComponent(string $dictionary): ?int
    {
        if (preg_match('/\/BitsPerComponent\s+(\d+)/', $dictionary, $match) !== 1) {
            return null;
        }

        return max(1, (int) $match[1]);
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
            'DeviceGray' => 1,
            'DeviceRGB', 'CalRGB' => 3,
            'DeviceCMYK' => 4,
            default => null,
        };
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
