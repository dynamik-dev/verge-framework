# Verge Framework Improvement Plan

Opportunities to simplify and clarify the framework, organized by priority.

---

## High Impact

### 1. ~~Break Up App.php~~ ✅ DONE

Extracted request handling to `RequestHandler` following the framework's own service provider pattern:

- `src/Http/RequestHandlerInterface.php` - contract
- `src/Http/RequestHandler.php` - implementation (152 LOC)
- `src/Http/HttpServiceProvider.php` - registers service
- `src/App.php` reduced from 673 → 543 LOC

---

### 2. ~~Fix CachedRouter's LSP Violation~~ ✅ DONE

Split into two separate interfaces (composition, not inheritance):

- `RouteMatcherInterface` - read operations: `match()`, `url()`, `getRoutes()`, `getNamedRoute()`
- `RouterInterface` - write operations: `add()`, `any()`, `registerNamedRoute()`, `register()`

Now:
- `Router implements RouterInterface, RouteMatcherInterface`
- `CachedRouter implements RouteMatcherInterface` (read-only, no LSP violation)
- `RequestHandler` resolves `RouteMatcherInterface` for matching
- `RoutingServiceProvider` binds both interfaces

**Files affected:** `src/Routing/RouterInterface.php`, `src/Routing/RouteMatcherInterface.php` (new), `src/Bootstrap/CachedRouter.php`, `src/Routing/Router.php`, `src/Routing/RoutingServiceProvider.php`, `src/Routing/Routes.php`, `src/Http/RequestHandler.php`, `src/App.php`

---

### 3. ~~Consolidate Container's Duplicate Resolution Logic~~ ✅ DONE

Removed duplicate `resolveCallableParameters()` method - it was identical to `resolveDependencies()`.

- `resolveDependencies()` - handles `ReflectionParameter[]` (used by both `build()` and `call()`)
- `resolveDependenciesFromCache()` - handles cached array metadata (justified, different input type)

Container reduced from 304 → 274 LOC.

**Files affected:** `src/Container.php`

---

## DX Improvements

### 4. Document Response Coercion

`handle()` silently converts return types:
- `null` → 204 No Content
- `array` → 200 JSON
- `string` → 200 text/plain
- `Stringable` → 200 text/plain
- `Response` → as-is

Users discover this by accident.

**Proposal:** Add clear documentation in CLAUDE.md under a "Response Handling" section.

**Files affected:** `CLAUDE.md`

---

### 5. Fluent Route Constraints

Current optional parameter syntax is dense: `{id?:\d+}`

**Status:** Needs rethinking. `where()` rejected as it sounds like DB query/validation magic.

**Ideas to explore:**
- `->pattern('id', '\d+')` - explicit about regex
- `->constraint('id', '\d+')` - direct naming
- `->match('id', '\d+')` - describes behavior
- Or keep inline syntax as-is

**Files affected:** `src/Routing/Route.php`, `src/Routing/PathParser.php`

---

### 6. Better Error Messages

Container throws generic `RuntimeException` without context.

**Proposal:** Custom exceptions with actionable messages:

```php
// Current
throw new \RuntimeException('Unable to resolve parameter');

// Proposed
throw new UnresolvableParameterException(
    "Cannot resolve parameter \$service in UserController::__construct(). " .
    "No binding for ServiceInterface exists. " .
    "Hint: Register it with \$app->singleton(ServiceInterface::class, ...)"
);
```

**New files:** `src/Container/UnresolvableParameterException.php`, etc.

---

## Architectural Clarity

### 7. ~~Reduce Routing File Count~~ ✅ DONE

Consolidated pure DTOs into single file: 12 → 9 files

**Before:**
- RouteMatch.php, RouteInfo.php, ParamInfo.php, RouteNotFoundException.php (separate files)

**After:**
- `Data.php` - contains RouteMatch, RouteInfo, ParamInfo, RouteNotFoundException

Used classmap in composer.json for multi-class file autoloading.

RouteGroup.php kept separate (has middleware behavior, not just a DTO).

---

### 8. Clarify Interface Policy

Current inconsistency:
| Component | Has Interface | Multiple Impls? |
|-----------|---------------|-----------------|
| Router | Yes | Yes (CachedRouter) |
| EventDispatcher | Yes | No |
| Cache | Yes | Yes (drivers) |
| Logger | Yes | Yes (drivers) |
| Env | Yes | No |

**Proposal:** Remove interfaces that don't enable swapping:
- Keep: RouterInterface, CacheInterface, LoggerInterface
- Consider removing: EventDispatcherInterface, EnvInterface

Or document the rule: "Interfaces exist for testability/mocking even without multiple implementations."

**Files affected:** Potentially `src/Events/`, `src/Env/`

---

### 9. Single Source for Named Routes

Named routes are tracked in 3 places:
- `Router::$namedRoutes`
- `CachedRouter::$named`
- `Route::$name`

**Proposal:** Router owns the name registry. CachedRouter reads from serialized cache data only. Route stores its own name but doesn't duplicate the lookup.

**Files affected:** `src/Routing/Router.php`, `src/Bootstrap/CachedRouter.php`

---

## Quick Wins

### 10. Evaluate Verge.php Static Facade

Verge.php (72 LOC) provides global singleton access:

```php
Verge::get('/path', handler);  // Instead of $app->get(...)
```

**Questions to answer:**
- Is this used in practice?
- Does it provide value beyond `app()` helper?
- Global state complicates testing

**Proposal:** If rarely used, deprecate and remove. If used, document clearly.

**Files affected:** `src/Verge.php`, `src/helpers.php`

---

### 11. Simplify helpers.php

Helpers depend on Verge facade which can throw "No application instance".

**Proposal:** Either:
- A) Make helpers stateless utilities only, OR
- B) Document the global app dependency clearly, OR
- C) Have `app()` always return an App (create if needed)

**Files affected:** `src/helpers.php`

---

## Summary

| # | Improvement | Effort | Impact | Category |
|---|-------------|--------|--------|----------|
| 1 | ~~Break up App.php~~ | ~~High~~ | ~~High~~ | ✅ Done |
| 2 | ~~Fix CachedRouter LSP~~ | ~~Medium~~ | ~~Medium~~ | ✅ Done |
| 3 | ~~Consolidate Container resolution~~ | ~~Medium~~ | ~~Medium~~ | ✅ Done |
| 4 | Document response coercion | Low | Medium | DX |
| 5 | Fluent route constraints | Medium | High | Needs rethinking |
| 6 | Better error messages | Low | Medium | DX |
| 7 | ~~Reduce routing files~~ | ~~Medium~~ | ~~Medium~~ | ✅ Done |
| 8 | Clarify interface policy | Low | Low | Architecture |
| 9 | Single named routes store | Low | Low | Architecture |
| 10 | Evaluate Verge facade | Low | Low | Simplification |
| 11 | Simplify helpers | Low | Low | DX |
