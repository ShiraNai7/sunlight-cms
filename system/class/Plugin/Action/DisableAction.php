<?php

namespace Sunlight\Plugin\Action;

use Sunlight\Action\ActionResult;
use Sunlight\Message;
use Sunlight\Plugin\Plugin;

/**
 * Disable a plugin
 */
class DisableAction extends PluginAction
{
    function getTitle(): string
    {
        return _lang('admin.plugins.action.do.disable');
    }

    protected function execute(): ActionResult
    {
        $dependantsWarning = $this->getDependantsWarning();

        if ($dependantsWarning !== null && !$this->isConfirmed()) {
            return $this->confirm(
                _lang('admin.plugins.action.disable.confirm'),
                [
                    'button_text' => _lang('admin.plugins.action.do.disable'),
                    'content_after' => $dependantsWarning,
                ]
            );
        }

        if ($this->plugin->canBeDisabled() && touch($this->plugin->getDirectory() . '/' . Plugin::DEACTIVATING_FILE)) {
            return ActionResult::success(
                Message::ok(_lang('admin.plugins.action.disable.success', ['%plugin%' => $this->plugin->getOption('name')]))
            );
        }

        return ActionResult::failure(
            Message::error(_lang('admin.plugins.action.disable.failure', ['%plugin%' => $this->plugin->getOption('name')]))
        );
    }
}
