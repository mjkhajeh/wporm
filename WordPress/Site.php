<?php

namespace MJ\WPORM\WordPress;

/**
 * A WordPress multisite network (wp_site).
 */
class Site extends WordPressModel
{
    protected $wpdbTable = 'site';
    protected $primaryKey = 'id';

    public function blogs()
    {
        return $this->hasMany(Blog::class, 'site_id', 'id');
    }

    public function meta()
    {
        return $this->hasMany(SiteMeta::class, 'site_id', 'id');
    }
}
