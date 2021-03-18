<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Spiral\Bootloader\Temporal;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\Exception\BootException;
use Spiral\Boot\KernelInterface;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Core\Container;
use Spiral\RoadRunner\EnvironmentInterface;
use Spiral\Temporal\ClientManager;
use Spiral\Temporal\ClientManagerInterface;
use Spiral\Temporal\Config\TemporalConfig;
use Spiral\Temporal\TemporalDispatcher;
use Temporal\Api\Enums\V1\QueryRejectCondition;
use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowClientInterface;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Internal\Support\DateInterval;
use Temporal\Worker\Transport\Goridge;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\Worker\WorkerInterface;
use Temporal\Worker\WorkerOptions;
use Temporal\WorkerFactory;

/**
 * Enables support of Temporal
 *
 * @psalm-import-type DateIntervalValue from DateInterval
 *
 * @psalm-type ClientConfigSectionRejectionCondition = QueryRejectCondition::QUERY_REJECT_CONDITION_*
 *
 * @psalm-type ClientConfigSection = array {
 *      host?: string|null,
 *      namespace?: string|null,
 *      identity?: string|null,
 *      queryRejectionCondition?: ClientConfigSectionRejectionCondition|null,
 * }
 *
 * @psalm-type ClientConfigSections = array<string, ClientConfigSection>
 *
 * @psalm-type WorkerConfigSectionOptions = array {
 *      maxConcurrentActivityExecutionSize?:        positive-int|0,
 *      workerActivitiesPerSecond?:                 float,
 *      maxConcurrentLocalActivityExecutionSize?:   positive-int|0,
 *      workerLocalActivitiesPerSecond?:            float,
 *      taskQueueActivitiesPerSecond?:              float,
 *      maxConcurrentActivityTaskPollers?:          positive-int|0,
 *      maxConcurrentWorkflowTaskExecutionSize?:    positive-int|0,
 *      maxConcurrentWorkflowTaskPollers?:          positive-int|0,
 *      stickyScheduleToStartTimeout?:              DateIntervalValue,
 *      workerStopTimeout?:                         DateIntervalValue,
 *      enableSessionWorker?:                       bool,
 *      sessionResourceId?:                         string,
 *      maxConcurrentSessionExecutionSize?:         positive-int|0
 * }
 *
 * @psalm-type WorkerConfigSection = array {
 *      workflows?:  array<class-string>|null,
 *      activities?: array<class-string>|null,
 *      options?:    WorkerConfigSectionOptions|null,
 * }
 *
 * @pslam-type WorkerConfigSections = array<string, WorkerConfigSection>
 *
 * @psalm-type TemporalConfigArray = array {
 *      client?:  string|null,
 *      clients?: ClientConfigSections|null,
 *      workers?: WorkerConfigSections|null,
 * }
 *
 * @see DateInterval
 */
final class TemporalBootloader extends Bootloader
{
    /**
     * @var ConfiguratorInterface
     */
    private $configurator;

    /**
     * @var TemporalConfigArray
     */
    private const DEFAULT_CONFIGURATION = [

        /*
         |--------------------------------------------------------------------------
         | Default Temporal Client Connection Name
         |--------------------------------------------------------------------------
         |
         | Here you may specify which of the client connections below you wish
         | to use as your default connection for all temporal work. Of course
         | you may use many connections at once using the temporal sdk.
         |
         */

        'client'  => 'default',

        /*
         |--------------------------------------------------------------------------
         | Temporal Client Connections
         |--------------------------------------------------------------------------
         |
         | Here are each of the temporal client connections setup for your
         | application. Of course, examples of configuring each client that is
         | supported by Spiral is shown below to make development simple.
         |
         */

        'clients' => [

            'default' => [
                /**
                 * Temporal Server Host
                 *
                 * @var string
                 */
                'host'                    => 'localhost:7233',

                /**
                 * Temporal Client Namespace
                 *
                 * @var string
                 */
                'namespace'               => ClientOptions::DEFAULT_NAMESPACE,

                /**
                 * Temporal Client Identifier
                 *
                 * @var string|null
                 */
                'identity'                => null,

                /**
                 * Temporal Client Query Rejection Condition
                 *
                 * @var QueryRejectCondition::QUERY_REJECT_CONDITION_*
                 */
                'queryRejectionCondition' => QueryRejectCondition::QUERY_REJECT_CONDITION_NONE,
            ],

        ],

        /*
        |--------------------------------------------------------------------------
        | Temporal Workers
        |--------------------------------------------------------------------------
        |
        | Here you may configure the connection information for each worker that
        | is running in your application. A default configuration has been added
        | for each back-end shipped with default Spiral Application.
        |
        | You are free to add more!
        |
        */

        'workers'  => [

            WorkerFactoryInterface::DEFAULT_TASK_QUEUE => [

                /**
                 * List of workflows processed within this worker.
                 */
                'workflows' => [
                    // \App\Temporal\Workflow\ExampleWorkflow::class,
                ],

                /**
                 * List of activities processed within this worker.
                 *
                 * Please note that you can safely use dependency injection from
                 * your container in the activity classes.
                 */
                'activities' => [
                    // \App\Temporal\Activity\ExampleActivity::class,
                ],

                /**
                 * List of worker options, for information {@see WorkerOptions}
                 */
                'options' => [
                    // 'maxConcurrentActivityExecutionSize'      => 0,
                    // 'workerActivitiesPerSecond'               => 0,
                    // 'maxConcurrentLocalActivityExecutionSize' => 0,
                    // 'workerLocalActivitiesPerSecond'          => 0,
                    // 'taskQueueActivitiesPerSecond'            => 0,
                    // 'maxConcurrentActivityTaskPollers'        => 0,
                    // 'maxConcurrentWorkflowTaskExecutionSize'  => 0,
                    // 'maxConcurrentWorkflowTaskPollers'        => 0,
                    // 'stickyScheduleToStartTimeout'            => null,
                    // 'workerStopTimeout'                       => null,
                    // 'enableSessionWorker'                     => false,
                    // 'sessionResourceId'                       => null,
                    // 'maxConcurrentSessionExecutionSize'       => 1000,
                ]
            ],

        ],
    ];

    /**
     * Please note that this bootloader class can only be initialized if
     * there is a Temporal SDK.
     *
     * @param ConfiguratorInterface $configurator
     */
    public function __construct(ConfiguratorInterface $configurator)
    {
        $this->configurator = $configurator;

        if (! \interface_exists(WorkerInterface::class)) {
            throw new BootException(
                'Unable to find [temporal/sdk] dependency, ' .
                'please install it using Composer:' . \PHP_EOL .
                '    composer require temporal/sdk'
            );
        }
    }

    /**
     * Bootloader initializer method.
     * Read more at {@link https://spiral.dev/docs/framework-bootloaders#configuring-container}
     *
     * @param Container $app
     * @param KernelInterface $kernel
     * @throws \Throwable
     */
    public function boot(Container $app, KernelInterface $kernel)
    {
        $config = $this->registerConfiguration('temporal');

        // Register Client Manager
        $app->bindSingleton(ClientManagerInterface::class, $this->clientManagerRegistrar($config));
        $app->bindSingleton(ClientManager::class, static function (ClientManagerInterface $cm) {
            return $cm;
        });

        // Register Default Client
        $app->bindSingleton(WorkflowClientInterface::class, $this->defaultClientRegistrar());
        $app->bindSingleton(WorkflowClient::class, static function (WorkflowClientInterface $client) {
            return $client;
        });

        // Register Workers
        $app->bindSingleton(DataConverterInterface::class, $this->dataConverterRegistrar());
        $app->bindSingleton(DataConverter::class, static function (DataConverterInterface $dc) {
            return $dc;
        });

        $app->bindSingleton(WorkerFactoryInterface::class, $this->workerFactoryRegistrar($app, $config));
        $app->bindSingleton(WorkerFactory::class, static function (WorkerFactoryInterface $factory) {
            return $factory;
        });

        // Register Dispatcher
        $kernel->addDispatcher(
            // TODO Avoid service-location
            $app->get(TemporalDispatcher::class)
        );
    }

    /**
     * @param string $section
     * @return TemporalConfigArray
     */
    private function registerConfiguration(string $section): array
    {
        $this->configurator->setDefaults($section, self::DEFAULT_CONFIGURATION);

        return $this->configurator->getConfig($section);
    }

    /**
     * This method provides registrar of a Temporal data-converter. If any
     * need to use custom data-converter, user can do this on hims own by
     * redefining it in the custom provider.
     *
     * @return \Closure
     */
    private function dataConverterRegistrar(): \Closure
    {
        return static function (): DataConverterInterface {
            return DataConverter::createDefault();
        };
    }

    /**
     * This method provides a worker group registrar for the Temporal. Using
     * custom configuration defined in "temporal.workers" section of the
     * configuration ({@see TemporalConfig}).
     *
     * @param Container $app
     * @param TemporalConfigArray $config
     * @return \Closure
     */
    private function workerFactoryRegistrar(Container $app, array $config): \Closure
    {
        return function (DataConverterInterface $dc, EnvironmentInterface $env) use ($app, $config) {
            $factory = WorkerFactory::create($dc, Goridge::create($env));

            foreach ((array)($config['workers'] ?? []) as $queue => $workerConfig) {
                $worker = $factory->newWorker($queue, $this->createOptions(
                    (array)($workerConfig['options'] ?? [])
                ));

                foreach ((array)($workerConfig['workflows'] ?? []) as $class) {
                    $worker->registerWorkflowTypes($class);
                }

                foreach ((array)($workerConfig['activities'] ?? []) as $class) {
                    $worker->registerActivityImplementations(
                        $app->get($class)
                    );
                }
            }

            return $factory;
        };
    }

    /**
     * @param WorkerConfigSectionOptions $options
     * @return WorkerOptions
     */
    private function createOptions(array $options): WorkerOptions
    {
        $config = WorkerOptions::new();

        if (isset($options['maxConcurrentActivityExecutionSize'])) {
            $config = $config->withMaxConcurrentActivityExecutionSize(
                $options['maxConcurrentActivityExecutionSize']
            );
        }

        if (isset($options['workerActivitiesPerSecond'])) {
            $config = $config->withWorkerActivitiesPerSecond(
                $options['workerActivitiesPerSecond']
            );
        }

        if (isset($options['maxConcurrentLocalActivityExecutionSize'])) {
            $config = $config->withMaxConcurrentLocalActivityExecutionSize(
                $options['maxConcurrentLocalActivityExecutionSize']
            );
        }

        if (isset($options['workerLocalActivitiesPerSecond'])) {
            $config = $config->withWorkerLocalActivitiesPerSecond(
                $options['workerLocalActivitiesPerSecond']
            );
        }

        if (isset($options['taskQueueActivitiesPerSecond'])) {
            $config = $config->withTaskQueueActivitiesPerSecond(
                $options['taskQueueActivitiesPerSecond']
            );
        }

        if (isset($options['maxConcurrentActivityTaskPollers'])) {
            $config = $config->withMaxConcurrentActivityTaskPollers(
                $options['maxConcurrentActivityTaskPollers']
            );
        }

        if (isset($options['maxConcurrentWorkflowTaskExecutionSize'])) {
            $config = $config->withMaxConcurrentWorkflowTaskExecutionSize(
                $options['maxConcurrentWorkflowTaskExecutionSize']
            );
        }

        if (isset($options['maxConcurrentWorkflowTaskPollers'])) {
            $config = $config->withMaxConcurrentWorkflowTaskPollers(
                $options['maxConcurrentWorkflowTaskPollers']
            );
        }

        if (isset($options['stickyScheduleToStartTimeout'])) {
            $config = $config->withStickyScheduleToStartTimeout(
                $options['stickyScheduleToStartTimeout']
            );
        }

        if (isset($options['workerStopTimeout'])) {
            $config = $config->withWorkerStopTimeout(
                $options['workerStopTimeout']
            );
        }

        if (isset($options['enableSessionWorker'])) {
            $config = $config->withEnableSessionWorker(
                $options['enableSessionWorker']
            );
        }

        if (isset($options['sessionResourceId'])) {
            $config = $config->withSessionResourceId(
                $options['sessionResourceId']
            );
        }

        if (isset($options['maxConcurrentSessionExecutionSize'])) {
            $config = $config->withMaxConcurrentSessionExecutionSize(
                $options['maxConcurrentSessionExecutionSize']
            );
        }

        return $config;
    }

    /**
     * @return \Closure
     */
    private function defaultClientRegistrar(): \Closure
    {
        return static function (ClientManagerInterface $manager): WorkflowClientInterface {
            return $manager->get();
        };
    }

    /**
     * @param TemporalConfigArray $config
     * @return \Closure
     */
    private function clientManagerRegistrar(array $config): \Closure
    {
        return function () use ($config): ClientManagerInterface {
            $manager = new ClientManager(
                \is_string($config['client'] ?? null) ? $config['client'] : null
            );

            foreach ((array)($config['clients'] ?? []) as $name => $clientConfig) {
                $manager->addResolver($name, $this->createClientResolver((array)$clientConfig));
            }

            return $manager;
        };
    }

    /**
     * @param array $config
     * @return \Closure
     */
    private function createClientResolver(array $config): \Closure
    {
        return static function () use ($config): WorkflowClientInterface {
            $client = ServiceClient::create($config['host'] ?? 'localhost:7233');
            $options = new ClientOptions();

            if (isset($config['namespace'])) {
                $options = $options->withNamespace($config['namespace']);
            }

            if (isset($config['identity'])) {
                $options = $options->withIdentity($config['identity']);
            }

            if (isset($config['queryRejectionCondition'])) {
                $options = $options->withQueryRejectionCondition($config['queryRejectionCondition']);
            }

            return WorkflowClient::create($client, $options);
        };
    }
}
