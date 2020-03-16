<?php

namespace App\Command;

use Cassandra\Date;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Yoeunes\Notify\Config\Config;
use Yoeunes\Notify\Middleware\AddCreatedAtStampMiddleware;
use Yoeunes\Notify\Middleware\AddPriorityStampMiddleware;
use Yoeunes\Notify\Middleware\MiddlewareManager;
use Yoeunes\Notify\Presenter\CliPresenter;
use Yoeunes\Notify\Presenter\HtmlPresenter;
use Yoeunes\Notify\Presenter\JsonPresenter;
use Yoeunes\Notify\Presenter\PresenterManager;
use Yoeunes\Notify\Producer\ProducerManager;
use Yoeunes\Notify\Renderer\RendererManager;
use Yoeunes\Notify\Storage\ArrayStorage;
use Yoeunes\Notify\Storage\StorageManager;
use Yoeunes\Notify\Toastr\Producer\ToastrProducer;
use Yoeunes\Notify\Toastr\Renderer\ToastrRenderer;

class NotifyCommand extends Command
{
    protected static $defaultName = 'notify';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = new Config(array(
            'default' => 'toastr',
            'renderers' => array(
                'toastr' => array(
                    'scripts' => array('jquery.js', 'toastr.js'),
                    'styles' => array('toastr.css'),
                    'options' => array()
                )
            ),
            'storage' => 'array',
            'envelope_middleware' => array(
                '\Yoeunes\Notify\Middleware\AddCreatedAtStampMiddleware',
                '\Yoeunes\Notify\Middleware\AddPriorityStampMiddleware'
            ),
            'filters' => array(
                'default' => array(
                    'max_results' => 3,
                    'priority' => array(
                        'min' => 0,
                        'max' => 5
                    ),
                    'created_at' => array(
                        'min' => 'now',
                        'max' => 'now + 2minutes'
                    ),
                    'sort' => array(
                        'priority' => 'asc',
                        'created_at' => 'desc'
                    )
                )
            )
        ));

        $storageManager = new StorageManager($config);
        $storageManager->addDriver('array', new ArrayStorage());
        $arrayStorage = $storageManager->make('array');

        $middlewareList = array_map(function ($middleware) {
            return new $middleware;
        }, $config->get('envelope_middleware'));
        $middleware = new MiddlewareManager($middlewareList);

        $producerManager = new ProducerManager($config);
        $producerManager->addDriver('toastr', new ToastrProducer($arrayStorage, $middleware));
        $producerManager->make('toastr')->success('hello success');
        $producerManager->make('toastr')->error('hello error');

        $rendererManager = new RendererManager($config);
        $rendererManager->addDriver('toastr', new ToastrRenderer(
            $config->get('renderers.toastr.scripts'),
            $config->get('renderers.toastr.styles'),
            $config->get('renderers.toastr.options')
        ));

        $presenterManager = new PresenterManager($config);
        $presenterManager->addDriver('html', new HtmlPresenter($arrayStorage, $rendererManager));
        $presenterManager->addDriver('json', new JsonPresenter($arrayStorage, $rendererManager));
        $presenterManager->addDriver('cli', new CliPresenter($arrayStorage));

        dd($presenterManager->make('cli')->render());

        return 0;
    }
}
