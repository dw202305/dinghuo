<?php
// 日志配置
return [
    'default' => 'file',
    'channels' => [
        'file' => [
            'type' => 'File',
            'path' => '',
            'single' => false,
            'apart_level' => [],
            'max_files' => 30,
        ],
    ],
];
