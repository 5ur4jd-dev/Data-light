<?php
/**
 * data-light - Entry Point Redirect
 * Redirects to the public/ directory for servers where document root cannot be changed.
 */

header('Location: public/');
exit;
