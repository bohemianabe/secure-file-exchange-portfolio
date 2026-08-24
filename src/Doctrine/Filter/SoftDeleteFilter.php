<?php

namespace App\Doctrine\Filter;
// src/Doctrine/Filter/SoftDeleteFilter.php
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

// ag: created this filter for the soft delete feature used by admin on deleting firms. 
final class SoftDeleteFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $meta, $alias): string
    {
        if (!$meta->hasField('deletedAt')) {
            return '';
        }
        return sprintf('%s.deleted_at IS NULL', $alias);
    }
}
