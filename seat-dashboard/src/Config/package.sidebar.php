<?php

return [
    '00custom' => [
        'name'          => 'Custom',
        'label'         => 'Custom',
        'icon'          => 'fas fa-tools',
        'route_segment' => 'seat-dashboard',
        'permission'    => 'seat-dashboard.view',
        'entries'       => [
            [
                'name'  => 'Dashboard',
                'label' => 'Dashboard',
                'icon'  => 'fas fa-tachometer-alt',
                'route' => 'seat-dashboard::index',
                'permission' => 'seat-dashboard.view',
            ],
        ],
    ],
];
