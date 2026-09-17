<?php

namespace MJ\WPORM\WordPress;

class TermTaxonomy extends WordPressModel
{
    protected $wpdbTable = 'term_taxonomy';
    protected $primaryKey = 'term_taxonomy_id';

    public function term()
    {
        return $this->belongsTo(Term::class, 'term_id', 'term_id');
    }

    public function relationships()
    {
        return $this->hasMany(TermRelationship::class, 'term_taxonomy_id', 'term_taxonomy_id');
    }

    public function posts()
    {
        return $this->belongsToMany(
            Post::class,
            'term_relationships',
            'term_taxonomy_id',
            'object_id'
        );
    }
}
