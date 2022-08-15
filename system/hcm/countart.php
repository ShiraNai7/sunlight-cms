<?php

use Sunlight\Article;
use Sunlight\Database\Database as DB;
use Sunlight\Util\Arr;

return function ($kategorie = null) {
    if (!empty($kategorie)) {
        $kategorie = Arr::removeValue(explode('-', $kategorie), '');
    } else {
        $kategorie = [];
    }

    [$joins, $cond] = Article::createFilter('art', $kategorie);

    return DB::result(DB::query('SELECT COUNT(*) FROM ' . DB::table('article') . ' AS art ' . $joins . ' WHERE ' . $cond));
};
