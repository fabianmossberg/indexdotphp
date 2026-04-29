# Contributing

Thanks for taking a look. This is a small library, so the conventions are
correspondingly small.

## Development setup

Requires PHP 8.3+ and Composer.

```bash
composer install
composer test       # run the suite
composer test:cov   # with coverage (requires pcov or xdebug)
composer lint       # php-cs-fixer dry-run
composer stan       # phpstan analyse
composer check      # lint + stan + test:cov in one go
```

The coverage script gates at 80%; the suite currently sits around 96%. New
features should land with tests. PRs that drop coverage below the gate will
fail CI.

## Commit message format

This project uses [Conventional Commits](https://www.conventionalcommits.org/).
The format determines version bumps and changelog entries automatically — so
the prefix matters.

| Prefix | When | Effect on release |
|---|---|---|
| `feat:` | new user-facing feature | minor bump |
| `fix:` | bug fix | patch bump |
| `feat!:` *or* `BREAKING CHANGE:` in body | breaking public API change | major bump (or minor while pre-1.0) |
| `chore:` | tooling, deps, internal cleanup | no release |
| `docs:` | documentation only | no release, but listed in changelog |
| `test:` | test-only changes | no release |
| `refactor:` | non-functional code restructure | no release |
| `ci:` | CI / GitHub Actions changes | no release |

Examples:

```
feat: add JWT helper
fix: handle null query in queryInt
docs: clarify decoder fall-through behavior
chore: bump pestphp/pest to ^4.7
feat!: rename Router::handle() to Router::dispatch()
```

The body of a commit can include a `BREAKING CHANGE:` footer for major bumps
when you don't want the `!` in the type:

```
feat: rework decoder API

BREAKING CHANGE: registerDecoder now takes a closure instead of
a string class name. Existing decoders need to be wrapped.
```

## How releases work

Releases are automated via
[release-please](https://github.com/googleapis/release-please-action). You
don't tag manually.

1. Push commits with conventional prefixes to `master`.
2. The workflow opens (or updates) a "Release PR" titled
   `chore(master): release X.Y.Z` containing the version bump and a generated
   `CHANGELOG.md` entry.
3. Review and merge the release PR when you're ready to ship.
4. The merge triggers the actual tag (`vX.Y.Z`) and GitHub release.

**Don't push manual tags.** The bot tracks "current version" by reading the
latest tag; a manually-pushed tag desyncs its understanding of what's released
vs unreleased.

## Tests

Tests live under `tests/` using [Pest](https://pestphp.com/). New behavior
should have tests; bug fixes should have a regression test that fails on
`master` and passes with the fix. Run a single test file with:

```bash
vendor/bin/pest tests/Router/SomethingTest.php
```

## What's in scope

The library is intentionally small. Before opening a PR for a substantial new
subsystem (JWT, DB wrapper, cache layer, etc.), open an issue first to discuss
fit and scope.
