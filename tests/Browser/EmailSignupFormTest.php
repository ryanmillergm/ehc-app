<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class EmailSignupFormTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_page_variant_keeps_submit_disabled_when_turnstile_script_is_unavailable(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/emails/subscribe')
                ->type('@email-signup-page-first-name', 'No')
                ->type('@email-signup-page-last-name', 'Script')
                ->type('@email-signup-page-email', 'noscript@example.com')
                ->assertVisible('@email-signup-page-turnstile-loading')
                ->assertDisabled('@email-signup-page-submit');
        });
    }

    public function test_page_variant_requires_turnstile_before_each_submit_attempt(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/emails/subscribe');

            $this->installTurnstileStub($browser);

            $browser->type('@email-signup-page-first-name', 'Dusk')
                ->type('@email-signup-page-last-name', 'User')
                ->type('@email-signup-page-email', 'dusk-page@example.com')
                ->assertDisabled('@email-signup-page-submit');

            $this->solveTurnstile($browser, 'page-token-1');

            $browser->waitUntilEnabled('@email-signup-page-submit')
                ->click('@email-signup-page-submit')
                ->waitFor('@email-signup-banner')
                ->assertDisabled('@email-signup-page-submit');

            $this->solveTurnstile($browser, 'page-token-2');

            $browser->waitUntilEnabled('@email-signup-page-submit')
                ->click('@email-signup-page-submit')
                ->waitFor('@email-signup-banner')
                ->assertDisabled('@email-signup-page-submit');
        });
    }

    public function test_footer_variant_requires_turnstile_before_each_submit_attempt(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/');

            $this->installTurnstileStub($browser);

            $browser->script("document.querySelector('[dusk=\"email-signup-footer-email\"]').scrollIntoView({block:'center'});");

            $browser->type('@email-signup-footer-email', 'dusk-footer@example.com')
                ->assertDisabled('@email-signup-footer-submit');

            $this->solveTurnstile($browser, 'footer-token-1');

            $browser->waitUntilEnabled('@email-signup-footer-submit')
                ->click('@email-signup-footer-submit')
                ->waitFor('@email-signup-banner')
                ->assertDisabled('@email-signup-footer-submit');

            $this->solveTurnstile($browser, 'footer-token-2');

            $browser->waitUntilEnabled('@email-signup-footer-submit')
                ->click('@email-signup-footer-submit')
                ->waitFor('@email-signup-banner')
                ->assertDisabled('@email-signup-footer-submit');
        });
    }

    private function installTurnstileStub(Browser $browser): void
    {
        $browser->script(<<<'JS'
window.__tsWidgets = {};
window.__tsLastWidgetId = null;
window.turnstile = {
  render: function(el, opts) {
    const id = 'wid_' + Math.random().toString(36).slice(2);
    window.__tsWidgets[id] = opts || {};
    window.__tsLastWidgetId = id;
    if (el) {
      el.innerHTML = '<iframe title="Turnstile test widget"></iframe>';
    }
    return id;
  },
  reset: function(id) {
    const wid = id || window.__tsLastWidgetId;
    if (!wid || !window.__tsWidgets[wid]) return;
    const opts = window.__tsWidgets[wid];
    if (typeof opts['expired-callback'] === 'function') {
      opts['expired-callback']();
    }
  }
};
window.__tsSolve = function(token) {
  const wid = window.__tsLastWidgetId;
  if (!wid || !window.__tsWidgets[wid]) return false;
  const opts = window.__tsWidgets[wid];
  if (typeof opts.callback === 'function') {
    opts.callback(token || 'dusk-token');
    return true;
  }
  return false;
};
JS);

        $browser->pause(300);
    }

    private function solveTurnstile(Browser $browser, string $token): void
    {
        $browser->script("window.__tsSolve('{$token}');");
        $browser->pause(200);
    }
}
