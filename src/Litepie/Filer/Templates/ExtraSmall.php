<?php

namespace Litepie\Filer\Templates;

use Intervention\Image\Image;
use Intervention\Image\Filters\FilterInterface;

class ExtraSmall implements FilterInterface
{
    public function applyFilter(Image $image)
    {
        $action = config('filer.size.xs.action', 'fit');
        $width  = config('filer.size.xs.width', 80);
        $height = config('filer.size.xs.height', 60);

        if ($action == 'resize') {
            $image->resize($width, $height);
        } else {
            $image->fit($width, $height, function ($constraint) {
                $constraint->upsize();
            });
        }

        if (!empty(config('filer.size.xs.watermark'))) {
            $image->insert(config('filer.size.xs.watermark'), 'center');
        }

        return $image;
    }
}