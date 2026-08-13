<?php

/**
 * Fallback si le document root est la racine du projet (hébergement mutualisé).
 * Préférer configurer le vhost sur le dossier public/ quand c'est possible.
 */

require __DIR__.'/public/index.php';
