<?php

namespace App\Domains\DataRoom\Support;

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Stamps every page (PDF) or the image itself with the bidder's name,
 * bidder number and time, so leaked copies can be traced.
 *
 * PDFs FPDI can't read directly (compressed cross-reference streams,
 * common in scans) are first converted with Ghostscript. If that also
 * fails, a WatermarkFailed exception is thrown: documents are never served
 * unmarked.
 */
final class Watermarker
{
    /** Compression off only in tests, so the stamp can be read back. */
    public function __construct(private readonly bool $compress = true) {}

    public function stamp(string $content, string $mime, string $line): string
    {
        return match (true) {
            $mime === 'application/pdf' => $this->pdf($content, $line),
            in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) => $this->image($content, $mime, $line),
            default => throw new WatermarkFailed("Unsupported document type {$mime}."),
        };
    }

    private function pdf(string $content, string $line): string
    {
        try {
            return $this->stampPdf($content, $line);
        } catch (Throwable) {
            return $this->stampPdf($this->normalise($content), $line);
        }
    }

    private function stampPdf(string $content, string $line): string
    {
        try {
            $pdf = new Fpdi;
            $pdf->SetCompression($this->compress);
            $pdf->SetAutoPageBreak(false);
            $pages = $pdf->setSourceFile(StreamReader::createByString($content));
            $text = $this->latin1($line);

            for ($i = 1; $i <= $pages; $i++) {
                $tpl = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($tpl);
                if (! is_array($size)) {
                    throw new WatermarkFailed("Could not read the size of page {$i}.");
                }
                $w = (float) $size['width'];
                $h = (float) $size['height'];
                $pdf->AddPage($w > $h ? 'L' : 'P', [$w, $h]);
                $pdf->useTemplate($tpl);

                foreach ([2.5, $h - 7.5] as $y) {
                    $pdf->SetFillColor(255, 255, 255);
                    $pdf->Rect(0, $y - 1, $w, 6, 'F');
                    $pdf->SetFont('Helvetica', 'B', 8);
                    $pdf->SetTextColor(180, 30, 30);
                    $pdf->SetXY(4, $y);
                    $pdf->Cell($w - 8, 4, $text, 0, 0, 'C');
                }
            }

            return $pdf->Output('S');
        } catch (Throwable $e) {
            throw new WatermarkFailed('Could not watermark this PDF: '.$e->getMessage(), 0, $e);
        }
    }

    /** Rewrites a PDF as version 1.4 with Ghostscript so FPDI can read it. */
    private function normalise(string $content): string
    {
        $in = tempnam(sys_get_temp_dir(), 'wm-in');
        $out = tempnam(sys_get_temp_dir(), 'wm-out');
        if ($in === false || $out === false) {
            throw new WatermarkFailed('No temporary space to prepare the PDF.');
        }

        try {
            file_put_contents($in, $content);
            $process = new Process(['gs', '-q', '-dNOPAUSE', '-dBATCH', '-dSAFER', '-dPDFSTOPONERROR', '-sDEVICE=pdfwrite', '-dCompatibilityLevel=1.4', "-sOutputFile={$out}", $in]);
            $process->setTimeout(60)->run();

            if (! $process->isSuccessful() || filesize($out) === 0) {
                throw new WatermarkFailed('Ghostscript could not convert this PDF.');
            }

            return (string) file_get_contents($out);
        } catch (Throwable $e) {
            throw $e instanceof WatermarkFailed ? $e : new WatermarkFailed('Ghostscript is not available to convert this PDF.', 0, $e);
        } finally {
            @unlink($in);
            @unlink($out);
        }
    }

    private function image(string $content, string $mime, string $line): string
    {
        $img = @imagecreatefromstring($content);
        if ($img === false) {
            throw new WatermarkFailed('Could not read this image.');
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $font = 5;
        $band = imagefontheight($font) + 10;
        $white = imagecolorallocatealpha($img, 255, 255, 255, 20);
        $red = imagecolorallocate($img, 180, 30, 30);

        foreach ([0, $h - $band] as $y) {
            imagefilledrectangle($img, 0, $y, $w, $y + $band, (int) $white);
            $text = mb_strimwidth($this->latin1($line), 0, max(10, intdiv($w - 20, imagefontwidth($font))));
            imagestring($img, $font, max(5, intdiv($w - strlen($text) * imagefontwidth($font), 2)), $y + 5, $text, (int) $red);
        }

        ob_start();
        match ($mime) {
            'image/png' => imagepng($img),
            'image/webp' => imagewebp($img, null, 85),
            default => imagejpeg($img, null, 88),
        };
        imagedestroy($img);

        return (string) ob_get_clean();
    }

    /** FPDF's core fonts are Latin-1. */
    private function latin1(string $text): string
    {
        return (string) iconv('UTF-8', 'windows-1252//TRANSLIT', str_replace(['’', '–', '·'], ["'", '-', '-'], $text));
    }
}
