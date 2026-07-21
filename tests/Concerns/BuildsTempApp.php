<?php

namespace NickDeKruijk\LeapTemplate\Tests\Concerns;

use NickDeKruijk\Leap\ServiceProvider;

/**
 * A throwaway Laravel-app skeleton to install into, and the teardown that removes it
 * again. Shared by the tests that run leap:template: the installer writes into the
 * project it is standing in, so each one needs a project of its own.
 */
trait BuildsTempApp
{
    protected string $temp;

    protected string $originalCwd;

    /**
     * The directories and files a bare app already ships with, plus the ones the patch
     * steps expect to edit. Deliberately not app/Leap, app/Livewire, app/Support, lang,
     * public/css or tests/Feature — those are the ones copyOrReplace has to create on
     * its own.
     */
    protected function buildTempApp(): void
    {
        $this->originalCwd = getcwd();
        $this->temp = sys_get_temp_dir().'/leap-template-'.uniqid();

        foreach ([
            'app/Http/Controllers', 'app/Models', 'database/migrations',
            'database/seeders', 'config', 'public', 'tests', 'routes',
        ] as $dir) {
            mkdir($this->temp.'/'.$dir, 0777, true);
        }

        // leap's shipped config lives in the leap package, not this one — locate it by
        // reflecting on its ServiceProvider (works via vendor or the local path repo).
        $leapConfig = dirname((new \ReflectionClass(ServiceProvider::class))->getFileName(), 2).'/config/leap.php';
        copy($leapConfig, $this->temp.'/config/leap.php');
        file_put_contents($this->temp.'/routes/web.php', "<?php\n\nRoute::get('/', function () {\n    return view('welcome');\n});\n");
        file_put_contents($this->temp.'/database/seeders/DatabaseSeeder.php', "<?php\n\nnamespace Database\\Seeders;\n\nuse Illuminate\\Database\\Seeder;\n\nclass DatabaseSeeder extends Seeder\n{\n    public function run(): void\n    {\n    }\n}\n");
        file_put_contents($this->temp.'/app/Models/User.php', "<?php\n\nnamespace App\\Models;\n\nclass User {}\n");
        file_put_contents($this->temp.'/.env', "APP_LOCALE=en\nAPP_FALLBACK_LOCALE=en\n");

        // One of the two compiled-asset rules is already here, so a re-run has to
        // add the missing one without duplicating the other
        file_put_contents($this->temp.'/.gitignore', "/vendor\n/public/css/builds\n");

        $this->app->setBasePath($this->temp);
        chdir($this->temp);

        // storage:link reads filesystems.links, which was resolved against the real
        // application path before the base path moved here
        mkdir($this->temp.'/storage/app/public', 0777, true);
        config(['filesystems.links' => [
            $this->temp.'/public/storage' => $this->temp.'/storage/app/public',
        ]]);
    }

    protected function removeTempApp(): void
    {
        chdir($this->originalCwd);
        $this->deleteDir($this->temp);
    }

    protected function deleteDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (array_diff(scandir($dir), ['.', '..']) as $entry) {
            $path = $dir.'/'.$entry;
            is_dir($path) ? $this->deleteDir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
