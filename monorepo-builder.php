<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symplify\MonorepoBuilder\Release\ReleaseWorker\AddTagToChangelogReleaseWorker;
use Symplify\MonorepoBuilder\Release\ReleaseWorker\PushNextDevReleaseWorker;
use Symplify\MonorepoBuilder\Release\ReleaseWorker\PushTagReleaseWorker;
use Symplify\MonorepoBuilder\Release\ReleaseWorker\SetCurrentMutualDependenciesReleaseWorker;
use Symplify\MonorepoBuilder\Release\ReleaseWorker\SetNextMutualDependenciesReleaseWorker;
use Symplify\MonorepoBuilder\Release\ReleaseWorker\TagVersionReleaseWorker;
use Symplify\MonorepoBuilder\Release\ReleaseWorker\UpdateBranchAliasReleaseWorker;
use Symplify\MonorepoBuilder\ValueObject\Option;

/**
 * Monorepo Builder additional fields
 *
 * @see https://github.com/symplify/symplify/issues/2061
 */
\register_shutdown_function(static function () {
    $dest = \json_decode(\file_get_contents(__DIR__ . '/composer.json'), true);

    $result = [
        'name'              => 'spiral/framework',
        'type'              => 'library',
        'description'       => $dest['description'] ?? '',
        'homepage'          => 'https://spiral.dev',
        'license'           => 'MIT',
        'support'           => [
            'issues' => 'https://github.com/spiral/framework/issues',
            'source' => 'https://github.com/spiral/framework',
        ],
        'authors'           => [
            [
                'name'  => 'Wolfy-J',
                'email' => 'wolfy.jd@gmail.com',
            ],
        ],
        'bin'               => [
            'bin/spiral',
        ],
        'require'           => $dest['require'] ?? [],
        'autoload'          => $dest['autoload'] ?? [],
        'require-dev'       => $dest['require-dev'] ?? [],
        'autoload-dev'      => $dest['autoload-dev'] ?? [],
        'replace'           => $dest['replace'] ?? [],
        'scripts'           => $dest['scripts'] ?? [],
        'extra'             => $dest['extra'] ?? [],
        'config'            => $dest['config'] ?? [],
        'minimum-stability' => $dest['minimum-stability'] ?? 'dev',
        'prefer-stable'     => $dest['prefer-stable'] ?? true,
    ];

    $json = \json_encode($result, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES);

    \file_put_contents(__DIR__ . '/composer.json', $json . "\n");
});


return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters->set(Option::PACKAGE_ALIAS_FORMAT, '<major>.<minor>.x-dev');
    $parameters->set(Option::PACKAGE_DIRECTORIES, ['src']);

    $parameters->set(Option::DATA_TO_APPEND, [
        'type'         => 'library',
        'autoload'     => [
            'files' => [
                'src/Framework/helpers.php',
            ],
            'psr-4' => [
                'Spiral\\' => 'src/Framework',
            ],
        ],
        'require'      => [
            'spiral/composer-publish-plugin' => '^1.0',
            'laminas/laminas-diactoros'      => '^2.4',
        ],
        'autoload-dev' => [
            'psr-4' => [
                'Spiral\\Tests\\Framework\\' => 'tests/Framework',
                'Spiral\\App\\'              => 'tests/app/src',
            ],
        ],
        'require-dev'  => [
            // Cycle ORM
            'cycle/annotated'           => '^2.0.6',
            'cycle/migrations'          => '^1.0.1',
            'cycle/orm'                 => '^1.2.6',
            'cycle/proxy-factory'       => '^1.2',
            'cycle/schema-builder'      => '^1.1',
            'laminas/laminas-hydrator'  => '^3.0',
            'spiral/database'           => '^2.7.3',
            'spiral/migrations'         => '^2.1',

            // RoadRunner
            'spiral/roadrunner'         => '^1.9.2|^2.0',

            // Testing
            'phpunit/phpunit'           => '^8.5|^9.0',
            'mockery/mockery'           => '^1.3',
            'vimeo/psalm'               => '^4.6',
            'spiral/code-style'         => '^1.0',
            'ramsey/uuid'               => '^3.9',

            // Deployment
            'symplify/monorepo-builder' => '^8.3.40',
        ],
    ]);

    $parameters->set(Option::DIRECTORIES_TO_REPOSITORIES, [
        // Bridge
        'src/Bridge/DataGrid' => 'git@github.com:spiral/data-grid-bridge.git',
        'src/Bridge/Stempler' => 'git@github.com:spiral/stempler-bridge.git',
        'src/Bridge/Monolog'  => 'git@github.com:spiral/monolog-bridge.git',
        'src/Bridge/Dotenv'   => 'git@github.com:spiral/dotenv-bridge.git',

        // Components
        'src/AnnotatedRoutes' => 'git@github.com:spiral/annotated-routes.git',
        'src/Annotations'     => 'git@github.com:spiral/annotations.git',
        'src/Auth'            => 'git@github.com:spiral/auth.git',
        'src/AuthHttp'        => 'git@github.com:spiral/auth-http.git',
        'src/Boot'            => 'git@github.com:spiral/boot.git',
        'src/Config'          => 'git@github.com:spiral/config.git',
        'src/Console'         => 'git@github.com:spiral/console.git',
        'src/Cookies'         => 'git@github.com:spiral/cookies.git',
        'src/Core'            => 'git@github.com:spiral/core.git',
        'src/Csrf'            => 'git@github.com:spiral/csrf.git',
        'src/DataGrid'        => 'git@github.com:spiral/data-grid.git',
        'src/Debug'           => 'git@github.com:spiral/debug.git',
        'src/Dumper'          => 'git@github.com:spiral/dumper.git',
        'src/Encrypter'       => 'git@github.com:spiral/encrypter.git',
        'src/Exceptions'      => 'git@github.com:spiral/exceptions.git',
        'src/Files'           => 'git@github.com:spiral/files.git',
        'src/Filters'         => 'git@github.com:spiral/filters.git',
        'src/Hmvc'            => 'git@github.com:spiral/hmvc.git',
        'src/Http'            => 'git@github.com:spiral/http.git',
        'src/Logger'          => 'git@github.com:spiral/logger.git',
        'src/Mailer'          => 'git@github.com:spiral/mailer.git',
        'src/Models'          => 'git@github.com:spiral/models.git',
        'src/Pagination'      => 'git@github.com:spiral/pagination.git',
        'src/Prototype'       => 'git@github.com:spiral/prototype.git',
        'src/Reactor'         => 'git@github.com:spiral/reactor.git',
        'src/Router'          => 'git@github.com:spiral/router.git',
        'src/Scaffolder'      => 'git@github.com:spiral/scaffolder.git',
        'src/Security'        => 'git@github.com:spiral/security.git',
        'src/Session'         => 'git@github.com:spiral/session.git',
        'src/Snapshots'       => 'git@github.com:spiral/snapshots.git',
        'src/Stempler'        => 'git@github.com:spiral/stempler.git',
        'src/Streams'         => 'git@github.com:spiral/streams.git',
        'src/Tokenizer'       => 'git@github.com:spiral/tokenizer.git',
        'src/Translator'      => 'git@github.com:spiral/translator.git',
        'src/Validation'      => 'git@github.com:spiral/validation.git',
        'src/Views'           => 'git@github.com:spiral/views.git',
    ]);

    $services = $containerConfigurator->services();

    # release workers - in order to execute
    $services->set(SetCurrentMutualDependenciesReleaseWorker::class);
    $services->set(AddTagToChangelogReleaseWorker::class);
    $services->set(TagVersionReleaseWorker::class);
    $services->set(PushTagReleaseWorker::class);
    $services->set(SetNextMutualDependenciesReleaseWorker::class);
    $services->set(UpdateBranchAliasReleaseWorker::class);
    $services->set(PushNextDevReleaseWorker::class);
};
