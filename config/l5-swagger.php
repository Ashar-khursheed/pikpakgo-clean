<!-- <?php

return [
    'default' => 'default',

    'documentations' => [
        'default' => [
            'api' => [
                'title' => 'PikPakGo API Documentation',
            ],

            'routes' => [
                'api' => 'documentation',
            ],

            'paths' => [
                'use_absolute_path' => true,
                'docs_json' => 'api-docs.json',
                'docs_yaml' => 'api-docs.yaml',
                'format_to_use_for_docs' => 'json',
                'annotations' => [
                    base_path('app'),
                ],
            ],
        ],
    ],

    'defaults' => [
        'routes' => [
            'docs' => 'docs',
            'oauth2_callback' => 'oauth2-callback',
            'middleware' => [
                'api' => [],
                'asset' => [],
                'docs' => [],
                'oauth2_callback' => [],
            ],
            'group_options' => [],
        ],

        'paths' => [
            'docs' => storage_path('api-docs'),
            'views' => base_path('resources/views/vendor/l5-swagger'),

            // ✅ API BASE COMPLETELY REMOVED
            'base' => '',

            'swagger_ui_assets_path' => 'vendor/swagger-api/swagger-ui/dist/',
            'excludes' => [],
        ],

        // ✅ NO /api ANYWHERE
        'servers' => [
            [
                'url' => 'http://localhost:8000',
                'description' => 'Local Server',
            ],
            [
                'url' => 'https://pickpackgo.in-sourceit.com',
                'description' => 'Production Server',
            ],
        ],

        'scanOptions' => [
            'open_api_spec_version' => \L5Swagger\Generator::OPEN_API_DEFAULT_SPEC_VERSION,
        ],

        'securityDefinitions' => [
            'securitySchemes' => [
                'bearerAuth' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'JWT',
                ],
            ],
            'security' => [
                [
                    'bearerAuth' => [],
                ],
            ],
        ],

        'generate_always' => false,
        'generate_yaml_copy' => false,
        'proxy' => false,

        // ❌ HOST GUESSING OFF
        'constants' => [],
    ],
];
