<?php

namespace NickDeKruijk\LeapTemplate\Tests\Feature;

use NickDeKruijk\LeapTemplate\Commands\TemplateCommand;
use NickDeKruijk\LeapTemplate\Tests\TestCase;
use ReflectionMethod;

/**
 * Leap needs four things on the User model, and the installer knows exactly which — leaving
 * them as homework was out of step with routes/web.php, DatabaseSeeder and config/leap.php,
 * which it patches for you. Without them /admin has no roles, no 2FA and no passkeys.
 */
class UserModelPatchTest extends TestCase
{
    /**
     * A stock Laravel 13 User model, attributes and all.
     */
    private function stockUserModel(): string
    {
        return <<<'PHP'
<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;
}

PHP;
    }

    private function patched(string $contents): ?string
    {
        $command = new TemplateCommand;
        $command->setLaravel($this->app);

        return (new ReflectionMethod($command, 'patchedUserModel'))->invoke($command, $contents);
    }

    public function test_it_adds_the_traits_the_imports_and_the_interface(): void
    {
        $patched = $this->patched($this->stockUserModel());

        $this->assertStringContainsString('use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;', $patched);
        $this->assertStringContainsString('class User extends Authenticatable implements PasskeyUser', $patched);

        foreach ([
            'use NickDeKruijk\Leap\Traits\HasRoles;',
            'use Laravel\Fortify\TwoFactorAuthenticatable;',
            'use Laravel\Passkeys\PasskeyAuthenticatable;',
            'use Laravel\Passkeys\Contracts\PasskeyUser;',
        ] as $import) {
            $this->assertStringContainsString($import, $patched);
        }

        // Untouched things stay untouched.
        $this->assertStringContainsString("#[Fillable(['name', 'email', 'password'])]", $patched);
        $this->assertStringContainsString('// use Illuminate\Contracts\Auth\MustVerifyEmail;', $patched);
    }

    /**
     * The project runs pint over its own app/, so a file the installer wrote has to pass it.
     * The laravel preset sorts both imports and trait names, which is why nothing here is
     * simply appended. This is the check that would have caught that.
     */
    public function test_the_patched_model_is_pint_clean(): void
    {
        $dir = sys_get_temp_dir().'/leap-user-'.uniqid();
        mkdir($dir, 0777, true);
        file_put_contents($file = $dir.'/User.php', $this->patched($this->stockUserModel()));
        file_put_contents($dir.'/pint.json', '{"preset": "laravel"}');

        exec(
            escapeshellarg(__DIR__.'/../../vendor/bin/pint').' --test --config '.escapeshellarg($dir.'/pint.json').' '.escapeshellarg($file).' 2>&1',
            $output,
            $status
        );

        unlink($file);
        unlink($dir.'/pint.json');
        rmdir($dir);

        $this->assertSame(0, $status, "pint would reformat the patched User model:\n".implode("\n", $output));
    }

    public function test_it_is_a_noop_when_everything_is_already_there(): void
    {
        $once = $this->patched($this->stockUserModel());

        $this->assertSame($once, $this->patched($once), 'Re-running must change nothing.');
    }

    /**
     * An existing interface is kept rather than replaced.
     */
    public function test_it_appends_to_an_existing_implements_clause(): void
    {
        $patched = $this->patched(str_replace(
            'class User extends Authenticatable',
            'class User extends Authenticatable implements MustVerifyEmail',
            $this->stockUserModel(),
        ));

        $this->assertStringContainsString('implements MustVerifyEmail, PasskeyUser', $patched);
    }

    public function test_it_gives_up_on_something_it_does_not_recognise(): void
    {
        $this->assertNull($this->patched("<?php\n\nnamespace App\Models;\n\nreturn 'not a class';\n"));
    }
}
