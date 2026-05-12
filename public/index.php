<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

// --- CONFIGURACIÓN GLOBAL DE ZONA HORARIA ---
// Esto fuerza a PHP a usar la hora de Madrid/Península en todo el proyecto
date_default_timezone_set('Europe/Madrid');
// ---------------------------------------------

return static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
