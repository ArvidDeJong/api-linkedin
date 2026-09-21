---
title: "Quick start"
description: "One complete Laravel example for darvis/api-linkedin: a settings page with a connect button, the flash messages, and a queued job that publishes a post."
nav_order: 3
---

# Quick start

This page builds the main use case from start to finish: an administrator connects a LinkedIn account once, and your application publishes posts from a queued job. It assumes you finished [Installation](installation.md), and that your application has a login.

## 1. Allow only administrators to connect

Publish the config when you have not done so yet:

```bash
php artisan vendor:publish --tag=linkedin-config
```

```php
// config/linkedin.php
'routes' => [
    'callback_name' => 'linkedin.callback',
    'connect_name' => 'linkedin.connect',
    'enabled' => env('LINKEDIN_ROUTES_ENABLED', true),
    'middleware' => ['web', 'auth', 'can:manage-linkedin'],
    'prefix' => env('LINKEDIN_ROUTE_PREFIX', 'linkedin'),
    'redirect_to' => 'settings.linkedin',
],
```

`can:manage-linkedin` is Laravel's [authorization middleware](https://laravel.com/docs/authorization#via-middleware): it lets a request through when the gate `manage-linkedin` says yes. `redirect_to` is the name of the route the user returns to after connecting. Define the gate:

```php
// app/Providers/AppServiceProvider.php
use App\Models\User;
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    // Replace the rule with how your application recognises an administrator.
    Gate::define('manage-linkedin', fn (User $user) => (bool) $user->is_admin);
}
```

Anyone who fails this gate gets a 403 on `/linkedin/connect`. A guest is sent to your `login` route.

## 2. Add a settings page with a connect button

```php
// routes/web.php
use Illuminate\Support\Facades\Route;

Route::view('/settings/linkedin', 'settings.linkedin')
    ->middleware(['auth', 'can:manage-linkedin'])
    ->name('settings.linkedin');
```

{% raw %}
```blade
{{-- resources/views/settings/linkedin.blade.php --}}
@if (session('linkedin_status'))
    <p>{{ session('linkedin_status') }}</p>
@endif

@if (session('linkedin_error'))
    <p>{{ session('linkedin_error') }}</p>
@endif

@if (LinkedIn::isConnected())
    <p>Connected as {{ LinkedIn::account()->name }}.</p>
@endif

<a href="{{ route('linkedin.connect') }}">Connect LinkedIn</a>
<a href="{{ route('linkedin.connect', ['profile_only' => 1]) }}">Connect my profile only</a>
```
{% endraw %}

`LinkedIn` is the facade alias the package registers, so it works in a view without an import. The first link asks for the scopes your config implies. The second link asks for the profile scopes only; use it when your LinkedIn app has no Community Management API. After the flow the package redirects back to this page with a message under `linkedin_status` or `linkedin_error`; [Connecting](connecting.md#when-connecting-fails) lists the texts.

## 3. Publish from a queued job

A publish is one or more HTTP calls to LinkedIn. Keep it out of the web request, so a slow answer does not make a visitor wait.

```php
// app/Jobs/PublishToLinkedIn.php
namespace App\Jobs;

use Darvis\ApiLinkedin\Exceptions\LinkedInApiException;
use Darvis\ApiLinkedin\Exceptions\LinkedInException;
use Darvis\ApiLinkedin\Facades\LinkedIn;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class PublishToLinkedIn implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(public string $text) {}

    public function handle(): void
    {
        try {
            $result = LinkedIn::postAsMember($this->text);
        } catch (LinkedInApiException $e) {
            // No answer at all, or LinkedIn had a server error: let the queue try again.
            if ($e->isConnectionProblem() || $e->status >= 500) {
                throw $e;
            }

            // LinkedIn said no. The same request would get the same answer.
            $this->fail($e);

            return;
        } catch (LinkedInException $e) {
            // Not connected, connection expired, scope missing or a setting missing.
            $this->fail($e);

            return;
        }

        Log::info('Published on LinkedIn.', $result);
    }
}
```

`postAsMember()` returns an array with two keys: `urn`, the id LinkedIn gave the post, and `permalink`, the address of the post. The job retries only when a retry can help. Everything else fails at once, and the exception type tells you why; see [Error handling](errors.md).

## 4. Dispatch the job

```php
// anywhere in your application, for example a controller or an observer
use App\Jobs\PublishToLinkedIn;

PublishToLinkedIn::dispatch("We published a new article.\n\nhttps://example.com/blog/my-article");
```

The package sends the text as it is, without a link card; a preview of an address in the text is LinkedIn's own work. Your queue worker has to run (`php artisan queue:work`), or the job waits in the queue.

## What to read next

- Post on a company page instead of a profile: [Publishing](publishing.md#posting-as-a-company-page).
- Attach a card with your own image: [Publishing](publishing.md#an-article-card-with-your-own-image).
- Test the job without calling LinkedIn: [Testing](testing.md).
