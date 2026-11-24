<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed loadView(string $template, ?string $templateName = null, array $options = [])
 */
class PDF extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'pdf.driver';
    }
}
