<?php

namespace App\Services\EstimateDocument;

use Illuminate\Support\Facades\File;

/**
 * CSS for the offer PDF: the same files the editor uses, with the font files
 * inlined as data URIs so Gotenberg never has to fetch anything.
 */
class DocumentStyles
{
    public static function css(): string
    {
        return self::fonts()."\n".File::get(resource_path('css/angebot-document.css'));
    }

    public static function fonts(): string
    {
        $css = File::get(resource_path('css/angebot-fonts.css'));

        return preg_replace_callback(
            "#url\\('\\.\\./static/fonts/angebot/([a-z0-9-]+\\.woff2)'\\)#",
            function (array $match) {
                $path = resource_path('static/fonts/angebot/'.$match[1]);

                if (! File::exists($path)) {
                    return 'url()';
                }

                return "url('data:font/woff2;base64,".base64_encode(File::get($path))."')";
            },
            $css
        );
    }
}
