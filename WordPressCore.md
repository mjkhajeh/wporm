# WordPress Core Models

WPORM includes read/write model classes for the standard WordPress database
tables. They are in the `MJ\WPORM\WordPress` namespace and use the active
WordPress `$wpdb` object to resolve table names.

These models are intended for plugins and themes that already load WordPress
and WPORM's Composer autoloader:

```php
require_once __DIR__ . '/vendor/autoload.php';

use MJ\WPORM\WordPress\Post;

$posts = Post::where('post_status', 'publish')->get();
```

## Important behavior

### No schema management

Core models do not define `up()` methods. They never create, alter, or
initialize WordPress tables. WordPress owns the schema and migrations for
these tables.

The shared `WordPressModel` base class:

- disables WPORM timestamps, because WordPress tables do not use WPORM's
  `created_at` and `updated_at` convention;
- allows the model's database attributes to be filled;
- resolves the table through a `$wpdb` property such as `$wpdb->posts` or
  `$wpdb->users`.

### Table prefixes and multisite

Using `$wpdb` properties is important because table names depend on the
installation and the current blog. Site-specific tables resolve to the
currently selected site:

```php
switch_to_blog($blogId);

try {
    $posts = \MJ\WPORM\WordPress\Post::where('post_type', 'page')->get();
} finally {
    restore_current_blog();
}
```

Always restore the original blog in a `finally` block. Do not cache a
site-specific model query builder and reuse it after switching blogs: the
builder contains the table name selected when it was created.

`User`, `UserMeta`, `Site`, `SiteMeta`, `Blog`, `BlogMeta`, `Signup`, and
`RegistrationLog` use network-level `$wpdb` tables. `Post`, `PostMeta`,
`Comment`, `CommentMeta`, `Term`, `TermTaxonomy`, `TermRelationship`,
`TermMeta`, `Option`, and `Link` use the current site's tables.

## Model reference

All classes below extend `WordPressModel`. The **primary key** column is the
key WPORM uses for `find()`, `belongsTo()`, and other identity-based
operations.

| Model | WordPress table | `$wpdb` property | Primary key | Scope |
|---|---|---|---|---|
| `Post` | `wp_posts` | `posts` | `ID` | Current site |
| `PostMeta` | `wp_postmeta` | `postmeta` | `meta_id` | Current site |
| `User` | `wp_users` | `users` | `ID` | Network |
| `UserMeta` | `wp_usermeta` | `usermeta` | `umeta_id` | Network |
| `Comment` | `wp_comments` | `comments` | `comment_ID` | Current site |
| `CommentMeta` | `wp_commentmeta` | `commentmeta` | `meta_id` | Current site |
| `Term` | `wp_terms` | `terms` | `term_id` | Current site |
| `TermTaxonomy` | `wp_term_taxonomy` | `term_taxonomy` | `term_taxonomy_id` | Current site |
| `TermRelationship` | `wp_term_relationships` | `term_relationships` | Composite | Current site |
| `TermMeta` | `wp_termmeta` | `termmeta` | `meta_id` | Current site |
| `Option` | `wp_options` | `options` | `option_id` | Current site |
| `Link` | `wp_links` | `links` | `link_id` | Current site |
| `Blog` | `wp_blogs` | `blogs` | `blog_id` | Network |
| `BlogMeta` | `wp_blogmeta` | `blogmeta` | `meta_id` | Network |
| `Site` | `wp_site` | `site` | `id` | Network |
| `SiteMeta` | `wp_sitemeta` | `sitemeta` | `meta_id` | Network |
| `Signup` | `wp_signups` | `signups` | `signup_id` | Network |
| `RegistrationLog` | `wp_registration_log` | `registration_log` | `ID` | Network |

The `wp_` prefix in the table examples is illustrative. The actual prefix is
resolved from `$wpdb`.

## Posts

### `Post`

Represents `wp_posts`. Its primary key is `ID`.

Relations:

- `author()` → `User` through `post_author` → `ID`
- `meta()` → `PostMeta` through `post_id` → `ID`
- `comments()` → `Comment` through `comment_post_ID` → `ID`
- `termTaxonomies()` → `TermTaxonomy` through the
  `term_relationships` pivot table

```php
use MJ\WPORM\WordPress\Post;

$post = Post::with(['author', 'meta', 'comments'])
    ->where('post_status', 'publish')
    ->where('post_type', 'post')
    ->first();

echo $post->post_title;
echo $post->author->display_name;
foreach ($post->comments as $comment) {
    echo $comment->comment_content;
}
```

### `PostMeta`

Represents `wp_postmeta`. Its primary key is `meta_id`.

Relation:

- `post()` → `Post` through `post_id` → `ID`

```php
use MJ\WPORM\WordPress\PostMeta;

$featured = PostMeta::where('meta_key', '_thumbnail_id')->get();
$post = $featured->first()->post;
```

WordPress stores metadata values as strings. Cast or decode `meta_value`
according to the metadata convention used by the plugin that created it.

## Users

### `User`

Represents `wp_users`. Its primary key is `ID`.

Relations:

- `posts()` → `Post` through `post_author` → `ID`
- `comments()` → `Comment` through `user_id` → `ID`
- `meta()` → `UserMeta` through `user_id` → `ID`

```php
use MJ\WPORM\WordPress\User;

$authors = User::withCount('posts')
    ->where('user_status', 0)
    ->get();
```

### `UserMeta`

Represents `wp_usermeta`. Its primary key is `umeta_id`.

Relation:

- `user()` → `User` through `user_id` → `ID`

`wp_users` and `wp_usermeta` are network-level tables in multisite. User
metadata is shared across the network even when it is used by one site.

## Comments

### `Comment`

Represents `wp_comments`. Its primary key is `comment_ID`.

Relations:

- `post()` → `Post` through `comment_post_ID` → `ID`
- `author()` → `User` through `user_id` → `ID`
- `meta()` → `CommentMeta` through `comment_id` → `comment_ID`
- `parent()` → another `Comment` through `comment_parent` → `comment_ID`
- `replies()` → child `Comment` records through `comment_parent`

```php
use MJ\WPORM\WordPress\Comment;

$thread = Comment::with(['author', 'replies'])
    ->where('comment_post_ID', $postId)
    ->where('comment_parent', 0)
    ->get();
```

### `CommentMeta`

Represents `wp_commentmeta`. Its primary key is `meta_id`.

Relation:

- `comment()` → `Comment` through `comment_id` → `comment_ID`

## Terms and taxonomies

### `Term`

Represents `wp_terms`. Its primary key is `term_id`.

Relations:

- `taxonomies()` → `TermTaxonomy` through `term_id`
- `meta()` → `TermMeta` through `term_id`

### `TermTaxonomy`

Represents `wp_term_taxonomy`. Its primary key is `term_taxonomy_id`.

Relations:

- `term()` → `Term` through `term_id`
- `relationships()` → `TermRelationship` through `term_taxonomy_id`
- `posts()` → `Post` through the `term_relationships` pivot table

The `taxonomy` column belongs to `TermTaxonomy`, not `Term`. A term can have
different taxonomy records, so filter taxonomy queries on that model:

```php
use MJ\WPORM\WordPress\TermTaxonomy;

$categories = TermTaxonomy::with('term')
    ->where('taxonomy', 'category')
    ->get();
```

### `TermRelationship`

Represents `wp_term_relationships`, which connects a post-like object to a
term taxonomy. It has no single primary key: WordPress identifies rows with
the pair `(object_id, term_taxonomy_id)`.

Relations:

- `taxonomy()` → `TermTaxonomy` through `term_taxonomy_id`
- `post()` → `Post` through `object_id` → `ID`

WPORM supports one primary-key column per model. Therefore
`TermRelationship` is suitable for reads and relationship queries, but
identity-safe `find()`, updates, and deletes for a composite row should use
both columns in an explicit query:

```php
use MJ\WPORM\WordPress\TermRelationship;

$relationship = TermRelationship::where('object_id', $postId)
    ->where('term_taxonomy_id', $taxonomyId)
    ->first();
```

### `TermMeta`

Represents `wp_termmeta`. Its primary key is `meta_id`.

Relation:

- `term()` → `Term` through `term_id`

## Options and links

### `Option`

Represents the current site's `wp_options` table. Its primary key is
`option_id`. Options have no model relations.

```php
use MJ\WPORM\WordPress\Option;

$option = Option::where('option_name', 'my_plugin_settings')->first();
$settings = $option ? maybe_unserialize($option->option_value) : [];
```

For normal option operations, prefer `get_option()`, `update_option()`, and
related WordPress APIs because they handle serialization, caching, and hooks.

### `Link`

Represents the legacy current site's `wp_links` table. Its primary key is
`link_id`. It has no model relations. The table may be absent or unused on
modern WordPress installations.

## Multisite network models

### `Blog`

Represents `wp_blogs`, one row per site in a multisite network. Its primary
key is `blog_id`.

Relations:

- `network()` → `Site` through `site_id` → `id`
- `meta()` → `BlogMeta` through `blog_id`

### `BlogMeta`

Represents `wp_blogmeta`. Its primary key is `meta_id`.

Relation:

- `blog()` → `Blog` through `blog_id`

### `Site`

Represents `wp_site`, the multisite network record. Its primary key is `id`.

Relations:

- `blogs()` → `Blog` through `site_id`
- `meta()` → `SiteMeta` through `site_id`

### `SiteMeta`

Represents `wp_sitemeta`. Its primary key is `meta_id`.

Relation:

- `network()` → `Site` through `site_id` → `id`

### `Signup`

Represents pending multisite registrations in `wp_signups`. Its primary key
is `signup_id`. It has no model relations.

### `RegistrationLog`

Represents the multisite registration log in `wp_registration_log`. Its
primary key is `ID`. It has no model relations.

## Eager loading and relation counts

All supported relation methods can be eager-loaded with `with()` and counted
with `withCount()`:

```php
use MJ\WPORM\WordPress\Post;

$posts = Post::with([
    'author',
    'comments.author',
    'termTaxonomies.term',
])->withCount(['comments', 'termTaxonomies'])->get();

foreach ($posts as $post) {
    echo $post->post_title . ': ' . $post->comments_count;
}
```

Nested relation support depends on the normal WPORM eager-loading API. Relation
queries are still ordinary `QueryBuilder` instances, so `where()`,
`orderBy()`, `select()`, and other query methods can be chained.

## Relations with custom models

Core models can relate to plugin or theme models exactly like any other
WPORM model. The tables do not need database-level foreign keys; the
relationship is defined by matching column values.

### Custom table belongs to a WordPress post

```php
namespace Acme\Catalog;

use MJ\WPORM\Model;
use MJ\WPORM\WordPress\Post;

class Product extends Model
{
    protected $table = 'acme_products';
    protected $timestamps = false;

    public function post()
    {
        return $this->belongsTo(Post::class, 'post_id', 'ID');
    }
}
```

Use it from the WordPress model by defining the inverse relation in a
subclass, or by querying the custom model directly:

```php
class ProductPost extends Post
{
    public function product()
    {
        return $this->hasOne(Product::class, 'post_id', 'ID');
    }
}

$post = ProductPost::with('product')->find($postId);
```

### Custom table belongs to a WordPress user

```php
namespace Acme\Members;

use MJ\WPORM\Model;
use MJ\WPORM\WordPress\User;

class Profile extends Model
{
    protected $table = 'acme_profiles';
    protected $timestamps = false;

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'ID');
    }
}

$profile = Profile::with('user')->find($profileId);
```

### Custom model extending a core model

Extending a core model is useful when a plugin wants application-specific
relations or accessors while retaining the WordPress table and primary key:

```php
use Acme\Catalog\Product;
use MJ\WPORM\WordPress\Post;

class ProductPost extends Post
{
    public function product()
    {
        return $this->hasOne(Product::class, 'post_id', 'ID');
    }
}
```

Ensure the relation direction and key arguments match the actual columns in
the custom table. A plugin table named `acme_products` is automatically
prefixed by normal `Model::getTable()` behavior when `protected $table =
'acme_products'` is used.

## WordPress API and direct writes

These models use `$wpdb` through WPORM and are not a replacement for
WordPress's domain APIs. For writes to posts, users, terms, comments,
metadata, options, and multisite records, prefer the corresponding WordPress
functions when available. Those APIs handle hooks, object caches,
serialization, capability-related behavior, and other WordPress invariants.

Use the models for relational reads, reporting, controlled maintenance, and
plugin-specific workflows where direct database behavior is intentional.
