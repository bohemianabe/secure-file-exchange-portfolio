<?php

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;

class BytesToHumanTransformer implements DataTransformerInterface
{
    public function transform(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return '';
        }

        // If you expect an int/float-ish value:
        $bytes = (float) $value;

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function reverseTransform(mixed $value): mixed
    {
        // It's a readonly/display field, so don't convert back
        return null;
    }
}
