<?php

return [
    'install' => [
        [
            'table' => 'settings',
            'rows'  => [
                ['class' => 'CMS', 'key' => 'siteName',   'value' => 'Pubvana', 'type' => 'string', 'title' => 'Site Name', 'description' => 'Enter the name of your site'],
                ['class' => 'CMS', 'key' => 'siteByline', 'value' => 'Publishing Nirvana', 'type' => 'string', 'title' => 'Site Byline', 'description' => 'Enter the slogan of your website'],
            ],
        ],
        [
            'table' => 'auth_permissions',
            'rows'  => [
                ['alias' => 'settings.edit', 'description' => 'Edit site settings'],
            ],
        ],
    ],
];
