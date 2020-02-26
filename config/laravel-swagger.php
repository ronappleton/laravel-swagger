<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Basic Info
    |--------------------------------------------------------------------------
    |
    | The basic info for the application such as the title description,
    | description, version, etc...
    |
    */

    'title' => env('APP_NAME', 'Laravel'),

    'description' => env('APP_DESCRIPTION', ''),

    'app_version' => env('APP_VERSION', '1.0.0'),

    'host' => env('APP_URL', 'localhost'),

    'basePath' => '/',

    'schemes' => [
        'http',
        // 'https',
    ],

    'consumes' => [
        // 'application/json',
    ],

    'produces' => [
        // 'application/json',
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignore request methods
    |--------------------------------------------------------------------------
    |
    | Request methods in the 'global_ignored_request_methods' array will be ignored
    | in the paths array
    | 'controller_ignored_request_methods' is request methods to be ignored within a
    | given controller
    | 'controller_method_ignored_request_methods' is request methods to be ignored
    | for certain methods in certain controllers.
    */

    'global_ignored_request_methods' => [
        'head',
    ],

    'controller_ignored_request_methods' => [
    ],

    'controller_method_ignored_request_methods' => [

    ],

    /*
    |--------------------------------------------------------------------------
    | Parse summary and descriptions
    |--------------------------------------------------------------------------
    |
    | If true will parse the action method docBlock and make it's best guess
    | for what is the summary and description. Usually the first line will be
    | used as the route's summary and any paragraphs below (other than
    | annotations) will be used as the description. It will also parse any
    | appropriate annotations, such as @deprecated.
    |
    */

    'parse_doc_block' => true,

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    |
    | If your application uses Laravel's Passport package with the recommended
    | settings, Laravel Swagger will attempt to parse your settings and
    | automatically generate the securityDefinitions along with the operation
    | object's security parameter, you may turn off this behavior with parse_security.
    |
    | Possible values for flow: ["implicit", "password", "application", "accessCode"]
    | See https://medium.com/@darutk/diagrams-and-movies-of-all-the-oauth-2-0-flows-194f3c3ade85
    | for more information.
    |
    */

    'parse_security' => true,

    'auth_flow' => 'accessCode',

    /*
    |--------------------------------------------------------------------------
    | Output options
    |--------------------------------------------------------------------------
    |
    | Here you can alter the output of the generator.
    | You can output to 'file' or 'console'.
    | You can choose the file type 'json' or 'yaml'.
    | You can set the output path, /public/swagger by default.
    | You can set the disk used for storage using 'disk', by default it is
    | configured to use this packages disk for the public folder (storage).
    |
    | If the output option is used on the command line, it will override these
    | settings.
    */

    'output' => 'file', // || file

    'file_type' => 'json', // || yaml

    'path' => public_path('swagger'),

    'file_name' => 'swagger',

    'disk' => 'swagger',
    /*
    |--------------------------------------------------------------------------
    | Filter options
    |--------------------------------------------------------------------------
    |
    | Here you can add filters to control the generation of the swagger data.
    | These filters can be used in place of the generator filter option.
    |
    | If a filter is given on the command line, it will override this array.
    */

    'filters' => [
        '/api/'
    ],

    'controller_filters' => [
    ],

    'controller_method_filters' => [
    ],

    'swagger_ui_dist_location' => 'https://github.com/swagger-api/swagger-ui/archive/master.zip',

    'post_ui_generate' => true,
];
