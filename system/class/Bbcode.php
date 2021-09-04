<?php

namespace Sunlight;

use Sunlight\Util\UrlHelper;

abstract class Bbcode
{
    /**
     * Entry format:
     *
     *      array(
     *           (bool) is pair tag
     *           (bool) has argument
     *           (bool) is nestable
     *           (bool) parse children
     *           (null|int|string) button icon (null = none, 1 = template, string = custom path)
     *      )
     */
    private static $tags = [
        'b' => [true, false, true, true, 1], // bold
        'i' => [true, false, true, true, 1], // italic
        'u' => [true, false, true, true, 1], // underline
        'q' => [true, false, true, true, null], // quote
        's' => [true, false, true, true, 1], // strike
        'img' => [true, true, false, false, 1], // image
        'code' => [true, true, false, true, 1], // code
        'c' => [true, false, true, true, null], // inline code
        'url' => [true, true, true, true, 1], // link
        'hr' => [false, false, false, false, 1], // horizontal rule
        'color' => [true, true, true, true, null], // color
        'size' => [true, true, true, true, null], // size
        'noformat' => [true, false, true, false, null], // no format
    ];

    private static $extended = false;

    /**
     * Get known BBCode tags
     *
     * @return array
     */
    static function getTags(): array
    {
        self::$extended || self::extendTags();

        return self::$tags;
    }

    /**
     * Parse BBCode tags in string
     *
     * @param string $s input string (HTML)
     * @return string
     */
    static function parse(string $s): string
    {
        self::$extended || self::extendTags();

        // prepare
        $mode = 0;
        $submode = 0;
        $closing = false;
        $parents = []; // 0 = tag, 1 = arg, 2 = buffer
        $parents_n = -1;
        $tag = '';
        $output = '';
        $buffer = '';
        $arg = '';
        $reset = 0;

        // scan
        for ($i = 0; isset($s[$i]); ++$i) {

            // get char
            $char = $s[$i];

            // mode step
            switch ($mode) {

                ########## look for tag ##########
                case 0:
                    if ($char === '[') {
                        $mode = 1;
                        if ($parents_n === -1) {
                            $output .= $buffer;
                        }
                        else {
                            $parents[$parents_n][2] .= $buffer;
                        }
                        $buffer = '';
                    }
                    break;

                ########## scan tag ##########
                case 1:
                    if (($ord = ord($char)) > 47 && $ord < 59 || $ord > 64 && $ord < 91 || $ord > 96 && $ord < 123) {
                        // tag character
                        $tag .= $char;
                    } elseif ($tag === '' && $char === '/') {
                        // closing tag
                        $closing = true;
                        break;
                    } elseif ($char === ']') {
                        // tag end
                        $tag = mb_strtolower($tag);
                        if (isset(self::$tags[$tag])) {
                            if ($parents_n === -1 || self::$tags[$tag][2] || self::$tags[$tag][0] && $closing) {
                                if (self::$tags[$tag][0]) {
                                    // paired tag
                                    if ($closing) {
                                        if ($parents_n === -1 || $parents[$parents_n][0] !== $tag) {
                                            // reset - invalid closing tag
                                            $reset = 2;
                                        } else {
                                            --$parents_n;
                                            $pop = array_pop($parents);
                                            $buffer = self::processTag($pop[0], $pop[1], $pop[2]);
                                            if ($parents_n === -1) {
                                                $output .= $buffer;
                                            } else {
                                                $parents[$parents_n][2] .= $buffer;
                                            }
                                            $reset = 1;
                                            $char = '';
                                        }
                                    } elseif ($parents_n === -1 || self::$tags[$parents[$parents_n][0]][3]) {
                                        // opening tag
                                        $parents[] = [$tag, $arg, ''];
                                        ++$parents_n;
                                        $buffer = '';
                                        $char = '';
                                        $reset = 1;
                                    } else {
                                        // reset - disallowed children
                                        $reset = 7;
                                    }
                                } else {
                                    // standalone tag
                                    $buffer = self::processTag($tag, $arg);
                                    if ($parents_n === -1) {
                                        $output .= $buffer;
                                    } else {
                                        $parents[$parents_n][2] .= $buffer;
                                    }
                                    $reset = 1;
                                }
                            } else {
                                // reset - disallowed nesting
                                $reset = 3;
                            }
                        } else {
                            // reset - bad tag
                            $reset = 4;
                        }
                    } elseif ($char === '=') {
                        if (isset(self::$tags[$tag]) && self::$tags[$tag][1] === true && $arg === '' && !$closing) {
                            $mode = 2; // scan tag argument
                        } else {
                            // reset - bad / no argument
                            $reset = 5;
                        }
                    } else {
                        // reset - invalid character
                        $reset = 8;
                    }
                    break;

                ########## scan tag argument ##########
                case 2:

                    // detect submode
                    if ($submode === 0) {
                        if ($char === '"') {
                            // quoted mode
                            $submode = 1;
                            break;
                        }

                        // unquoted mode
                        $submode = 2;
                    }

                    // gather argument
                    if ($submode === 1) {
                        if ($char !== '"') {
                            // char ok
                            $arg .= $char;
                            break;
                        }
                    } elseif ($char !== ']') {
                        // char ok
                        $arg .= $char;
                        break;
                    }

                    // end
                    if ($submode === 2) {
                        // end of unquoted
                        $mode = 1;
                        $char = '';
                        --$i;
                    } elseif (isset($s[$i + 1]) && $s[$i + 1] === ']') {
                        $mode = 1;
                    } else {
                        // reset - bad syntax
                        $reset = 6;
                    }

                    break;

            }

            // buffer char
            $buffer .= $char;

            // reset
            if ($reset !== 0) {
                if ($reset > 1) {
                    if ($parents_n === -1) {
                        $output .= $buffer;
                    } else {
                        $parents[$parents_n][2] .= $buffer;
                    }
                }
                $buffer = '';
                $reset = 0;
                $mode = 0;
                $submode = 0;
                $closing = false;
                $tag = '';
                $arg = '';
            }

        }

        // flush remaining parents and buffer
        if ($parents_n !== -1) {
            for($i = 0; isset($parents[$i]); ++$i) {
                $output .= $parents[$i][2];
            }
        }
        $output .= $buffer;

        // return output
        return $output;
    }

    private static function processTag(string $tag, string $arg = '', ?string $buffer = null): string
    {
        // load extend tag processors
        static $ext = null;
        if (!isset($ext)) {
            $ext = [];
            Extend::call('bbcode.init.proc', ['tags' => &$ext]);
        }

        // process
        if (isset($ext[$tag])) {
            return $ext[$tag]($arg, $buffer);
        }
        switch ($tag) {
            case 'b':
                if ($buffer !== '') {
                    return '<strong>' . $buffer . '</strong>';
                }
                break;

            case 'i':
                if ($buffer !== '') {
                    return '<em>' . $buffer . '</em>';
                }
                break;

            case 'u':
                if ($buffer !== '') {
                    return '<u>' . $buffer . '</u>';
                }
                break;

            case 'q':
                if ($buffer !== '') {
                    return '<q>' . $buffer . '</q>';
                }
                break;

            case 's':
                if ($buffer !== '') {
                    return '<del>' . $buffer . '</del>';
                }
                break;

            case 'code':
                if ($buffer !== '') {
                    return '<span class="pre">' . str_replace(' ', '&nbsp;', $buffer) . '</span>';
                }
                break;

            case 'c':
                if ($buffer !== '') {
                    return '<code>' . $buffer . '</code>';
                }
                break;

            case 'url':
                if ($buffer !== '') {
                    $url = trim($arg !== '' ? $arg : $buffer);
                    $url = UrlHelper::isSafe($url) ? UrlHelper::addScheme($url) : '#';

                    return '<a href="' . $url . '" rel="nofollow noopener ugc" target="_blank">' . $buffer . '</a>';
                }
                break;

            case 'hr':
                return '<span class="hr"></span>';

            case 'color':
                static $colors = ['aqua' => 0, 'black' => 1, 'blue' => 2, 'fuchsia' => 3, 'gray' => 4, 'green' => 5, 'lime' => 6, 'maroon' => 7, 'navy' => 8, 'olive' => 9, 'orange' => 10, 'purple' => 11, 'red' => 12, 'silver' => 13, 'teal' => 14, 'white' => 15, 'yellow' => 16];
                if ($buffer !== '') {
                    if (preg_match('{#[0-9A-Fa-f]{3,6}$}AD', $arg) !== 1) {
                        $arg = mb_strtolower($arg);
                        if (!isset($colors[$arg])) {
                            return $buffer;
                        }
                    }

                    return '<span style="color:' . $arg . ';">' . $buffer . '</span>';
                }
                break;

            case 'size':
                if ($buffer !== '') {
                    $arg = (int) $arg;
                    if ($arg < 1 || $arg > 8) {
                        return $buffer;
                    }
                    return '<span style="font-size:' . round((0.5 + ($arg / 6)) * 100) . '%;">' . $buffer . '</span>';
                }
                break;

            case 'img':
                $buffer = trim($buffer);
                if ($buffer !== '' && UrlHelper::isSafe($buffer)) {
                    $src = UrlHelper::ensureValidScheme($buffer);
                    $link = ($arg !== '' && UrlHelper::isSafe($arg)) ? UrlHelper::addScheme($arg) : $src;

                    return '<a href="' . $link . '" rel="nofollow noopener ugc" target="_blank">'
                        . '<img src="' . $src . '" alt="img" class="bbcode-img">'
                        . '</a>';
                }
                break;

            case 'noformat':
                return $buffer;
        }

        return '';
    }

    private static function extendTags(): void
    {
        Extend::call('bbcode.init.tags', ['tags' => &self::$tags]);
        self::$extended = true;
    }
}
