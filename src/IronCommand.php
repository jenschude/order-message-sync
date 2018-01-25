<?php
/**
 * @author @jayS-de <jens.schulze@commercetools.de>
 */

namespace Commercetools\IronIO\MessageSync;

use Cache\Adapter\Filesystem\FilesystemCachePool;
use Commercetools\Core\Client;
use Commercetools\Core\Config;
use Commercetools\Core\Model\Common\Context;
use GuzzleHttp\Client as HttpClient;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;

abstract class IronCommand extends Command
{
    const DEFAULT_PAGE_SIZE = 500;
    const CTP_CLIENT_ID = 'CTP_CLIENT_ID';
    const CTP_CLIENT_SECRET = 'CTP_CLIENT_SECRET';
    const CTP_PROJECT = 'CTP_PROJECT';
    const CTP_SCOPE = 'CTP_SCOPE';

    private $configLoader;
    private $payloadLoader;
    private $ironLoader;
    private $cache;

    public function __construct($name = null)
    {
        parent::__construct($name);

        $this->configLoader = new ConfigLoader('config', 'CONFIG_FILE');
        $this->payloadLoader = new ConfigLoader('payload', 'PAYLOAD_FILE');
        $this->ironLoader = new ConfigLoader('iron-file', 'IRON_FILE');

    }

    protected function configure()
    {
        $this
            ->addOption('iron-file', 'i', InputArgument::OPTIONAL, '', __DIR__ . '/../iron.json')
            ->addOption('config', 'c', InputArgument::OPTIONAL, '', __DIR__ . '/../config.json')
            ->addOption('payload', 'p', InputArgument::OPTIONAL, '', __DIR__ . '/../payload.json')
        ;
    }

    /**
     * @return ConfigLoader
     */
    public function getIronLoader()
    {
        return $this->ironLoader;
    }

    public function getConfigLoader()
    {
        return $this->configLoader;
    }

    public function getPayloadLoader()
    {
        return $this->payloadLoader;
    }

    /**
     * @return LoggerInterface
     */
    private function getLogger()
    {
        $logger = new Logger($this->getName());
        $logger->pushHandler(new ErrorLogHandler());

        return $logger;
    }

    private function getCtpConfig(array $config)
    {
        $clientConfig = [
            'client_id' => getenv(static::CTP_CLIENT_ID) ? getenv(static::CTP_CLIENT_ID) : $config[static::CTP_CLIENT_ID],
            'client_secret' => getenv(static::CTP_CLIENT_SECRET) ? getenv(static::CTP_CLIENT_SECRET) : $config[static::CTP_CLIENT_SECRET],
            'project' => getenv(static::CTP_PROJECT) ? getenv(static::CTP_PROJECT) : $config[static::CTP_PROJECT],
            'scope' => getenv(static::CTP_SCOPE) ? getenv(static::CTP_SCOPE) : (isset($config[static::CTP_SCOPE]) ? $config[static::CTP_SCOPE] : 'manage_project')
        ];
        return $clientConfig;
    }

    /**
     * @param array $config
     * @return Client
     */
    protected function getCtpClient(array $config)
    {
        $clientConfig = $this->getCtpConfig($config);

        $context = Context::of()->setLanguages(['en'])->setGraceful(true);

        // create the api client config object
        $config = Config::fromArray($clientConfig)->setContext($context);


        $logger = $this->getLogger();

        $client = Client::ofConfigCacheAndLogger($config, $this->getCache(), $logger);
        return $client;
    }

    /**
     * @return CacheItemPoolInterface
     */
    protected function getCache()
    {
        if (is_null($this->cache)) {
            $filesystemAdapter = new Local(__DIR__.'/');
            $filesystem        = new Filesystem($filesystemAdapter);
            $this->cache = new FilesystemCachePool($filesystem);
        }

        return $this->cache;
    }
}
