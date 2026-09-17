<?php

namespace MJ\WPORM\WordPress;

/**
 * WordPress uses a composite key (object_id, term_taxonomy_id) here.
 *
 * WPORM currently supports one primary-key column per model; this model is
 * intended for relationship reads and direct queries, not identity-safe
 * updates/deletes of relationship rows.
 */
class TermRelationship extends WordPressModel
{
    protected $wpdbTable = 'term_relationships';
    protected $primaryKey = 'object_id';

    public function taxonomy()
    {
        return $this->belongsTo(
            TermTaxonomy::class,
            'term_taxonomy_id',
            'term_taxonomy_id'
        );
    }

    public function post()
    {
        return $this->belongsTo(Post::class, 'object_id', 'ID');
    }
}
