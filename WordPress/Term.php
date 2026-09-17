<?php

namespace MJ\WPORM\WordPress;

class Term extends WordPressModel
{
    protected $wpdbTable = 'terms';
    protected $primaryKey = 'term_id';

    public function taxonomies()
    {
        return $this->hasMany(TermTaxonomy::class, 'term_id', 'term_id');
    }

    public function meta()
    {
        return $this->hasMany(TermMeta::class, 'term_id', 'term_id');
    }
}
