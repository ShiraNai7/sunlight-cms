<?php

use Sunlight\Database\Database as DB;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\Util\Form;
use Sunlight\Util\Request;
use Sunlight\Util\StringManipulator;
use Sunlight\Xsrf;

defined('SL_ROOT') or exit;

/* ---  priprava  --- */

$message = '';

/* ---  vystup  --- */

// text a menu
$output .= "<p class='bborder'>" . _lang('admin.content.redir.p') . "</p>
<p>
    <a class='button' href='" . _e(Router::admin('content-redir', ['query' => ['new' => 1]])) . "'><img src='" . _e(Router::path('admin/images/icons/new.png')) . "' alt='new' class='icon'>" . _lang('admin.content.redir.act.new') . "</a>
    <a class='button' href='" . _e(Router::admin('content-redir', ['query' => ['wipe' => 1]])) . "'><img src='" . _e(Router::path('admin/images/icons/delete.png')) . "' alt='wipe' class='icon'>" . _lang('admin.content.redir.act.wipe') . '</a>
</p>
';

// akce - uprava / vytvoreni
if (isset($_GET['new']) || isset($_GET['edit'])) {
    do {
        // priprava
        $new = isset($_GET['new']);
        if (!$new) {
            $edit_id = (int) Request::get('edit');
        }

        // zpracovani
        if (isset($_POST['old'])) {

            // nacteni dat
            $q = [];
            $q['old'] = StringManipulator::slugify(trim(Request::post('old', '')), true, '._/');
            $q['new'] = StringManipulator::slugify(trim(Request::post('new', '')), true, '._/');
            $q['permanent'] = Form::loadCheckbox('permanent');
            $q['active'] = Form::loadCheckbox('act');

            // kontrola
            if ($q['old'] === '' || $q['new'] === '') {
                $message = Message::warning(_lang('admin.content.redir.emptyidt'));
            } elseif ($new) {
                // vytvoreni
                DB::insert('redirect', $q);
                $new = false;
                $message = Message::ok(_lang('global.created'));
                break;
            } else {
                // ulozeni
                DB::update('redirect', 'id=' . DB::val($edit_id), $q);
                $message = Message::ok(_lang('global.saved'));
            }

        }

        // nacteni dat
        if ($new) {
            if (!isset($q)) {
                $q = [];
            }
            $q += ['id' => null, 'old' => '', 'new' => '', 'permanent' => '0', 'active' => '1'];
        } else {
            $q = DB::queryRow('SELECT * FROM ' . DB::table('redirect') . ' WHERE id=' . $edit_id);
            if ($q === false) {
                break;
            }
        }

        // formular
        $output .= $message . "\n<form method='post'>
<table class='formtable'>

<tr>
    <th>" . _lang('admin.content.redir.old') . "</th>
    <td><input type='text' name='old' value='" . $q['old'] . "' class='inputmedium' maxlength='255'></td>
</tr>

<tr>
    <th>" . _lang('admin.content.redir.new') . "</th>
    <td><input type='text' name='new' value='" . $q['new'] . "' class='inputmedium' maxlength='255'></td>
</tr>

<tr>
    <th>" . _lang('admin.content.redir.permanent') . "</th>
    <td><input type='checkbox' name='permanent' value='1'" . Form::activateCheckbox($q['permanent']) . '></td>
</tr>

<tr>
    <th>' . _lang('admin.content.redir.act') . "</th>
    <td><input type='checkbox' name='act' value='1'" . Form::activateCheckbox($q['active']) . "></td>
</tr>

<tr>
    <td></td>
    <td><input type='submit' value='" . _lang('global.' . ($new ? 'create' : 'save')) . "'></td>
</tr>

</table>
" . Xsrf::getInput() . '</form>';
    } while (false);
} elseif (isset($_GET['del']) && Xsrf::check(true)) {

    // smazani
    DB::delete('redirect', 'id=' . DB::val(Request::get('del')));
    $output .= Message::ok(_lang('global.done'));

} elseif (isset($_GET['wipe'])) {

    // smazani vsech
    if (isset($_POST['wipe_confirm'])) {
        DB::query('TRUNCATE TABLE ' . DB::table('redirect'));
        $output .= Message::ok(_lang('global.done'));
    } else {
        $output .= "
<form method='post' class='well'>
" . Message::warning(_lang('admin.content.redir.act.wipe.confirm')) . "
<input type='submit' name='wipe_confirm' value='" . _lang('global.confirmdelete') . "'>
" . Xsrf::getInput() . '</form>
';
    }

}

// tabulka
$output .= "<table class='list list-hover list-max'>
<thead>
<tr>
    <td>" . _lang('admin.content.redir.old') . '</td>
    <td>' . _lang('admin.content.redir.new') . '</td>
    <td>' . _lang('admin.content.redir.permanent') . '</td>
    <td>' . _lang('admin.content.redir.act') . '</td>
    <td>' . _lang('global.action') . '</td>
</tr>
</thead>
<tbody>
';

// vypis
$counter = 0;
$q = DB::query('SELECT * FROM ' . DB::table('redirect'));
while ($r = DB::row($q)) {
    $output .= '<tr>
        <td><code>' . $r['old'] . '</code></td>
        <td><code>' . $r['new'] . "</code></td>
        <td class='text-" . ($r['permanent'] ? 'success' : 'danger') . "'>" . _lang('global.' . ($r['permanent'] ? 'yes' : 'no')) . "</td>
        <td class='text-" . ($r['active'] ? 'success' : 'danger') . "'>" . _lang('global.' . ($r['active'] ? 'yes' : 'no')) . "</td>
        <td class='actions'>
            <a class='button' href='" . _e(Router::admin('content-redir', ['query' => ['edit' => $r['id']]])) . "'><img src='" . _e(Router::path('admin/images/icons/edit.png')) . "' alt='edit' class='icon'>" . _lang('global.edit') . "</a>
            <a class='button' href='" . _e(Xsrf::addToUrl(Router::admin('content-redir', ['query' => ['del' => $r['id']]]))) . "' onclick='return Sunlight.confirm();'><img src='" . _e(Router::path('admin/images/icons/delete.png')) . "' alt='del' class='icon'>" . _lang('global.delete') . '</a>
        </td>
    </tr>';
    ++$counter;
}

// zadna data?
if ($counter === 0) {
    $output .= "<tr><td colspan='5'>" . _lang('global.nokit') . "</td></tr>\n";
}

// konec tabulky
$output .= "</tbody>
</table>\n";
