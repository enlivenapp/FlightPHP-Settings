<?php 


return [
    'install' => [
        	[
            'table' => 'settings',
            'rows'  => [
                ['class' => 'CMS', 'key' => 'siteName',   'value' => '', 'type' => 'string'],
                ['class' => 'CMS', 'key' => 'siteByline', 'value' => '', 'type' => 'string'],
            ],
        ],
    ],
];
