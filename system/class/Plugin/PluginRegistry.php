<?php

namespace Sunlight\Plugin;

class PluginRegistry
{
    /** @var array<string, Plugin> ID-indexed */
    public $map = [];
    /** @var array<string, array<string, Plugin>> type and name-indexed */
    public $typeMap = [];

    /**
     * See if the given plugin ID exists
     */
    function has(string $id): bool
    {
        return isset($this->map[$id]);
    }

    /**
     * Get plugin by ID
     */
    function get(string $id): ?Plugin
    {
        return $this->map[$id] ?? null;
    }

    /**
     * Get all plugins of the given type
     *
     * @return array<string, Plugin> name-indexed
     */
    function getByType(string $type): array
    {
        return $this->typeMap[$type] ?? [];
    }

    /**
     * See if a plugin with the given type and name exists
     */
    function hasName(string $type, string $name): bool
    {
        return isset($this->typeMap[$type][$name]);
    }

    /**
     * Get a single plugin by type and name
     */
    function getByName(string $type, string $name): ?Plugin
    {
        return $this->typeMap[$type][$name] ?? null;
    }

    function hasExtend(string $name): bool
    {
        return $this->hasName('extend', $name);
    }

    function getExtend(string $name): ExtendPlugin
    {
        return $this->getByName('extend', $name);
    }

    /**
     * @return array<string, ExtendPlugin>
     */
    function getExtends(): array
    {
        return $this->getByType('extend');
    }

    function hasTemplate(string $name): bool
    {
        return $this->hasName('template', $name);
    }

    function getTemplate(string $name): TemplatePlugin
    {
        return $this->getByName('template', $name);
    }

    /**
     * @return array<string, TemplatePlugin>
     */
    function getTemplates(): array
    {
        return $this->getByType('template');
    }

    function hasLanguage(string $name): bool
    {
        return $this->hasName('language', $name);
    }

    function getLanguage(string $name): LanguagePlugin
    {
        return $this->getByName('language', $name);
    }

    /**
     * @return array<string, LanguagePlugin>
     */
    function getLanguages(): array
    {
        return $this->getByType('language');
    }
}
