<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Should the SPX MCP server be enabled?
    |--------------------------------------------------------------------------
    |
    | This option may be used to disable the SPX MCP server entirely.
    |
    */

    'enabled' => env('SPX_MCP_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | The directory where SPX stores its data files
    |--------------------------------------------------------------------------
    |
    | This is configured in your php.ini file as `spx.data_dir`.
    |
    */

    'spx_data_dir' => env('SPX_DATA_DIR', ini_get('spx.data_dir')),

];