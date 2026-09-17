<?php

namespace MJ\WPORM\WordPress;

/**
 * Metadata for a WordPress multisite network (wp_sitemeta).
 */
class SiteMeta extends WordPressModel
{
    protected $wpdbTable = 'sitemeta';
    protected $primaryKey = 'meta_id';

    public function network()
    {
        return $this->belongsTo(Site::class, 'site_id', 'id');
    }
}
