<?php

namespace Tests;

use Facebook\WebDriver\Chrome\ChromeOptions;
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
        // before typing doesn't fix this reliably; this macro instead polls
        // the actual field value and retries the type until it lands, bounded
        // by a real timeout instead of a guess.
        Browser::macro('typeReliably', function (string $field, string $value, int $seconds = 10) {
            /** @var Browser $this */
            $this->waitUsing($seconds, 100, function () use ($field, $value) {
                if ($this->inputValue($field) === $value) {
                    return true;
                }

                $this->clear($field)->type($field, $value);

                return $this->inputValue($field) === $value;
            }, "Waited %s seconds for [{$field}] to accept the value [{$value}].");

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
