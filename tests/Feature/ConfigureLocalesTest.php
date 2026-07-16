<?php

namespace NickDeKruijk\LeapTemplate\Tests\Feature;

use Illuminate\Console\OutputStyle;
use NickDeKruijk\LeapTemplate\Commands\TemplateCommand;
use NickDeKruijk\LeapTemplate\Tests\TestCase;
use ReflectionMethod;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * leap.locales decides which locale is served unprefixed (see leap's Leap::detectLocale).
 * APP_LOCALE steers what is left: the admin, the console, queues and mail. They may
 * disagree on purpose — an English admin on a Dutch site is a normal thing to want — so
 * the installer must not quietly force them back together.
 */
class ConfigureLocalesTest extends TestCase
{
    private string $temp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temp = sys_get_temp_dir().'/leap-locales-'.uniqid();
        mkdir($this->temp.'/config', 0777, true);

        $this->app->setBasePath($this->temp);
    }

    protected function tearDown(): void
    {
        foreach ((array) glob($this->temp.'/{,.}*', GLOB_BRACE) as $f) {
            is_file($f) && unlink($f);
        }
        foreach ((array) glob($this->temp.'/config/*') as $f) {
            unlink($f);
        }
        @rmdir($this->temp.'/config');
        @rmdir($this->temp);

        parent::tearDown();
    }

    private function configureLocales(array $locales): void
    {
        $command = new TemplateCommand;
        $command->setLaravel($this->app);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new NullOutput));

        // --locales answers the picker, --fresh keeps the rest of the run silent.
        (new ReflectionMethod($command, 'configureLocales'))->invoke(
            tap($command, fn (TemplateCommand $c) => $c->setInput(new ArrayInput(
                ['--locales' => implode(',', $locales), '--fresh' => true],
                $c->getDefinition(),
            ))),
        );
    }

    public function test_a_fresh_config_gets_both_the_locales_and_a_matching_app_locale(): void
    {
        file_put_contents($this->temp.'/config/leap.php', "<?php\n\nreturn [\n    'locales' => null,\n];\n");
        file_put_contents($this->temp.'/.env', "APP_LOCALE=en\nAPP_FALLBACK_LOCALE=en\n");

        $this->configureLocales(['nl', 'en']);

        $config = file_get_contents($this->temp.'/config/leap.php');
        $this->assertStringContainsString("'nl' => 'Nederlands'", $config);
        $this->assertStringContainsString('APP_LOCALE=nl', file_get_contents($this->temp.'/.env'));
    }

    /**
     * The bug this test exists for: the installer wrote APP_LOCALE unconditionally, even
     * on the branch where it had just decided to leave leap.locales alone. So re-running
     * leap:template on a site deliberately running an English admin reset it to the first
     * locale, and said nothing about it.
     */
    public function test_a_customised_config_keeps_its_app_locale(): void
    {
        file_put_contents(
            $this->temp.'/config/leap.php',
            "<?php\n\nreturn [\n    'locales' => ['nl' => 'Nederlands', 'en' => 'English'],\n];\n",
        );
        // Dutch site, English admin — on purpose.
        file_put_contents($this->temp.'/.env', "APP_LOCALE=en\nAPP_FALLBACK_LOCALE=en\n");

        $this->configureLocales(['nl', 'en']);

        $this->assertStringContainsString(
            'APP_LOCALE=en',
            file_get_contents($this->temp.'/.env'),
            'A hand-configured leap.locales means APP_LOCALE was a choice too.',
        );
    }

    public function test_a_customised_config_is_left_untouched(): void
    {
        $original = "<?php\n\nreturn [\n    'locales' => ['en' => 'English', 'nl' => 'Nederlands'],\n];\n";
        file_put_contents($this->temp.'/config/leap.php', $original);
        file_put_contents($this->temp.'/.env', "APP_LOCALE=en\n");

        $this->configureLocales(['nl']);

        $this->assertSame($original, file_get_contents($this->temp.'/config/leap.php'));
    }
}
