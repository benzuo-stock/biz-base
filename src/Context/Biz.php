<?php

namespace Benzuo\Biz\Base\Context;

use Benzuo\Biz\Base\Dao\DaoProxy;
use Benzuo\Biz\Base\Dao\FieldSerializer;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

class Biz extends Container
{
    protected $providers = array();
    protected $booted = false;

    public function __construct(array $values = array())
    {
        parent::__construct();

        $this['debug'] = false;
        $this['migration.directories'] = new \ArrayObject();

        $this['dao.serializer'] = function () {
            return new FieldSerializer();
        };

        $this['dao.cache.adapter'] = null;

        $this['dispatcher'] = function () {
            return new EventDispatcher();
        };

        $this['autoload.aliases'] = new \ArrayObject(array('' => 'Biz'));

        $this['autoload.object_maker.service'] = function ($biz) {
            return function ($namespace, $name) use ($biz) {
                $class = "{$namespace}\\Service\\Impl\\{$name}Impl";
                return new $class($biz);
            };
        };

        $this['autoload.object_maker.dao'] = function ($biz) {
            return function ($namespace, $name) use ($biz) {
                $class = "{$namespace}\\Dao\\Impl\\{$name}Impl";
                return new DaoProxy(new $class($biz), $this['dao.serializer'], $this['dao.cache.adapter']);
            };
        };

        $this['autoloader'] = function ($biz) {
            return new ContainerAutoloader(
                $biz,
                $biz['autoload.aliases'],
                array(
                    'service' => $biz['autoload.object_maker.service'],
                    'dao' => $biz['autoload.object_maker.dao'],
                )
            );
        };

        foreach ($values as $key => $value) {
            $this[$key] = $value;
        }
    }

    public function register(ServiceProviderInterface $provider, array $values = array())
    {
        $this->providers[] = $provider;
        parent::register($provider, $values);

        return $this;
    }

    public function boot()
    {
        if (true === $this->booted) {
            return;
        }

        foreach ($this->providers as $provider) {
            if ($provider instanceof EventListenerProviderInterface) {
                $provider->subscribe($this, $this['dispatcher']);
            }

            if ($provider instanceof BootableProviderInterface) {
                $provider->boot($this);
            }
        }

        $this->booted = true;
    }


    public function service($alias)
    {
        return $this['autoloader']->autoload('service', $alias);
    }

    public function dao($alias)
    {
        return $this['autoloader']->autoload('dao', $alias);
    }
}
