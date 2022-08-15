<?php

namespace Sunlight\Plugin;

use Sunlight\Core;

class LanguagePlugin extends Plugin
{
    function canBeDisabled(): bool
    {
        return !$this->isFallback() && parent::canBeDisabled();
    }
    
    function canBeRemoved(): bool
    {
        return !$this->isFallback() && parent::canBeRemoved();
    }

    /**
     * See if this is the fallback language
     */
    function isFallback(): bool
    {
        return $this->id === Core::$fallbackLang;
    }

    /**
     * Get localization entries
     *
     * @param bool|null $admin load administration dictionary as well 1/0 (null = auto)
     * @return array|bool false on failure
     */
    function getLocalizationEntries(?bool $admin = null)
    {
        if ($admin === null) {
            $admin = Core::$env === Core::ENV_ADMIN;
        }

        // base dictionary
        $fileName = sprintf('%s/dictionary.php', $this->dir);
        if (is_file($fileName)) {
            $data = (array) include $fileName;

            // admin dictionary
            if ($admin) {
                $adminFileName = sprintf('%s/admin_dictionary.php', $this->dir);
                if (is_file($adminFileName)) {
                    $data += (array) include $adminFileName;
                } elseif ($this->manager->has($this->type, Core::$fallbackLang)) {
                    $adminFileName = sprintf(
                        '%s/admin_dictionary.php',
                        $this->manager->getLanguage(Core::$fallbackLang)->getDirectory()
                    );
                    if (is_file($adminFileName)) {
                        $data += (array) include $adminFileName;
                    }
                }
            }

            return $data;
        }

        return false;
    }
}
