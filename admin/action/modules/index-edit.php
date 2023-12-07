<?php

use Sunlight\Admin\Admin;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\Util\Form;
use Sunlight\Util\Request;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

// save
if (isset($_POST['text'])) {
    Settings::update('admin_index_custom', trim(Request::post('text', '')));
    Settings::update('admin_index_custom_pos', (Request::post('pos') == 0) ? '0' : '1');
    $_admin->redirect(Router::admin('index-edit', ['query' => ['saved' => 1]]));

    return;
}

$admin_index_cfg = Settings::getMultiple(['admin_index_custom', 'admin_index_custom_pos']);

// output
$output .= '

<p class="bborder">' . _lang('admin.menu.index.edit.p') . '</p>

' . (isset($_GET['saved']) ? Message::ok(_lang('global.saved')) : '') . '

<form method="post">

<table class="formtable">

<tr>
    <th>' . _lang('admin.menu.index.edit.pos') . '</th>
    <td>' . Form::select(
        'pos',
        [
            0 => _lang('admin.menu.index.edit.pos.0'),
            1 => _lang('admin.menu.index.edit.pos.1')
        ],
        $admin_index_cfg['admin_index_custom_pos']
    ) . '</td>
</tr>

<tr class="valign-top">
    <th>' . _lang('admin.menu.index.edit.text') . '</th>
    <td class="minwidth">' . Admin::editor('index-edit', 'text', $admin_index_cfg['admin_index_custom']) . '</td>
</tr>

<tr>
    <td></td>
    <td>' . Form::input('submit', null, _lang('global.savechanges'), ['class' => 'button bigger', 'accesskey' => 's']) . '</td>
</tr>

</table>

' . Xsrf::getInput() . '</form>
';
