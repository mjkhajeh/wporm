<?php

namespace MJ\WPORM\WordPress;

/**
 * Multisite user/site registration log (wp_registration_log).
 */
class RegistrationLog extends WordPressModel
{
    protected $wpdbTable = 'registration_log';
    protected $primaryKey = 'ID';
}
