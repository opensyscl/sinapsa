<?php

namespace App\Integrations\Google;

use RuntimeException;

/** El refresh_token ya no sirve: el usuario final tiene que volver a conectar. */
class GoogleReauthRequired extends RuntimeException {}
