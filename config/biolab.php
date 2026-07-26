<?php

return [
    'storage' => env('BIOLAB_STORAGE', 'json'),

    'users' => [
        [
            'name' => env('BIOLAB_ADMIN_NAME', 'Administrador'),
            'email' => env('BIOLAB_ADMIN_EMAIL', 'admin@biolab.local'),
            'password' => env('BIOLAB_ADMIN_PASSWORD', 'admin123'),
            'role' => 'admin',
        ],
        [
            'name' => env('BIOLAB_RECEPTION_NAME', 'Recepcion'),
            'email' => env('BIOLAB_RECEPTION_EMAIL', 'recepcion@biolab.local'),
            'password' => env('BIOLAB_RECEPTION_PASSWORD', 'recepcion123'),
            'role' => 'recepcion',
        ],
        [
            'name' => env('BIOLAB_LAB_NAME', 'Laboratorio'),
            'email' => env('BIOLAB_LAB_EMAIL', 'lab@biolab.local'),
            'password' => env('BIOLAB_LAB_PASSWORD', 'lab123'),
            'role' => 'laboratorio',
        ],
        [
            'name' => env('BIOLAB_CASHIER_NAME', 'Caja'),
            'email' => env('BIOLAB_CASHIER_EMAIL', 'caja@biolab.local'),
            'password' => env('BIOLAB_CASHIER_PASSWORD', 'caja123'),
            'role' => 'caja',
        ],
    ],
];
