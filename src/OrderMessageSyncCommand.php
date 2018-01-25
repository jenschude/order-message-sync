<?php
/**
 * @author @jayS-de <jens.schulze@commercetools.de>
 */

namespace Commercetools\IronIO\MessageSync;

use Commercetools\Core\Model\Channel\ChannelDraft;
use Commercetools\Core\Model\Channel\ChannelReference;
use Commercetools\Core\Model\Channel\ChannelRole;
use Commercetools\Core\Model\Subscription\Delivery;
use Commercetools\Core\Model\Subscription\ResourceCreatedDelivery;
use Commercetools\Core\Request\Channels\ChannelCreateRequest;
use Commercetools\Core\Request\Channels\ChannelQueryRequest;
use Commercetools\Core\Request\Orders\Command\OrderUpdateSyncInfoAction;
use Commercetools\Core\Request\Orders\OrderByIdGetRequest;
use Commercetools\Core\Request\Orders\OrderUpdateRequest;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class OrderMessageSyncCommand extends IronCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            // the name of the command (the part after "bin/console")
            ->setName('order:message-sync')

            // the short description shown while running "php bin/console list"
            ->setDescription('Exports an order')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = $this->getConfigLoader()->load($input);
        $client = $this->getCtpClient($config);

        $payload = $this->getPayloadLoader()->load($input);
        $delivery = Delivery::fromArray($payload);

        $type = $delivery->getResource()->getTypeId();

        switch (true) {
            case $delivery instanceof ResourceCreatedDelivery:
                switch ($type) {
                    case 'order':
                        echo 'export order: ' . $delivery->getResource()->getId();
                        $request = OrderByIdGetRequest::ofId($delivery->getResource()->getId());
                        $response = $request->executeWithClient($client);
                        $order = $request->mapFromResponse($response);

                        $request = OrderUpdateRequest::ofIdAndVersion($order->getId(), $order->getVersion());
                        $channelId = $this->getChannel($client);
                        $request->addAction(OrderUpdateSyncInfoAction::ofChannel(ChannelReference::ofId($channelId)));

                        $request->executeWithClient($client);

                        break;
                }
                break;
        }


        echo 'done' . PHP_EOL;
    }

    private function getChannel($client)
    {
        $cache = $this->getCache();
        $channelItem = $cache->getItem('order_export_channel');

        if (!$channelItem->isHit()) {
            $channel = $this->getChannelByName($client, 'order-export');

            if (is_null($channel)) {
                $request = ChannelCreateRequest::ofDraft(
                    ChannelDraft::ofKey('order-export')->setRoles([ChannelRole::ORDER_EXPORT])
                );
                $response = $request->executeWithClient($client);
                $channel = $request->mapFromResponse($response);

                if (is_null($channel)) {
                    $channel = $this->getChannelByName($client, 'order-export');
                }
            }
            $channelItem->set($channel->getId());
            $cache->save($channelItem);
        }
        return $channelItem->get();
    }

    private function getChannelByName($client, $name) {
        $request = ChannelQueryRequest::of()->where('key="' . $name . '"')->limit(1);
        $response = $request->executeWithClient($client);
        $channel = $request->mapFromResponse($response);

        return $channel->current();
    }
}
