<?php

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

(new Amea\Core\Router())->dispatch('public');
