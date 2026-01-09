<?php

/**
 * Flint Configuration
 *
 * This file contains sensitive configuration. Keep secure permissions (0600).
 * DO NOT commit this file to version control.
 */

return array (
  'site' =>
  array (
    'name' => 'TEST',
    'theme' => 'motion',
  ),
  'mail' =>
  array (
    'admin_email' => 'chrismewhort@gmail.com',
    'smtp_host' => 'localhost',
  ),
  'admin' =>
  array (
    'password' => '$2y$12$8vurOMM1cWgXfi8iAgN6bekru/YLRkE.16TQQQnrQsxL/rgowgYB.',
  ),
  'system' =>
  array (
    'cache_enabled' => false,
    'show_errors' => true,
    'debug' => false,
  ),
  'updates' =>
  array (
    'auto_update' => 'ask',
  ),
);
