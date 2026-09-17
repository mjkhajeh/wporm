<?php

namespace MJ\WPORM\WordPress;

use MJ\WPORM\Model;

/**
 * Base model for WordPress core tables.
 *
 * Core tables are managed by WordPress, so these models deliberately do not
 * define an up() method or attempt to create/alter their tables.
 */
abstract class WordPressModel extends Model
{
    protected $timestamps = false;
    protected $guarded = [];

    /**
     * Name of the corresponding wpdb table property.
     *
     * @var string
     */
    protected $wpdbTable;

    public function getTable()
    {
        global $wpdb;

        return $wpdb->{$this->wpdbTable};
    }
}
