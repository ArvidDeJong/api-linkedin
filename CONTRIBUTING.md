# Contributing

Contributions are welcome: bug reports, fixes, documentation and ideas.

## Before you start

- **Bugs:** open an [issue](https://github.com/ArvidDeJong/api-linkedin/issues/new/choose) with the steps to reproduce.
- **Features:** open an issue first, so we can agree it fits before you build it.
- **Security issues:** don't open an issue; see [SECURITY.md](SECURITY.md).

## Development

```bash
git clone https://github.com/ArvidDeJong/api-linkedin.git
cd api-linkedin
composer install

composer test      # Pest
composer lint      # Pint, check only (composer format fixes)
composer analyse   # Larastan, level 8
```

CI runs the tests on PHP 8.2 to 8.4 with Laravel 11, 12 and 13, on the lowest and the latest dependencies.

## Pull requests

- Add or update tests for every change in behaviour. All LinkedIn calls are faked with `Http::fake()`; no test may reach LinkedIn.
- Keep the public API compatible within 1.x. Don't add return types to existing public methods that host apps may override; deprecate first and remove in 2.0.
- Every failure throws a typed subclass of `LinkedInException`. Give a new throw site the right type and operation constant; host apps branch on the type, never on the message.
- No external HTTP dependency: everything goes through `Illuminate\Support\Facades\Http`. Don't add a Guzzle wrapper, a Socialite provider or a LinkedIn SDK.
- Never read `$account->access_token` directly; go through `LinkedInOAuth::freshAccessToken()` so an expired token is refreshed first.
- Write code, comments, messages and docs in English. The Dutch documentation under `docs/nl/` mirrors the English root; a change to one means a change to the other.
- Update `README.md`, `CHANGELOG.md` (under `Unreleased`) and `resources/boost/` when users will notice the change.

## Code of conduct

This project follows the [Contributor Covenant](https://github.com/ArvidDeJong/.github/blob/main/CODE_OF_CONDUCT.md).
