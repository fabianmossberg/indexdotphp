# Changelog

## [1.2.0](https://github.com/fabianmossberg/indexdotphp/compare/v1.1.0...v1.2.0) (2026-04-29)


### Features

* **response:** error accessors and Response::text() helper ([#9](https://github.com/fabianmossberg/indexdotphp/issues/9)) ([3cd1a02](https://github.com/fabianmossberg/indexdotphp/commit/3cd1a02d42b27f426d58b2856a3021db0d3f3709))

## [1.1.0](https://github.com/fabianmossberg/indexdotphp/compare/v1.0.1...v1.1.0) (2026-04-29)


### Features

* **response:** add Response::html() and Response::json() helpers ([#7](https://github.com/fabianmossberg/indexdotphp/issues/7)) ([5ed36b5](https://github.com/fabianmossberg/indexdotphp/commit/5ed36b58fa1a965a1a8fcd8a244ec2dcd487d8f5))
* **router:** allow onError to register a default error handler ([#5](https://github.com/fabianmossberg/indexdotphp/issues/5)) ([5a20a50](https://github.com/fabianmossberg/indexdotphp/commit/5a20a50fbf35e852481337e200be77ac98d1a793))

## [1.0.1](https://github.com/fabianmossberg/indexdotphp/compare/v1.0.0...v1.0.1) (2026-04-29)


### Bug Fixes

* code-review cleanup (redirect body, query arrays, input validation) ([#2](https://github.com/fabianmossberg/indexdotphp/issues/2)) ([cfb4584](https://github.com/fabianmossberg/indexdotphp/commit/cfb45842fbdb5214eea96d6e9e0fce07641ca63b))

## [1.0.0](https://github.com/fabianmossberg/indexdotphp/compare/v0.0.1...v1.0.0) (2026-04-29)


### ⚠ BREAKING CHANGES

* routes registered with `decode: ['x' => 'string']` will throw `LogicException: Unknown decoder: string`. Migration: either drop the decode entry entirely (path params are strings by default) or register the desired validator via registerDecoder().
* withMessage() on a response with status >= 400 now throws. Migration: pass the message via Response::error()'s second argument, or remove the call.
* callers relying on bodyJson() returning null for malformed bodies must now catch JsonException.
* callers using Router::any() must rename to
* the JSON shape of every >=400 response changes from `{"data":null,"message":["..."]}` to `{"data":null,"error":{"status":N,"code":"X","message":"..."}}`. Callers parsing the message[] array on errors must read error.message instead.
* minimum PHP version is now 8.3.
* every JSON response shape changes from `{"items": ...}` to `{"data": ...}`. Callers using the fluent `withItems()` setter must rename to `withData()`.

### Features

* add 'validate' route option for pre-handler input validation ([fbdab96](https://github.com/fabianmossberg/indexdotphp/commit/fbdab960ad130467a0e07918dfce82f269fd735f))
* add content negotiation (accepts, preferredContentType) ([94914fd](https://github.com/fabianmossberg/indexdotphp/commit/94914fd2c62ad0417a187a12393e80e0d9b0bae3))
* add queryCsv, queryCsvInts, queryCsvStrings on Request ([0a9a02a](https://github.com/fabianmossberg/indexdotphp/commit/0a9a02ae63c06f7af35c1fe9de85ae0dc71ef8d4))
* add Response::withoutHeader and Router::stripHeaders for header removal ([78e94d5](https://github.com/fabianmossberg/indexdotphp/commit/78e94d5e7892646e7220c0c05db9138429b1d1fc))
* add Router::url() reverse routing for named routes ([11a190c](https://github.com/fabianmossberg/indexdotphp/commit/11a190c33eb357cc1505f162eaafd38e099cdfec))
* add Timing::measure() for per-span Server-Timing entries ([b956111](https://github.com/fabianmossberg/indexdotphp/commit/b9561117fd8542bd0e9f21f82a79e4d560394f95))
* auto-handle OPTIONS and HEAD requests ([fdce6e0](https://github.com/fabianmossberg/indexdotphp/commit/fdce6e088bc6c94e80f1a11952051151160684a9))
* rename envelope key items -&gt; data and add Response::raw() factory ([5fa0632](https://github.com/fabianmossberg/indexdotphp/commit/5fa0632833d988b4178ec4c66e2458631e32e99a))
* rework error response envelope with status, code, and message ([f368097](https://github.com/fabianmossberg/indexdotphp/commit/f368097915250390fd914176e21b6faf14ea153f))
* Router::defaultHeaders() for headers applied to every response ([fe5821a](https://github.com/fabianmossberg/indexdotphp/commit/fe5821a20c81e7c89ee4ce5ee0a08eefe060976e))


### Bug Fixes

* bodyJson() throws JsonException on malformed JSON ([733a5d9](https://github.com/fabianmossberg/indexdotphp/commit/733a5d945945dbd4a48646e49555469e30ff0e71))
* Response::error() rejects status below 400 ([f2bb65c](https://github.com/fabianmossberg/indexdotphp/commit/f2bb65cbacf211ee4659e8cbce872d88b95edd6c))
* run global middleware on 404, 405, and OPTIONS responses ([b8c9fab](https://github.com/fabianmossberg/indexdotphp/commit/b8c9fab5a7884e69e203fd689d64962ce5c777a5))
* withMessage() throws on error responses ([0f05cb5](https://github.com/fabianmossberg/indexdotphp/commit/0f05cb520b0c0bc1501623b798aa8bf93ea78f58))


### Miscellaneous Chores

* drop PHP 8.1 and 8.2 support, require ^8.3 ([9d38cfd](https://github.com/fabianmossberg/indexdotphp/commit/9d38cfd4da4403ac963caf4ae7cb23cd8ebfd6d6))


### Code Refactoring

* drop the no-op 'string' built-in decoder ([fc5acac](https://github.com/fabianmossberg/indexdotphp/commit/fc5acacd85335fd55fa2d99edf3db6814b3fada7))
* rename Router::any() to standardVerbs() ([feee6c5](https://github.com/fabianmossberg/indexdotphp/commit/feee6c590a2caf1391eaf4ea173c91692d0e3be3))
