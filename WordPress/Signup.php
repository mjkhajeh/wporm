<?php

namespace MJ\WPORM\WordPress;

/**
 * Pending multisite signup (wp_signups).
 */
class Signup extends WordPressModel
{
    protected $wpdbTable = 'signups';
    protected $primaryKey = 'signup_id';
}
