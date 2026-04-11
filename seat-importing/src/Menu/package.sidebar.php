<?php

return [
    '00market' => [
        'name'          => 'Market Analysis',
        'label'         => 'Market Analysis',
        'icon'          => 'fas fa-chart-line',
        'route_segment' => 'seat-importing',
        'permission'    => 'market.import',
        'entries'       => [
            [
                'name'          => 'Analysis',
                'label'         => 'Analysis',
                'icon'          => 'fas fa-chart-bar',
                'route'         => 'seat-importing.dashboard',
                'route_segment' => 'seat-importing',
                'permission'    => 'market.import',
            ],
            [
                'name'          => 'Stocking Dashboard',
                'label'         => 'Stocking Dashboard',
                'icon'          => 'fas fa-warehouse',
                'route'         => 'seat-importing.stocking.index',
                'route_segment' => 'seat-importing/stocking',
                'permission'    => 'market.import',
            ],
            [
                'name'          => 'Settings',
                'label'         => 'Settings',
                'icon'          => 'fas fa-cogs',
                'route'         => 'seat-importing.settings',
                'route_segment' => 'seat-importing.settings',
                'permission'    => 'market.settings',
            ],
        ],
    ],
];
