<?php

namespace Flint;

/**
 * ComponentInterface
 *
 * Standard interface that all Flint components must implement.
 * Ensures consistent component structure and lifecycle management.
 */
interface ComponentInterface
{
    /**
     * Initialize the component and register hooks
     *
     * This method is called when the component is loaded by HookManager.
     * Components should use this method to register their hook callbacks
     * using HookManager::on().
     *
     * @param string $hookManager HookManager class name for registering hooks
     * @param array $config Component configuration from config.php
     */
    public static function init(string $hookManager, array $config): void;

    /**
     * Get component information
     *
     * Returns metadata about the component including name, version,
     * author, and description.
     *
     * @return array Component information
     *   [
     *     'name' => 'Component Name',
     *     'version' => '1.0.0',
     *     'author' => 'Author Name',
     *     'description' => 'Component description'
     *   ]
     */
    public static function getInfo(): array;
}
