# laravel-simple-auth0

![laravel-simple-auth-tokens-logo](docs/laravel-simple-auth-tokens-logo.png)

Authentication of routes using API keys (ie: *https://example.com/my/route?api_key=XXXX*) can be achieved simply using Laravel's built-in `'token'` authentication driver.

This package enables this functionality by providing a simple **migration** to add the required `api_key` field to your `users` table.



We often enable session-based (as well as token-based) authentication on our API routes, but Laravel's `StartSession` middleware creates new sessions every time a request is made from an API clients (because it typically doesn't support cookies).  This can quickly result in the creation of thousands of sessions.

To solve this issue, this package provides a replacement `StartSession` **middleware** that prevents sessions from being created when `api_key=XXXX` is detected in a given request.



### Installation + Configuration:

```bash
composer require faithfm/laravel-simple-auth-tokens
php artisan migrate              # adds 'api_key' field to 'users' table
```

Modify `Models\User.php` to include the new `api_key` field:

```diff
    protected $fillable = [
        'name',
        'email',
        'password',
+       'api_key',
    ];
```

For Laravel 8 onwards, add a token-based guard to `config/auth.php`.  (This config was included by default in prior versions.)

    'guards' => [
        ...
        'api' => [
            'driver' => 'token',
            'provider' => 'users',
            'input_key' => 'api_token',       // Default value - not strictly required
            'storage_key' => 'api_token',     // Default value - not strictly required
            'hash' => false,                  // Default value - not strictly required
        ],

For Laravel 10 and earlier,  replace the `StartSession` middleware as follows in `App\Http\Kernel.php`:

```diff
protected $middlewareGroups = [
    'web' => [
        ...
-       \Illuminate\Session\Middleware\StartSession::class,
+       \FaithFM\SimpleAuthTokens\Http\Middleware\StartSession::class,
        ...
    ],
],
```

For Laravel 11 onwards,  replace the `StartSession` middleware as follows in  `bootstrap/app.php`:

```diff
return Application::configure(basePath: dirname(__DIR__))
    ...
    ->withMiddleware(function (Middleware $middleware) {
+       $middleware->web(replace: [
+           \Illuminate\Session\Middleware\StartSession::class => \FaithFM\SimpleAuthTokens\Http\Middleware\StartSession::class,
+       ]);
    })
    ....
```

To enable session-based (as well as token-based) authentication in you API routes, you could follow our (probably-less-than-optimal) method of adding the `'web'` middleware group (in addition the default `'api'`  middleware group in `App\Providers\RouteServiceProvider.php`.  **[Untested for Laravel 11 - will be different]**

```diff
public function boot(): void
{
    ...
    $this->routes(function () {
-       // FOR LARAVEL 8
        Route::prefix('api')
-            ->middleware('api')
+            ->middleware(['web', 'api'])
            ->group(base_path('routes/api.php'));

-       // FOR LARAVEL 9 + 10
-       Route::middleware('api')
+       Route::middleware(['web', 'api'])
            ->prefix('api')
            ->group(base_path('routes/api.php'));
    ...
```



### Basic Usage:

Assuming session and token guards have been configured as `'web'` and `'api`' respectively (ie: Laravel defaults), you can use [Laravel's normal authentication](https://laravel.com/docs/master/authentication) methods as follows:

```php
$loggedIn = auth('api')->check();							// check if logged in  (using helper function)
$loggedIn = Auth::guard('api')->check();			// ditto  (using Facades)
$user = auth('api')->user();								  // get current User using helper function
$user = Auth::guard('api')->user();					  // ditto (using Facades)

// Protect routes using 'auth' middleware
Route::get(...)->middleware('auth:api')				// use token-based 'api' guard
Route::get(...)->middleware('auth:web,api')		// allow either session-based or token-based guards

// Implicit guards can be used inside protected middleware (or other contexts where Auth::shouldUse($guard) was called)
//    middleware('auth:...') -> shouldUse() --> setDefaultDriver() overwrites config('auth.defaults.guard')
$loggedIn = auth()->check();							    
$user = auth()->user();								        // ditto
```



### How It Works:

Laravel's built-in `'token'` authentication driver is:

*  Defined in `Illuminate\Auth\TokenGuard.php`
* Registered by the `createTokenDriver()` method in `Illuminate\Auth\AuthManager.php`, when the `resolve()`  method matches a driver with `$name = 'token'`.

This `'token'` driver attempts to authenticate a request using the following approach:

* Look for `'api_key=XXXX'` request parameters  (ie: "https://example.com/my/route?api_key=XXXX").
* Attempt to match the provided API key against the `api_key` field in the `users` table / (`User` model).
* Authentication is successful if a user is found with a valid matching `api_key`



Our `FaithFM\SimpleAuthTokens\Http\Middleware\StartSession` middleware works as follows:

* Detect the presence of an `api_token=XXXX` request parameter.
* Force Laravel session to use the memory-based `'array'` driver for the request  (instead persistent file/database/etc driver)
