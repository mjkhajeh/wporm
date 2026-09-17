<?php

namespace MJ\WPORM\WordPress;

/**
 * A site in a WordPress multisite network (wp_blogs).
 */
class Blog extends WordPressModel
{
    protected $wpdbTable = 'blogs';
    protected $primaryKey = 'blog_id';

    public function network()
    {
        return $this->belongsTo(Site::class, 'site_id', 'id');
    }

    public function meta()
    {
        return $this->hasMany(BlogMeta::class, 'blog_id', 'blog_id');
    }
}
