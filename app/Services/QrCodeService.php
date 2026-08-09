<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Génère des QR codes via bacon/bacon-qr-code.
 * Retourne un SVG encodé en base64 — compatible avec DomPDF.
 */
class QrCodeService
{
    /**
     * Génère un QR code SVG en base64 à partir d'une URL.
     *
     * @param  string  $data  L'URL à encoder dans le QR code
     * @param  int     $size  Taille en pixels
     * @return string         data URI base64 SVG
     */
    public static function generateBase64Svg(string $data, int $size = 150): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);
        $svg    = $writer->writeString($data);

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
