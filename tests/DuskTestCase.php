<?php

namespace Tests;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Exception\TimeoutException;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Collection;
use Laravel\Dusk\Browser;
use Laravel\Dusk\TestCase as BaseTestCase;
use PHPUnit\Framework\Attributes\BeforeClass;

abstract class DuskTestCase extends BaseTestCase
{
    /**
     * Prepare for Dusk test execution.
     */
    #[BeforeClass]
    public static function prepare(): void
    {
        if (! static::runningInSail()) {
            static::startChromeDriver(['--port=9515']);
        }

        // CI's chromedriver is consistently slower than local Docker, especially
        // for page reloads and Alpine reactivity ticks later in a browse() session.
        Browser::$waitSeconds = 15;

        // On a resource-constrained runner, sendKeys can fire before a field is
        // genuinely interactive even after waitFor() resolves on its selector -
        // the DOM node exists, but isn't accepting input yet. A fixed pause()
        // before typing doesn't fix this reliably (still observed failing in
        // CI); this macro instead polls the actual field value and retries the
        // type until it lands, bounded by a real timeout instead of a guess.
        //
        // Diagnostic instrumentation (temporary): dumps the negotiated
        // WebDriver capabilities once, and on every failed poll iteration,
        // whether document.activeElement is genuinely the target field and
        // whether a plain JS value-set (bypassing sendKeys entirely) lands -
        // this distinguishes a sendKeys/WebDriver-protocol problem from
        // something broader affecting the whole page/session.
        Browser::macro('typeReliably', function (string $field, string $value, int $seconds = 10) {
            /** @var Browser $this */
            static $dumpedCapabilities = false;

            if (! $dumpedCapabilities && env('DUSK_DEBUG_TYPING')) {
                $dumpedCapabilities = true;
                fwrite(STDERR, "\n[typeReliably] capabilities: ".json_encode($this->driver->getCapabilities()->toArray())."\n");
            }

            $attempt = 0;

            try {
                $this->waitUsing($seconds, 100, function () use ($field, $value, &$attempt) {
                    $attempt++;

                    if ($this->inputValue($field) === $value) {
                        return true;
                    }

                    $this->clear($field)->type($field, $value);

                    $landed = $this->inputValue($field) === $value;

                    if (! $landed && env('DUSK_DEBUG_TYPING')) {
                        $element = $this->resolver->resolveForTyping($field);
                        $active = $this->driver->executeScript('return arguments[0] === document.activeElement ? "match" : (document.activeElement ? document.activeElement.tagName+"#"+document.activeElement.id : "none");', [$element]);
                        $jsSetWorks = $this->driver->executeScript('var el = arguments[0]; el.value = arguments[1]; el.dispatchEvent(new Event("input", {bubbles:true})); return el.value === arguments[1] ? "js-set-worked" : "js-set-failed:"+el.value;', [$element, $value]);

                        fwrite(STDERR, "[typeReliably] attempt {$attempt} for [{$field}]: activeElement={$active}, jsSet={$jsSetWorks}\n");
                    }

                    return $landed;
                }, "Waited %s seconds for [{$field}] to accept the value [{$value}].");
            } catch (TimeoutException $e) {
                if (env('DUSK_DEBUG_TYPING')) {
                    fwrite(STDERR, "[typeReliably] gave up on [{$field}] after {$attempt} attempts\n");
                }

                throw $e;
            }

            return $this;
        });
    }

    /**
     * Create the RemoteWebDriver instance.
     */
    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments(collect([
            $this->shouldStartMaximized() ? '--start-maximized' : '--window-size=1920,1080',
            '--disable-search-engine-choice-screen',
            '--disable-smooth-scrolling',
            '--no-sandbox',
        ])->unless($this->hasHeadlessDisabled(), function (Collection $items) {
            return $items->merge([
                '--disable-gpu',
                '--headless=new',
            ]);
        })->all());

        if ($binary = env('DUSK_CHROME_BINARY')) {
            $options->setBinary($binary);
        }

        return RemoteWebDriver::create(
            $_ENV['DUSK_DRIVER_URL'] ?? env('DUSK_DRIVER_URL') ?? 'http://localhost:9515',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY, $options
            )
        );
    }
}
