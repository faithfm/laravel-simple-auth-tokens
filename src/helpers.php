<?php

/**
 * Laravel's auth() helper does not support multiple guards, so we need to create a custom helper to support it.
 * 
 * Usage: auth_guards('web,api')->user()
 *   will return the authenticated user using the first guard that was found to be authenticated.
 * 
 * Note: 
 *  - This code is essentially based on Laravel's Illuminate\Auth\Middleware\Authenticate middleware - which DOES support multiple guards.
 *  - This helper is not required if your code is wrapped in a middleware('auth:web,api') call, since the middleware overrides the default guard (by calling shouldUser($fistAuthenticatedGuard) ).
 */

if (!function_exists('auth_guards')) {
    /**
     * Returns a proxy to call any auth method across multiple guards, by finding the first authenticated guard,
     * and calling the method on that guard.
     * Accepts guards as an array or a comma-separated string.
     *
     * @param array|string $guards List of authentication guards as an array or comma-separated string.
     * @return object
     */
    function auth_guards($guards) {
        // Convert a comma-separated string to an array
        if (is_string($guards)) {
            $guards = explode(',', $guards);
        }

        // Return an object with a __call method that will call the method on the first authenticated guard
        return new class($guards) {
            private $guards;

            public function __construct(array $guards) {
                $this->guards = array_map('trim', $guards); // Ensure to remove any whitespace
            }

            public function __call($method, $parameters) {
                $firstAuthenticatedGuard = $this->findAuthenticatedGuard($this->guards);

                if ($firstAuthenticatedGuard) {
                    return auth($firstAuthenticatedGuard)->$method(...$parameters);
                } else {
                    return null;
                }
            }

            public function findAuthenticatedGuard($guards) {
                foreach ($guards as $guard) {
                    if (auth($guard)->check()) {
                        return $guard;
                    }
                }
                return null;
            }            
        };
    }
}
