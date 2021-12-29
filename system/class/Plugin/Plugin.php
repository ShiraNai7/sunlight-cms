<?php

namespace Sunlight\Plugin;

use Sunlight\Core;
use Sunlight\Plugin\Action\PluginAction;
use Sunlight\Router;
use Sunlight\Util\ConfigurationFile;

abstract class Plugin
{
    /** ID pattern */
    const ID_PATTERN = '[a-zA-Z][a-zA-Z0-9_.\-]*';
    /** Name of the plugin definition file */
    const FILE = 'plugin.json';
    /** Name of the plugin deactivating file */
    const DEACTIVATING_FILE = 'DISABLED';

    /** Plugin status - OK */
    const STATUS_OK = 0;
    /** Plugin status - has error messages */
    const STATUS_HAS_ERRORS = 1;
    /** Plugin status - not installed */
    const STATUS_NEEDS_INSTALLATION = 2;
    /** Plugin status - disabled */
    const STATUS_DISABLED = 3;

    /** @var string */
    protected $id;
    /** @var string */
    protected $camelId;
    /** @var string */
    protected $type;
    /** @var int */
    protected $status;
    /** @var bool|null */
    protected $installed;
    /** @var string */
    protected $dir;
    /** @var string */
    protected $file;
    /** @var string */
    protected $webPath;
    /** @var string[] */
    protected $errors;
    /** @var array */
    protected $options;
    /** @var PluginManager */
    protected $manager;
    /** @var ConfigurationFile|null */
    private $config;

    function __construct(PluginData $data, PluginManager $manager)
    {
        $this->id = $data->id;
        $this->camelId = $data->camelId;
        $this->type = $data->type;
        $this->status = $data->status;
        $this->installed = $data->installed;
        $this->dir = $data->dir;
        $this->file = $data->file;
        $this->webPath = $data->webPath;
        $this->errors = $data->errors;
        $this->options = $data->options;
        $this->manager = $manager;
    }

    /**
     * See if this plugin is currently active
     *
     * @return bool
     */
    static function isActive(): bool
    {
        return Core::$pluginManager->hasInstance(static::class);
    }

    /**
     * Get plugin instance
     *
     * @throws \OutOfBoundsException if the plugin is not currently active
     */
    static function getInstance(): self
    {
        return Core::$pluginManager->getInstance(static::class);
    }

    /**
     * Get plugin identifier
     *
     * @return string
     */
    function getId(): string
    {
        return $this->id;
    }

    /**
     * Get camel cased plugin identifier
     *
     * @return string
     */
    function getCamelId(): string
    {
        return $this->camelId;
    }

    /**
     * @return string
     */
    function getType(): string
    {
        return $this->type;
    }

    /**
     * @return int
     */
    function getStatus(): int
    {
        return $this->status;
    }

    /**
     * See if the plugin is disabled
     *
     * @return bool
     */
    function isDisabled(): bool
    {
        return $this->status === self::STATUS_DISABLED;
    }

    /**
     * See if the plugin can be disabled
     *
     * @return bool
     */
    function canBeDisabled(): bool
    {
        return !$this->isDisabled();
    }

    /**
     * See if the plugin has been installed
     *
     * @return bool|null null if the plugin has no installer
     */
    function isInstalled(): ?bool
    {
        return $this->installed;
    }

    /**
     * See if the plugin has an installer
     *
     * @return bool
     */
    function hasInstaller(): bool
    {
        return $this->options['installer'] !== null;
    }

    /**
     * Get installer for this plugin
     *
     * @throws \LogicException if the plugin has no installer
     * @return PluginInstaller
     */
    function getInstaller(): PluginInstaller
    {
        if (!$this->hasInstaller()) {
            throw new \LogicException('Plugin has no installer');
        }

        return require $this->options['installer'];
    }

    /**
     * See if the plugin needs installation be activated
     *
     * @return bool
     */
    function needsInstallation(): bool
    {
        return $this->status === self::STATUS_NEEDS_INSTALLATION;
    }

    /**
     * See if the plugin can be installed
     *
     * @return bool
     */
    function canBeInstalled(): bool
    {
        return $this->hasInstaller() && $this->installed === false;
    }

    /**
     * See if the plugin can be uninstalled
     *
     * @return bool
     */
    function canBeUninstalled(): bool
    {
        return $this->hasInstaller() && $this->installed === true;
    }

    /**
     * See if the plugin can be removed
     *
     * @return bool
     */
    function canBeRemoved(): bool
    {
        return !$this->hasInstaller() || $this->installed === false;
    }

    /**
     * See if the plugin has errors
     *
     * @return bool
     */
    function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * @return string[]
     */
    function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return string
     */
    function getDirectory(): string
    {
        return $this->dir;
    }

    /**
     * @return string
     */
    function getFile(): string
    {
        return $this->file;
    }

    /**
     * @param bool $absolute
     * @return string
     */
    function getWebPath(bool $absolute = false): string
    {
        return Router::path($this->webPath, ['absolute' => $absolute]);
    }

    /**
     * @param string $name
     * @throws \OutOfBoundsException if the option does not exist
     * @return mixed
     */
    function getOption(string $name)
    {
        if (!array_key_exists($name, $this->options)) {
            throw new \OutOfBoundsException(sprintf('Option "%s" does not exist', $name));
        }

        return $this->options[$name];
    }

    /**
     * @return array
     */
    function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Get plugin configuration
     *
     * @return ConfigurationFile
     */
    function getConfig(): ConfigurationFile
    {
        if ($this->config === null) {
            $defaults = $this->getConfigDefaults();

            if (empty($defaults)) {
                throw new \LogicException('To use the configuration file, defaults must be specified by overriding the getConfigDefaults() method');
            }

            $this->config = new ConfigurationFile($this->getConfigPath(), $defaults);
        }

        return $this->config;
    }

    /**
     * @param string $key
     * @return string
     */
    function getConfigLabel(string $key): string
    {
        return $key;
    }

    /**
     * @return array
     */
    protected function getConfigDefaults(): array
    {
        return [];
    }

    /**
     * @return string
     */
    protected function getConfigPath(): string
    {
        return $this->dir . '/config.php';
    }

    /**
     * @param string $name
     * @return PluginAction|null
     */
    function getAction(string $name): ?PluginAction
    {
        switch ($name) {
            case 'info':
                return new Action\InfoAction($this);
            case 'config':
                return new Action\ConfigAction($this);
            case 'install':
                return new Action\InstallAction($this);
            case 'uninstall':
                return new Action\UninstallAction($this);
            case 'disable':
                return new Action\DisableAction($this);
            case 'enable':
                return new Action\EnableAction($this);
            case 'remove':
                return new Action\RemoveAction($this);
        }

        return null;
    }

    /**
     * Get list of currently available actions
     *
     * @throws \RuntimeException if run outside of administration environment
     * @return string[] name => label
     */
    function getActionList(): array
    {
        if (Core::$env !== Core::ENV_ADMIN) {
            throw new \RuntimeException('Plugin actions require administration environment');
        }

        $actions = [];

        $actions['info'] = _lang('admin.plugins.action.do.info');
        $actions += $this->getCustomActionList();
        if (count($this->getConfigDefaults())) {
            $actions['config'] = _lang('admin.plugins.action.do.config');
        }
        if ($this->canBeInstalled()) {
            $actions['install'] = _lang('admin.plugins.action.do.install');
        }
        if ($this->canBeUninstalled()) {
            $actions['uninstall'] = _lang('admin.plugins.action.do.uninstall');
        }
        if ($this->canBeDisabled()) {
            $actions['disable'] = _lang('admin.plugins.action.do.disable');
        }
        if ($this->isDisabled()) {
            $actions['enable'] = _lang('admin.plugins.action.do.enable');
        }
        if ($this->canBeRemoved()) {
            $actions['remove'] = _lang('admin.plugins.action.do.remove');
        }

        return $actions;
    }

    /**
     * Get list of custom actions
     *
     * @return string[] name => label
     */
    protected function getCustomActionList(): array
    {
        return [];
    }
}
