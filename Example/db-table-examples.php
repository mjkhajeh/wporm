<?php
/**
 * DB::table() Examples — Default WordPress Tables
 *
 * Demonstrates how to use the WPORM DB class to query every default
 * WordPress table. Table names are resolved through $wpdb so the
 * correct prefix is always used regardless of the installation.
 *
 * NOTE: DB::table() bypasses WordPress hooks, cache, and meta APIs.
 *       For content mutations prefer wp_insert_post(), wp_update_post(),
 *       update_user_meta(), etc. Use DB::table() for reads, reports,
 *       bulk operations, or admin utilities where raw SQL is appropriate.
 */

use MJ\WPORM\DB;

// ---------------------------------------------------------------------------
// Require the ORM (adjust path to match your plugin structure)
// ---------------------------------------------------------------------------
// require_once __DIR__ . '/vendor/autoload.php';

global $wpdb;


// ===========================================================================
// 1. POSTS  —  $wpdb->posts  (wp_posts)
// ===========================================================================

// Get the 10 most recent published posts
$posts = DB::table($wpdb->posts)
    ->where('post_status', 'publish')
    ->where('post_type', 'post')
    ->orderBy('post_date', 'desc')
    ->limit(10)
    ->get();

// Get a single post by ID
$post = DB::table($wpdb->posts)
    ->where('ID', 42)
    ->first();

// Get all published pages
$pages = DB::table($wpdb->posts)
    ->where('post_status', 'publish')
    ->where('post_type', 'page')
    ->orderBy('post_title', 'asc')
    ->get();

// Get posts by multiple statuses
$draftsAndPending = DB::table($wpdb->posts)
    ->whereIn('post_status', ['draft', 'pending'])
    ->where('post_type', 'post')
    ->get();

// Count all published posts
$publishedCount = DB::table($wpdb->posts)
    ->where('post_status', 'publish')
    ->where('post_type', 'post')
    ->count();

// Bulk-move trashed posts older than 30 days to a custom status
DB::table($wpdb->posts)
    ->where('post_status', 'trash')
    ->where('post_modified', '<', date('Y-m-d H:i:s', strtotime('-30 days')))
    ->update(['post_status' => 'archived']);

// Delete all auto-draft posts
DB::table($wpdb->posts)
    ->where('post_status', 'auto-draft')
    ->delete();

// Search posts by title keyword
$results = DB::table($wpdb->posts)
    ->whereLike('post_title', '%WordPress%')
    ->where('post_status', 'publish')
    ->get();

// Posts published within a date range
$rangeResults = DB::table($wpdb->posts)
    ->whereBetween('post_date', ['2024-01-01 00:00:00', '2024-12-31 23:59:59'])
    ->where('post_status', 'publish')
    ->get();

// Select only specific columns
$titles = DB::table($wpdb->posts)
    ->select(['ID', 'post_title', 'post_date'])
    ->where('post_status', 'publish')
    ->orderBy('post_date', 'desc')
    ->limit(5)
    ->get();


// ===========================================================================
// 2. POSTMETA  —  $wpdb->postmeta  (wp_postmeta)
// ===========================================================================

// Get all meta for a specific post
$meta = DB::table($wpdb->postmeta)
    ->where('post_id', 42)
    ->get();

// Get a single meta value by key
$metaRow = DB::table($wpdb->postmeta)
    ->where('post_id', 42)
    ->where('meta_key', '_thumbnail_id')
    ->first();

// Get all posts that have a specific meta key set
$featuredImageMeta = DB::table($wpdb->postmeta)
    ->where('meta_key', '_thumbnail_id')
    ->whereNotNull('meta_value')
    ->get();

// Find posts by meta value (e.g. a custom price field)
$pricedItems = DB::table($wpdb->postmeta)
    ->where('meta_key', 'price')
    ->where('meta_value', '>', 100)
    ->get();

// Update a meta value directly (prefer update_post_meta() for cache support)
DB::table($wpdb->postmeta)
    ->where('post_id', 42)
    ->where('meta_key', 'views')
    ->update(['meta_value' => 999]);

// Delete all meta entries for a post
DB::table($wpdb->postmeta)
    ->where('post_id', 42)
    ->delete();

// Delete a specific meta key across all posts
DB::table($wpdb->postmeta)
    ->where('meta_key', '_edit_lock')
    ->delete();


// ===========================================================================
// 3. USERS  —  $wpdb->users  (wp_users)
// ===========================================================================

// Get all users ordered by registration date
$users = DB::table($wpdb->users)
    ->orderBy('user_registered', 'desc')
    ->get();

// Find a user by email
$user = DB::table($wpdb->users)
    ->where('user_email', 'hello@example.com')
    ->first();

// Find users registered after a given date
$newUsers = DB::table($wpdb->users)
    ->where('user_registered', '>=', '2024-01-01 00:00:00')
    ->orderBy('user_registered', 'desc')
    ->get();

// Find users by login name pattern
$admins = DB::table($wpdb->users)
    ->whereLike('user_login', 'admin%')
    ->get();

// Count total users
$totalUsers = DB::table($wpdb->users)
    ->count();

// Select only public-safe columns (never expose user_pass)
$publicProfiles = DB::table($wpdb->users)
    ->select(['ID', 'user_login', 'display_name', 'user_registered'])
    ->orderBy('display_name', 'asc')
    ->get();

// Update display name (prefer wp_update_user() for cache/hook support)
DB::table($wpdb->users)
    ->where('ID', 7)
    ->update(['display_name' => 'Jane Doe']);


// ===========================================================================
// 4. USERMETA  —  $wpdb->usermeta  (wp_usermeta)
// ===========================================================================

// Get all meta for a user
$userMeta = DB::table($wpdb->usermeta)
    ->where('user_id', 7)
    ->get();

// Get a specific meta key for a user
$capabilities = DB::table($wpdb->usermeta)
    ->where('user_id', 7)
    ->where('meta_key', $wpdb->prefix . 'capabilities')
    ->first();

// Find all users who have a specific meta key
$verifiedUsers = DB::table($wpdb->usermeta)
    ->where('meta_key', 'email_verified')
    ->where('meta_value', '1')
    ->get();

// Delete a meta key for a specific user
DB::table($wpdb->usermeta)
    ->where('user_id', 7)
    ->where('meta_key', 'session_tokens')
    ->delete();

// Update a meta value for a user
DB::table($wpdb->usermeta)
    ->where('user_id', 7)
    ->where('meta_key', 'last_activity')
    ->update(['meta_value' => current_time('mysql')]);


// ===========================================================================
// 5. TERMS  —  $wpdb->terms  (wp_terms)
// ===========================================================================

// Get all terms
$terms = DB::table($wpdb->terms)
    ->orderBy('name', 'asc')
    ->get();

// Find a term by slug
$term = DB::table($wpdb->terms)
    ->where('slug', 'uncategorized')
    ->first();

// Find terms with at least some usage (count > 0)
$usedTerms = DB::table($wpdb->terms)
    ->where('term_group', 0)
    ->get();

// Search terms by name
$searchTerms = DB::table($wpdb->terms)
    ->whereLike('name', '%tech%')
    ->get();

// Rename a term (prefer wp_update_term() to keep taxonomy cache consistent)
DB::table($wpdb->terms)
    ->where('term_id', 3)
    ->update(['name' => 'Technology', 'slug' => 'technology']);


// ===========================================================================
// 6. TERM_TAXONOMY  —  $wpdb->term_taxonomy  (wp_term_taxonomy)
// ===========================================================================

// Get all categories with their term data via join
$categories = DB::table($wpdb->term_taxonomy)
    ->select([
        $wpdb->term_taxonomy . '.term_taxonomy_id',
        $wpdb->term_taxonomy . '.term_id',
        $wpdb->term_taxonomy . '.count',
        $wpdb->terms . '.name',
        $wpdb->terms . '.slug',
    ])
    ->join(
        $wpdb->terms,
        $wpdb->term_taxonomy . '.term_id',
        '=',
        $wpdb->terms . '.term_id'
    )
    ->where($wpdb->term_taxonomy . '.taxonomy', 'category')
    ->orderBy($wpdb->terms . '.name', 'asc')
    ->get();

// Count posts in each category
$categoryCounts = DB::table($wpdb->term_taxonomy)
    ->select([$wpdb->term_taxonomy . '.term_id', $wpdb->term_taxonomy . '.count'])
    ->where($wpdb->term_taxonomy . '.taxonomy', 'category')
    ->orderBy($wpdb->term_taxonomy . '.count', 'desc')
    ->get();

// Get all taxonomies in use
$taxonomies = DB::table($wpdb->term_taxonomy)
    ->select(['taxonomy'])
    ->groupBy('taxonomy')
    ->get();

// Update the post count for a term (prefer wp_update_term_count() normally)
DB::table($wpdb->term_taxonomy)
    ->where('term_taxonomy_id', 5)
    ->update(['count' => 10]);


// ===========================================================================
// 7. TERM_RELATIONSHIPS  —  $wpdb->term_relationships  (wp_term_relationships)
// ===========================================================================

// Get all term_taxonomy_ids assigned to a post
$postTerms = DB::table($wpdb->term_relationships)
    ->where('object_id', 42)
    ->get();

// Get all post IDs in a specific category (term_taxonomy_id = 3)
$postIds = DB::table($wpdb->term_relationships)
    ->select(['object_id'])
    ->where('term_taxonomy_id', 3)
    ->get();

// Check if a post has a specific term assigned
$assigned = DB::table($wpdb->term_relationships)
    ->where('object_id', 42)
    ->where('term_taxonomy_id', 3)
    ->first();

// Remove all terms from a post
DB::table($wpdb->term_relationships)
    ->where('object_id', 42)
    ->delete();

// Count how many posts use a specific term
$termUsage = DB::table($wpdb->term_relationships)
    ->where('term_taxonomy_id', 3)
    ->count();


// ===========================================================================
// 8. COMMENTS  —  $wpdb->comments  (wp_comments)
// ===========================================================================

// Get approved comments for a post
$comments = DB::table($wpdb->comments)
    ->where('comment_post_ID', 42)
    ->where('comment_approved', '1')
    ->orderBy('comment_date', 'asc')
    ->get();

// Get the most recent unapproved comments (moderation queue)
$pending = DB::table($wpdb->comments)
    ->where('comment_approved', '0')
    ->orderBy('comment_date', 'desc')
    ->limit(20)
    ->get();

// Count approved comments for a post
$commentCount = DB::table($wpdb->comments)
    ->where('comment_post_ID', 42)
    ->where('comment_approved', '1')
    ->count();

// Find comments by a specific author email
$authorComments = DB::table($wpdb->comments)
    ->where('comment_author_email', 'hello@example.com')
    ->orderBy('comment_date', 'desc')
    ->get();

// Approve all pending comments from a trusted email
DB::table($wpdb->comments)
    ->where('comment_author_email', 'trusted@example.com')
    ->where('comment_approved', '0')
    ->update(['comment_approved' => '1']);

// Delete all spam comments
DB::table($wpdb->comments)
    ->where('comment_approved', 'spam')
    ->delete();

// Get top-level comments only (not replies)
$topLevel = DB::table($wpdb->comments)
    ->where('comment_post_ID', 42)
    ->where('comment_parent', 0)
    ->where('comment_approved', '1')
    ->get();

// Get replies to a specific comment
$replies = DB::table($wpdb->comments)
    ->where('comment_parent', 15)
    ->where('comment_approved', '1')
    ->orderBy('comment_date', 'asc')
    ->get();


// ===========================================================================
// 9. COMMENTMETA  —  $wpdb->commentmeta  (wp_commentmeta)
// ===========================================================================

// Get all meta for a comment
$commentMeta = DB::table($wpdb->commentmeta)
    ->where('comment_id', 15)
    ->get();

// Get a specific meta key for a comment
$rating = DB::table($wpdb->commentmeta)
    ->where('comment_id', 15)
    ->where('meta_key', 'rating')
    ->first();

// Delete all meta for a comment
DB::table($wpdb->commentmeta)
    ->where('comment_id', 15)
    ->delete();

// Update a comment meta value
DB::table($wpdb->commentmeta)
    ->where('comment_id', 15)
    ->where('meta_key', 'rating')
    ->update(['meta_value' => '5']);


// ===========================================================================
// 10. OPTIONS  —  $wpdb->options  (wp_options)
// ===========================================================================

// Get a specific option (prefer get_option() to leverage object cache)
$siteUrl = DB::table($wpdb->options)
    ->where('option_name', 'siteurl')
    ->first();

// Get multiple options at once
$coreOptions = DB::table($wpdb->options)
    ->whereIn('option_name', ['siteurl', 'blogname', 'blogdescription', 'admin_email'])
    ->get();

// Get all autoloaded options
$autoloaded = DB::table($wpdb->options)
    ->where('autoload', 'yes')
    ->orderBy('option_name', 'asc')
    ->get();

// Count total options stored
$optionCount = DB::table($wpdb->options)
    ->count();

// Find options by name pattern (e.g. all widget settings)
$widgetOptions = DB::table($wpdb->options)
    ->whereLike('option_name', 'widget_%')
    ->get();

// Find large options (useful for diagnosing database bloat)
$largeOptions = DB::table($wpdb->options)
    ->select(['option_name', 'autoload'])
    ->whereLike('option_name', '%transient%')
    ->get();

// Update an option value directly (prefer update_option() for cache support)
DB::table($wpdb->options)
    ->where('option_name', 'blogname')
    ->update(['option_value' => 'My Updated Site Name']);

// Delete expired transients
DB::table($wpdb->options)
    ->whereLike('option_name', '_transient_timeout_%')
    ->where('option_value', '<', time())
    ->delete();


// ===========================================================================
// 11. LINKS  —  $wpdb->links  (wp_links)
//     The blogroll table — present in all WP installs, rarely used today.
// ===========================================================================

// Get all visible links
$links = DB::table($wpdb->links)
    ->where('link_visible', 'Y')
    ->orderBy('link_name', 'asc')
    ->get();

// Get links in a specific category (link_category is a term_taxonomy_id)
$categoryLinks = DB::table($wpdb->links)
    ->where('link_visible', 'Y')
    ->orderBy('link_rating', 'desc')
    ->get();

// Update a link's URL
DB::table($wpdb->links)
    ->where('link_id', 1)
    ->update(['link_url' => 'https://new-url.example.com']);

// Delete a link
DB::table($wpdb->links)
    ->where('link_id', 1)
    ->delete();


// ===========================================================================
// CROSS-TABLE EXAMPLE — Join posts + postmeta + users
//
// Get published posts with their author's display name and a custom meta value,
// ordered by date. This is the kind of report query where DB::table() shines.
// ===========================================================================

$report = DB::table($wpdb->posts)
    ->select([
        $wpdb->posts . '.ID',
        $wpdb->posts . '.post_title',
        $wpdb->posts . '.post_date',
        $wpdb->users . '.display_name',
        $wpdb->postmeta . '.meta_value AS views',
    ])
    ->join(
        $wpdb->users,
        $wpdb->posts . '.post_author',
        '=',
        $wpdb->users . '.ID'
    )
    ->leftJoin(
        $wpdb->postmeta,
        $wpdb->posts . '.ID',
        '=',
        $wpdb->postmeta . '.post_id'
    )
    ->where($wpdb->posts . '.post_status', 'publish')
    ->where($wpdb->posts . '.post_type', 'post')
    ->where($wpdb->postmeta . '.meta_key', 'views')
    ->orderBy($wpdb->posts . '.post_date', 'desc')
    ->limit(10)
    ->get();

foreach ($report as $row) {
    echo $row->post_title . ' by ' . $row->display_name . ' (' . $row->views . ' views)' . PHP_EOL;
}


// ===========================================================================
// DEBUGGING — Inspect the generated SQL before executing
// ===========================================================================

$query = DB::table($wpdb->posts)
    ->where('post_status', 'publish')
    ->where('post_type', 'post')
    ->orderBy('post_date', 'desc')
    ->limit(5);

// Print SQL with placeholders
echo $query->toSql() . PHP_EOL;

// Print SQL with bindings interpolated (for copy-paste into a DB client)
echo $query->toRawSQL() . PHP_EOL;

// Dump both at once
$query->dumpSql();

// Or enable per-query logging to error_log()
$query->setDebug(true)->get();
