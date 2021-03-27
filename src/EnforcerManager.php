<?php

declare(strict_types=1);

namespace EasySwoole\Permission;

use EasySwoole\EasySwoole\Config;
use InvalidArgumentException;
use Casbin\Bridge\Logger\LoggerBridge;
use Casbin\Enforcer;
use Casbin\Model\Model;
use Casbin\Log\Log;
use Casbin\Persist\Adapter;
use EasySwoole\EasySwoole\Logger;
use EasySwoole\Permission\Adapters\DatabaseAdapter;

class EnforcerManager
{
    /**
     * @var Enforcer
     */
    protected $enforcer;

    /**
     * @var Adapter
     */
    protected $adapter;

    /**
     * @var Model
     */
    protected $model;

    /**
     * @var Config
     */
    protected $config;

    /**
     * The array of created "guards".
     *
     * @var array
     */
    protected $guards = [];

    /**
     * @return Adapter|null
     */
    public function getAdapter(): ?Adapter
    {
        return $this->adapter;
    }

    public function __construct()
    {
        $this->config = $this->getConfig($this->getDefaultGuard());

        $adapter = $config['adapter'] ?? null;
        if (!is_null($adapter)) {
            if (!$this->getAdapter() instanceof Adapter) {
                $this->adapter = new DatabaseAdapter();
            }
        }

        $this->model = new Model();

        $configType = $this->config['model']['config_type'] ?? null;
        if ('file' == $configType) {
            $this->model->loadModel($this->config['model']['config_file_path'] ?? '');
        } elseif ('text' == $configType) {
            $this->model->loadModelFromText($this->config['model']['config_text'] ?? '');
        }
    }

    public function enforcer($newInstance = false)
    {
        if ($newInstance || is_null($this->enforcer)) {
            $this->enforcer = new Enforcer($this->model, $this->adapter, $this->isLogEnable());
        }

        return $this->enforcer;
    }

    /**
     * Resolve the given guard.
     *
     * @param string $name
     *
     * @return \Casbin\Enforcer
     *
     * @throws \InvalidArgumentException
     */
    protected function resolve($name)
    {
        if (is_null($this->config)) {
            throw new InvalidArgumentException("Enforcer [{$name}] is not defined.");
        }

        if ($logger = $this->config['log']['logger'] ?? null) {
            if (is_string($logger)) {
                $logger = Logger::getInstance();
            }
            Log::setLogger(new LoggerBridge($logger));
        }

        return $this->enforcer();
    }

    /**
     * @return string
     */
    public function getModelConfigText(): string
    {
        return $this->config['model']['config_text'] ?? '';
    }

    /**
     * @param string $model_config_text
     */
    public function setModelConfigText(string $model_config_text): void
    {
        $this->config['model']['config_text'] = $model_config_text;
        $this->config->setConf('DATABASE.host', 'localhost');
    }

    /**
     * Attempt to get the enforcer from the local cache.
     *
     * @param string $name
     *
     * @return \Casbin\Enforcer
     *
     * @throws \InvalidArgumentException
     */
    public function guard($name = null)
    {
        $name = $name ?: $this->getDefaultGuard();

        if (!isset($this->guards[$name])) {
            $this->guards[$name] = $this->resolve($name);
        }

        return $this->guards[$name];
    }

    /**
     * @param Adapter $adapter
     */
    public function setAdapter(Adapter $adapter): void
    {
        $this->adapter = $adapter;
    }

    /**
     * Get the easyswoole-permission driver configuration.
     *
     * @param string $name
     *
     * @return array
     */
    protected function getConfig($name): array
    {
        $config = Config::getInstance();
        return $config->getConf("EASYSWOOLE_PERMISSION.{$name}");
    }

    /**
     * Get the default enforcer guard name.
     *
     * @return string
     */
    public function getDefaultGuard(): string
    {
        $config = Config::getInstance();
        return $config->getConf("EASYSWOOLE_PERMISSION.default");
    }

    /**
     * Set the default authorization guard name.
     *
     * @param string $name
     */
    public function setDefaultGuard($name)
    {
        $config = Config::getInstance();
        $config->setConf("EASYSWOOLE_PERMISSION.default", $name);
    }

    /**
     * @return bool
     */
    public function isLogEnable(): bool
    {
        // return $this->log_enable;
    }

    /**
     * Dynamically call the default driver instance.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call($name, $params): mixed
    {
        return call_user_func_array([$this->enforcer(), $name], $params);
    }
}