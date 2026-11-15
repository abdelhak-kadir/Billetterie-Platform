<?php

use App\Kernel;

// Le serveur PHP de développement doit envoyer les assets avec leur vrai type MIME.
// Le chemin canonique reste strictement à l'intérieur du répertoire public.
if (PHP_SAPI === 'cli-server') {
    $path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
    $file = realpath(__DIR__.$path);
    if ($file !== false && str_starts_with($file, __DIR__.DIRECTORY_SEPARATOR) && is_file($file)) {
        return false;
    }
}

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
