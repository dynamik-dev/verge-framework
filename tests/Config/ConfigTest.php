<?php

declare(strict_types=1);

use Verge\App;
use Verge\Config\Config;
use Verge\Verge;

use function Verge\base_path;
use function Verge\config;

describe('Config', function () {
    describe('get/set', function () {
        it('sets and gets values', function () {
            $config = new Config();

            $config->set(['app.name' => 'My App']);

            expect($config->get('app.name'))->toBe('My App');
        });

        it('returns default for missing key', function () {
            $config = new Config();

            expect($config->get('missing', 'default'))->toBe('default');
        });

        it('returns null by default for missing key', function () {
            $config = new Config();

            expect($config->get('missing'))->toBeNull();
        });

        it('supports dot notation for nested values', function () {
            $config = new Config();

            $config->set(['database' => ['host' => 'localhost', 'port' => 3306]]);

            expect($config->get('database.host'))->toBe('localhost');
            expect($config->get('database.port'))->toBe(3306);
        });

        it('sets nested values with dot notation', function () {
            $config = new Config();

            $config->set(['mail.from.address' => 'hello@example.com']);

            expect($config->get('mail.from.address'))->toBe('hello@example.com');
        });

        it('gets entire nested array', function () {
            $config = new Config();

            $config->set(['database' => ['host' => 'localhost', 'port' => 3306]]);

            expect($config->get('database'))->toBe(['host' => 'localhost', 'port' => 3306]);
        });
    });

    describe('has()', function () {
        it('returns true for existing key', function () {
            $config = new Config();
            $config->set(['app.name' => 'My App']);

            expect($config->has('app.name'))->toBeTrue();
        });

        it('returns false for missing key', function () {
            $config = new Config();

            expect($config->has('missing'))->toBeFalse();
        });

        it('returns true for nested key', function () {
            $config = new Config();
            $config->set(['database' => ['host' => 'localhost']]);

            expect($config->has('database.host'))->toBeTrue();
        });
    });

    describe('all()', function () {
        it('returns all config items', function () {
            $config = new Config();
            $config->set(['app' => ['name' => 'My App'], 'debug' => true]);

            expect($config->all())->toBe(['app' => ['name' => 'My App'], 'debug' => true]);
        });
    });

    describe('load()', function () {
        beforeEach(function () {
            // Create a temp config file
            $this->configDir = sys_get_temp_dir() . '/verge-config-test';
            @mkdir($this->configDir, 0755, true);

            file_put_contents($this->configDir . '/payments.php', '<?php return ["gateway" => "stripe", "currency" => "USD"];');
        });

        afterEach(function () {
            @unlink($this->configDir . '/payments.php');
            @rmdir($this->configDir);
        });

        it('loads config from file with filename as namespace', function () {
            $config = new Config();

            $config->load($this->configDir . '/payments.php');

            expect($config->get('payments.gateway'))->toBe('stripe');
            expect($config->get('payments.currency'))->toBe('USD');
        });

        it('loads config with custom namespace', function () {
            $config = new Config();

            $config->load($this->configDir . '/payments.php', 'billing');

            expect($config->get('billing.gateway'))->toBe('stripe');
        });

        it('throws for missing file', function () {
            $config = new Config();

            expect(fn () => $config->load('/nonexistent/file.php'))
                ->toThrow(RuntimeException::class, 'Config file not found');
        });
    });
});

describe('App config integration', function () {
    beforeEach(fn () => Verge::reset());

    describe('config()', function () {
        it('sets config values with array', function () {
            $app = new App();

            $app->config(['app.name' => 'My App']);

            expect($app->config('app.name'))->toBe('My App');
        });

        it('gets config value', function () {
            $app = new App();
            $app->config(['debug' => true]);

            expect($app->config('debug'))->toBeTrue();
        });

        it('gets config with default', function () {
            $app = new App();

            expect($app->config('missing', 'default'))->toBe('default');
        });

        it('returns all config when called with no args', function () {
            $app = new App();
            $app->config(['app.name' => 'Test']);

            $all = $app->config();

            expect($all)->toBeArray();
            expect($all['app']['name'])->toBe('Test');
        });
    });

    describe('loadConfig()', function () {
        beforeEach(function () {
            $this->configDir = sys_get_temp_dir() . '/verge-config-test';
            @mkdir($this->configDir, 0755, true);
            file_put_contents($this->configDir . '/app.php', '<?php return ["name" => "Loaded App"];');
        });

        afterEach(function () {
            @unlink($this->configDir . '/app.php');
            @rmdir($this->configDir);
        });

        it('loads config file', function () {
            $app = new App();

            $app->loadConfig($this->configDir . '/app.php');

            expect($app->config('app.name'))->toBe('Loaded App');
        });

        it('is chainable', function () {
            $app = new App();

            $result = $app->loadConfig($this->configDir . '/app.php');

            expect($result)->toBe($app);
        });
    });

    describe('basePath()', function () {
        it('throws when base path not set', function () {
            $app = new App();

            expect(fn () => $app->basePath())
                ->toThrow(RuntimeException::class, 'Base path not set');
        });

        it('returns base path after setting', function () {
            $app = new App();
            $app->setBasePath('/var/www/myapp');

            expect($app->basePath())->toBe('/var/www/myapp');
        });

        it('appends sub-path', function () {
            $app = new App();
            $app->setBasePath('/var/www/myapp');

            expect($app->basePath('config/app.php'))->toBe('/var/www/myapp' . DIRECTORY_SEPARATOR . 'config/app.php');
        });

        it('setBasePath is chainable', function () {
            $app = new App();

            $result = $app->setBasePath('/var/www');

            expect($result)->toBe($app);
        });

        it('trims trailing slashes', function () {
            $app = new App();
            $app->setBasePath('/var/www/myapp/');

            expect($app->basePath())->toBe('/var/www/myapp');
        });
    });
});

describe('config helpers', function () {
    beforeEach(fn () => Verge::reset());

    it('config() gets value', function () {
        $app = Verge::create();
        $app->config(['test.key' => 'value']);

        expect(config('test.key'))->toBe('value');
    });

    it('config() sets value', function () {
        Verge::create();

        config(['helper.key' => 'from-helper']);

        expect(config('helper.key'))->toBe('from-helper');
    });

    it('base_path() returns base path', function () {
        $app = Verge::create();
        $app->setBasePath('/var/www');

        expect(base_path())->toBe('/var/www');
    });

    it('base_path() appends sub-path', function () {
        $app = Verge::create();
        $app->setBasePath('/var/www');

        expect(base_path('config'))->toBe('/var/www' . DIRECTORY_SEPARATOR . 'config');
    });
});
