<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ГПЗУ Processing Feature
    |--------------------------------------------------------------------------
    |
    | Enable or disable the ГПЗУ (Градостроительный план земельного участка)
    | parsing feature. When disabled, no ГПЗУ processing endpoints will
    | be registered and the info button will not appear in the UI.
    |
    */

    'enabled' => (bool) env('GPZU_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | OCR Language
    |--------------------------------------------------------------------------
    |
    | Tesseract OCR language for text recognition. Default is Russian.
    |
    */

    'ocr_lang' => env('GPZU_OCR_LANG', 'rus'),

    /*
    |--------------------------------------------------------------------------
    | OCR Resolution
    |--------------------------------------------------------------------------
    |
    | DPI for page-to-image conversion before OCR. Higher = better quality
    | but slower. Recommended: 200-300.
    |
    */

    'ocr_dpi' => (int) env('GPZU_OCR_DPI', 300),

    /*
    |--------------------------------------------------------------------------
    | PDF Page Extraction
    |--------------------------------------------------------------------------
    |
    | Temporary directory for extracted PDF pages.
    |
    */

    'temp_dir' => storage_path('app/gpzu'),

];
