---
title: Creating Modules
description: How to write your own modules for Verge applications
---

This guide walks through creating modules for your application. Whether you're organizing application code or building a reusable package, the patterns are the same.

## Basic Structure

A module is any callable that accepts the `App` instance:

```php
class BlogModule
{
    public function __invoke(App $app): void
    {
        // Register services
        $app->singleton(PostRepository::class, fn() => new PostRepository());

        // Register routes
        $app->get('/posts', ListPostsController::class);
        $app->get('/posts/{slug}', ShowPostController::class);
        $app->post('/posts', CreatePostController::class);
    }
}
```

Register it with your application:

```php
$app->module(BlogModule::class);
```

## File Organization

For feature domains, Verge follows a consistent structure:

```
src/Blog/
├── BlogModule.php           # Registers services and routes
├── Post.php                 # Domain model
├── PostRepository.php       # Data access
├── Controllers/
│   ├── ListPostsController.php
│   ├── ShowPostController.php
│   └── CreatePostController.php
└── Middleware/
    └── RequirePublishedPost.php
```

The module file ties everything together. The rest of the code is just regular PHP classes.

## Registering Services

Use the container methods to register services:

```php
public function __invoke(App $app): void
{
    // Singleton: one instance shared everywhere
    $app->singleton(PostRepository::class, fn() => new PostRepository(
        $app->make(Database::class)
    ));

    // Scoped: one instance per request
    $app->scoped(PostCache::class, fn() => new PostCache());

    // Transient: new instance every time
    $app->bind(PostDto::class, fn() => new PostDto());
}
```

When you don't need custom instantiation, skip the factory—the container auto-wires constructor dependencies:

```php
// Just register the class, container handles the rest
$app->singleton(PostRepository::class);
```

## Binding Interfaces

When you want to code against interfaces, bind the interface to the implementation:

```php
public function __invoke(App $app): void
{
    $app->singleton(PostRepository::class, fn() => new EloquentPostRepository());

    // Now PostRepositoryInterface resolves to the same instance
    $app->bind(PostRepositoryInterface::class, fn($app) => $app->make(PostRepository::class));
}
```

Controllers and other classes can type-hint the interface:

```php
class ListPostsController
{
    public function __construct(private PostRepositoryInterface $posts) {}
}
```

## Registering Routes

Define routes directly in the module:

```php
public function __invoke(App $app): void
{
    $app->get('/posts', ListPostsController::class);
    $app->get('/posts/{id}', ShowPostController::class);
    $app->post('/posts', CreatePostController::class)->name('posts.create');
    $app->put('/posts/{id}', UpdatePostController::class);
    $app->delete('/posts/{id}', DeletePostController::class);
}
```

Use groups for shared prefixes or middleware:

```php
public function __invoke(App $app): void
{
    $app->group('/admin', function ($admin) {
        $admin->use(AdminAuthMiddleware::class);

        $admin->get('/posts', AdminListPostsController::class);
        $admin->post('/posts', AdminCreatePostController::class);
    });
}
```

## Deferred Registration

Sometimes you need to register things after all modules have loaded. Use the `app.ready` event:

```php
public function __invoke(App $app): void
{
    $app->singleton(ApiDocGenerator::class, fn() => new ApiDocGenerator());

    $app->ready(function () use ($app) {
        // All routes are now registered
        $app->get('/api/docs', function (ApiDocGenerator $gen) use ($app) {
            return $gen->generate($app->routes());
        });
    });
}
```

The `ready()` method is shorthand for `$app->on('app.ready', ...)`. The event fires once after all modules have been loaded, but before the first request is handled.

## Using Drivers

For services with swappable backends, use the driver pattern:

```php
public function __invoke(App $app): void
{
    // Register available drivers
    $app->driver('search', 'database', fn() => new DatabaseSearchDriver());
    $app->driver('search', 'elasticsearch', fn() => new ElasticsearchDriver(
        $app->make(ElasticsearchClient::class)
    ));
    $app->driver('search', 'meilisearch', fn() => new MeilisearchDriver());

    // Set the default
    $app->defaultDriver('search', 'database');

    // Bind the interface to the active driver
    $app->singleton(SearchInterface::class, fn() => $app->driver('search'));
}
```

Users configure which driver to use via the `SEARCH_DRIVER` environment variable. Your code works with `SearchInterface` regardless of the backend.

## Event Listeners

Register listeners for application events:

```php
public function __invoke(App $app): void
{
    // Listen for specific events
    $app->on('post.published', NotifySubscribers::class);
    $app->on('post.published', UpdateSearchIndex::class);

    // Listen with wildcards
    $app->on('post.*', AuditLogger::class);

    // Inline listeners
    $app->on('post.deleted', function ($post) {
        logger()->info("Post deleted: {$post->id}");
    });
}
```

## Accepting Configuration

Modules can accept configuration through the constructor:

```php
class BlogModule
{
    public function __construct(
        private int $postsPerPage = 10,
        private bool $enableComments = true
    ) {}

    public function __invoke(App $app): void
    {
        $app->singleton(BlogConfig::class, fn() => new BlogConfig(
            postsPerPage: $this->postsPerPage,
            enableComments: $this->enableComments
        ));

        // Use config in routes, services, etc.
    }
}

// Register with custom config
$app->module(new BlogModule(postsPerPage: 20, enableComments: false));
```

Or read from the application config:

```php
public function __invoke(App $app): void
{
    $postsPerPage = $app->config('blog.posts_per_page', 10);

    $app->singleton(BlogConfig::class, fn() => new BlogConfig(
        postsPerPage: $postsPerPage
    ));
}
```

## Testing Modules

Modules are easy to test—just invoke them on a test app:

```php
it('registers blog routes', function () {
    $app = new App();
    $app->module(BlogModule::class);

    $response = $app->test()->get('/posts');

    expect($response->status())->toBe(200);
});

it('registers the post repository as singleton', function () {
    $app = new App();
    $app->module(BlogModule::class);

    $repo1 = $app->make(PostRepository::class);
    $repo2 = $app->make(PostRepository::class);

    expect($repo1)->toBe($repo2);
});
```

## Package Modules

When building a reusable package, your module is the entry point. Users install your package and register your module:

```php
// In user's application
$app->module(YourPackageModule::class);
```

Document what services, routes, and events your module provides. Consider using deferred registration for routes that inspect the application state.
