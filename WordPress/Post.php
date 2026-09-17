<?php

namespace MJ\WPORM\WordPress;

class Post extends WordPressModel
{
    protected $wpdbTable = 'posts';
    protected $primaryKey = 'ID';

    public function author()
    {
        return $this->belongsTo(User::class, 'post_author', 'ID');
    }

    public function meta()
    {
        return $this->hasMany(PostMeta::class, 'post_id', 'ID');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'comment_post_ID', 'ID');
    }

    public function termTaxonomies()
    {
        return $this->belongsToMany(
            TermTaxonomy::class,
            'term_relationships',
            'object_id',
            'term_taxonomy_id'
        );
    }
}
