<?php

namespace MJ\WPORM\WordPress;

/**
 * Metadata for a WordPress multisite site (wp_blogmeta).
 */
class BlogMeta extends WordPressModel
{
    protected $wpdbTable = 'blogmeta';
    protected $primaryKey = 'meta_id';

    public function blog()
    {
        return $this->belongsTo(Blog::class, 'blog_id', 'blog_id');
    }
}
