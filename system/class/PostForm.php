<?php

namespace Sunlight;

abstract class PostForm
{
    /**
     * Sestavit kod ovladaciho panelu
     *
     * @param string $form nazev formulare
     * @param string $area nazev textarey
     * @param bool $bbcode zobrazit BBCode 1/0
     */
    static function renderControls(string $form, string $area, bool $bbcode = true): string
    {
        $output = '';

        Extend::call('post_form.controls.before', [
            'form' => $form,
            'area' => $area,
            'bbcode' => &$bbcode,
            'output' => &$output,
        ]);

        // bbcode
        if ($bbcode && Settings::get('bbcode') && Template::getCurrent()->getOption('bbcode.buttons')) {
            // nacteni tagu
            $bbtags = Bbcode::getTags();

            // pridani kodu
            $output .= '<span class="post-form-bbcode">';
            foreach ($bbtags as $tag => $vars) {
                if (!isset($vars[4])) {
                    // tag bez tlacitka
                    continue;
                }
                $icon = (($vars[4] === 1) ? Template::image('bbcode/' . $tag . '.png') : $vars[4]);
                $output .= '<a class="bbcode-button post-form-bbcode-' . $tag . '" href="#" onclick="return Sunlight.addBBCode(\'' . $form . '\',\'' . $area . '\',\'' . $tag . '\', ' . ($vars[0] ? 'true' : 'false') . ');" class="bbcode-button"><img src="' . $icon . '" alt="' . $tag . "\"></a>\n";
            }
            $output .= '</span>';
        }

        Extend::call('post_form.controls.after', [
            'form' => $form,
            'area' => $area,
            'bbcode' => $bbcode,
            'output' => &$output,
        ]);

        if ($output !== '') {
            $output = '<span class="posts-form-controls">' . $output . '</span>';
        }

        return $output;
    }

    /**
     * Sestavit kod tlacitka pro nahled prispevku
     *
     * @param string $form nazev formulare
     * @param string $area nazev textarey
     */
    static function renderPreviewButton(string $form, string $area): string
    {
        return '<button class="post-form-preview" onclick="Sunlight.postPreview(this, \'' . $form . '\', \'' . $area . '\'); return false;">' . _lang('global.preview') . '</button>';
    }
}
