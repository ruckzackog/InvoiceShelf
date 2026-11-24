<?php

namespace App\Services;

/*
 * Two options:
 *  - Barryvdh\DomPDF\Facade\Pdf
 *  - Gotenberg
*/

use App;
use App\Services\PDFDrivers\GotenbergPDFDriver;
use Barryvdh\DomPDF\PDF;
use Illuminate\Http\Response;

interface ResponseStream
{
    public function stream(string $filename): Response;

    public function output(): string;
}

interface PDFDriver
{
    public function loadView(string $template): ResponseStream;
}

class PDFDriverFactory
{
    /**
     * @return PDF|GotenbergPDFDriver
     */
    public static function create(string $driver)
    {
        return match ($driver) {
            'dompdf' => App::make('dompdf.wrapper'),
            'gotenberg' => new GotenbergPDFDriver,
            default => throw new \InvalidArgumentException('Invalid PDFDriver requested')
        };
    }
}

class PDFService
{
    /**
     * Render a view with the configured driver.
     *
     * Templates listed in pdf.gotenberg_templates always go to Gotenberg, the
     * rest keep the global driver. $options are only used by Gotenberg:
     * "footer" (a view name) and "margins" ([top, bottom, left, right]).
     *
     * @param  array{footer?: string, margins?: array{0: string, 1: string, 2: string, 3: string}}  $options
     */
    public static function loadView(string $template, ?string $templateName = null, array $options = [])
    {
        $driver = self::driverFor($templateName);

        if ($driver === 'gotenberg') {
            return (new GotenbergPDFDriver)->loadView($template, $options);
        }

        return PDFDriverFactory::create($driver)->loadView($template);
    }

    public static function driverFor(?string $templateName): string
    {
        if ($templateName !== null && in_array($templateName, self::gotenbergTemplates(), true)) {
            return 'gotenberg';
        }

        return (string) config('pdf.driver');
    }

    /**
     * @return list<string>
     */
    public static function gotenbergTemplates(): array
    {
        $templates = config('pdf.gotenberg_templates', []);

        if (is_string($templates)) {
            $templates = explode(',', $templates);
        }

        return array_values(array_filter(array_map('trim', (array) $templates)));
    }
}
