<?php
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }

// cors_origin: allowed origin for browser clients, empty disables CORS headers.
return ['cors_origin' => ''];
