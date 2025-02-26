<?php

use Sunlight\Captcha;
use Sunlight\Core;
use Sunlight\Database\Database as DB;
use Sunlight\Email;
use Sunlight\Extend;
use Sunlight\GenericTemplates;
use Sunlight\IpLog;
use Sunlight\Logger;
use Sunlight\Message;
use Sunlight\Router;
use Sunlight\Settings;
use Sunlight\User;
use Sunlight\Util\Form;
use Sunlight\Util\Password;
use Sunlight\Util\Request;
use Sunlight\Util\StringGenerator;

defined('SL_ROOT') or exit;

if (!Settings::get('registration')) {
    $_index->notFound();
    return;
}

if (User::isLoggedIn()) {
    $_index->redirect(Router::module('login', ['absolute' => true]));
    return;
}

$message = '';
$user_data = [];
$user_data_valid = false;
$show_form = true;
$rules = Settings::get('rules');
$confirmed = !Settings::get('registration_confirm');

// action
if (isset($_GET['confirm'])) {
    // confirmation
    $show_form = false;

    if (!Settings::get('registration_confirm')) {
        $_index->notFound();
        return;
    }

    $code = Request::get('confirm');

    if (preg_match('{[\w\\-]{48}$}AD', $code)) {
        // check IP log
        if (IpLog::check(IpLog::FAILED_ACCOUNT_ACTIVATION)) {
            // remove expired activations
            DB::delete('user_activation', 'expire<' . time());

            // load activation
            $activation = DB::queryRow('SELECT * FROM ' . DB::table('user_activation') . ' WHERE code=' . DB::val($code));

            if ($activation !== false) {
                // activation found
                $user_data = unserialize($activation['data']);

                // check user name and e-mail availability
                if (
                    User::isNameAvailable($user_data['username'])
                    && User::isEmailAvailable($user_data['email'])
                ) {
                    // all ok
                    $user_data_valid = true;
                    $confirmed = true;

                    DB::delete('user_activation', 'id=' . DB::val($activation['id']));
                } else {
                    $message .= Message::warning(_lang('mod.reg.confirm.emailornametaken'));
                }
            } else {
                IpLog::update(IpLog::FAILED_ACCOUNT_ACTIVATION);
                $message = Message::warning(_lang('mod.reg.confirm.notfound'));
            }
        } else {
            $message = Message::warning(_lang('mod.reg.confirm.limit', ['%limit%' => Settings::get('accactexpire')]));
        }
    } else {
        $message = Message::error(_lang('mod.reg.confirm.badcode'));
    }
} else {
    // process registration form
    if (!empty($_POST)) {
        $errors = [];

        // check IP log
        if (!IpLog::check(IpLog::ANTI_SPAM)) {
            $errors[] = _lang('error.antispam', ['%antispamtimeout%' => Settings::get('antispamtimeout')]);
        }

        // load and check variables
        $user_data['username'] = User::normalizeUsername(Request::post('username', ''));

        if ($user_data['username'] == '') {
            $errors[] = _lang('user.msg.badusername');
        } elseif (!User::isNameAvailable($user_data['username'])) {
            $errors[] = _lang('user.msg.userexists');
        }

        $password = Request::post('password', '');
        $password2 = Request::post('password2', '');

        if (Password::validate($password, $password2, $password_err)) {
            $user_data['password'] = Password::create($password)->build();
        } else {
            $errors[] = Password::getErrorMessage($password_err);
        }

        $user_data['email'] = trim(Request::post('email', ''));

        if (!Email::validate($user_data['email'])) {
            $errors[] = _lang('user.msg.bademail');
        }

        if (!User::isEmailAvailable($user_data['email'])) {
            $errors[] = _lang('user.msg.emailexists');
        }

        if (!Captcha::check()) {
            $errors[] = _lang('captcha.failure');
        }

        $user_data['massemail'] = Form::loadCheckbox('massemail');

        if (Settings::get('registration_grouplist') && isset($_POST['group_id'])) {
            $user_data['group_id'] = (int) Request::post('group_id');
            $groupdata = DB::query('SELECT id FROM ' . DB::table('user_group') . ' WHERE id=' . $user_data['group_id'] . ' AND blocked=0 AND reglist=1');

            if (DB::size($groupdata) == 0) {
                $errors[] = _lang('global.badinput');
            }
        } else {
            $user_data['group_id'] = Settings::get('defaultgroup');
        }

        if ($rules !== '' && !Form::loadCheckbox('agreement')) {
            $errors[] = _lang('mod.reg.rules.disagreed');
        }

        $user_data['ip'] = Core::getClientIp();

        Extend::call('mod.reg.submit', [
            'user_data' => &$user_data,
            'errors' => &$errors,
        ]);

        // validate
        if (empty($errors)) {
            IpLog::update(IpLog::ANTI_SPAM);
            $user_data_valid = true;
        } else {
            $message = Message::list($errors);
        }
    }
}

// output
$_index->title = _lang('mod.reg');

$output .= $message;

if (!$user_data_valid && $show_form) {
    // form

    // group select
    $groupselect = [];

    if (Settings::get('registration_grouplist')) {
        $groupselect_items = DB::query('SELECT id,title FROM ' . DB::table('user_group') . ' WHERE blocked=0 AND reglist=1 ORDER BY title');

        if (DB::size($groupselect_items) != 0) {
            $group_choices = DB::queryRows('SELECT id,title FROM ' . DB::table('user_group') . ' WHERE blocked=0 AND reglist=1 ORDER BY title', 'id', 'title');

            $groupselect = [
                'label' => _lang('global.group'), 
                'content' => Form::select('group_id', $group_choices, Settings::get('defaultgroup'), [], false),
            ];
        }
    }

    // rules
    if ($rules !== '') {
        $rules = [
            'content' => '<h2>' . _lang('mod.reg.rules') . '</h2>'
                . $rules
                . '<p><label>' . Form::input('checkbox', 'agreement', '1', ['checked' => Form::loadCheckbox('agreement', false, 'regform')]) . ' ' . _lang('mod.reg.rules.agreement') . '</label></p>',
            'top' => true,
        ];
    } else {
        $rules = [];
    }

    // captcha
    $captcha = Captcha::init();

    // form
    $output .= '<p class="bborder">' . _lang('mod.reg.p') . (Settings::get('registration_confirm') ? ' ' . _lang('mod.reg.confirm.extratext') : '') . "</p>\n";

    $output .= Form::render(
        [
            'name' => 'regform',
            'action' => Router::module('reg'),
        ],
        [
            ['label' => _lang('login.username'), 'content' => Form::input('text', 'username', Request::post('username'), ['class' => 'inputsmall', 'maxlength' => 24, 'autocomplete' => 'username'])],
            ['label' => _lang('login.password'), 'content' => Form::input('password', 'password', null, ['class' => 'inputsmall', 'autocomplete' => 'new-password'])],
            ['label' => _lang('login.password') . ' (' . _lang('global.check') . ')', 'content' => Form::input('password', 'password2', null, ['class' => 'inputsmall', 'autocomplete' => 'new-password'])],
            ['label' => _lang('global.email'), 'content' => Form::input('email', 'email', Request::post('email', '@'), ['class' => 'inputsmall', 'autocomplete' => 'email'])],
            ['label' => _lang('mod.settings.account.massemail'), 'content' => '<label>' . Form::input('checkbox', 'massemail', '1', ['checked' => Form::loadCheckbox('massemail', false, 'regform')]) . ' ' . _lang('mod.settings.account.massemail.label') . '</label>'],
            $groupselect,
            $captcha,
            $rules,
            Form::getSubmitRow([
                'label' => $rules ? null : '',
                'name' => 'regform',
                'text' => _lang('mod.reg.submit' . (Settings::get('registration_confirm') ? '2' : '')),
            ]),
        ]
    );
} elseif ($user_data_valid) {
    // process data
    if ($confirmed) {
        // confirmed
        $user_id = DB::insert('user', $user_data + ['registertime' => time()], true);
        Logger::notice('user', sprintf('New user account "%s" has been registered', $user_data['username']), ['user_id' => $user_id]);

        // extend
        Extend::call('user.new', ['id' => $user_id]);

        // store username for login form
        $_SESSION['login_form_username'] = $user_data['username'];

        // message
        $output .= Message::ok(_lang('mod.reg.done', ['%login_link%' => _e(Router::module('login'))]), true);
    } else {
        // send confirmation message
        $code = StringGenerator::generateString(48);
        $insert_id = DB::insert('user_activation', [
            'code' => $code,
            'expire' => time() + 3600,
            'data' => serialize($user_data),
        ], true);

        $domain = Core::getBaseUrl()->getFullHost();
        $mail = Email::send(
            $user_data['email'],
            _lang('mod.reg.confirm.subject', ['%domain%' => $domain]),
            _lang('mod.reg.confirm.text', [
                '%username%' => $user_data['username'],
                '%domain%' => $domain,
                '%confirm_link%' => Router::module('reg', ['query' => ['confirm' => $code], 'absolute' => true]),
                '%ip%' => Core::getClientIp(),
                '%date%' => GenericTemplates::renderTime(time(), 'email'),
            ])
        );

        // message
        if ($mail) {
            $output .= Message::ok(_lang('mod.reg.confirm.sent', ['%email%' => _e($user_data['email'])]), true);
        } else {
            $output .= Message::error(_lang('global.emailerror'));
            DB::delete('user_activation', 'id=' . DB::val($insert_id));
        }
    }
}
