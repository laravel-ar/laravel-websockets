<?php

namespace BeyondCode\LaravelWebsockets\Tests\Channels;

use BeyondCode\LaravelWebSockets\Tests\Mocks\Message;
use BeyondCode\LaravelWebSockets\Tests\TestCase;

class ChannelTest extends TestCase
{
    /** @test */
    public function clients_can_subscribe_to_channels()
    {
        $connection = $this->getWebSocketConnection();

        $message = new Message(json_encode([
            'event' => 'pusher:subscribe',
            'data' => [
                'channel' => 'basic-channel'
            ],
        ]));

        $this->pusherServer->onOpen($connection);

        $this->pusherServer->onMessage($connection, $message);

        $connection->assertSentEvent('pusher_internal:subscription_succeeded', [
            'channel' => 'basic-channel'
        ]);
    }

    /** @test */
    public function client_messages_get_broadcasted_to_other_clients_in_the_same_channel()
    {
        // One connection inside channel "test-channel".
        $existingConnection = $this->getConnectedWebSocketConnection(['test-channel']);

        $connection = $this->getConnectedWebSocketConnection(['test-channel']);

        $message = new Message('{"event": "client-test", "data": {}, "channel": "test-channel"}');

        $this->pusherServer->onMessage($connection, $message);

        $existingConnection->assertSentEvent('client-test');
    }

    /** @test */
    public function closed_connections_get_removed_from_all_connected_channels()
    {
        $connection = $this->getConnectedWebSocketConnection(['test-channel-1', 'test-channel-2']);

        $channel1 = $this->getChannel($connection, 'test-channel-1');
        $channel2 = $this->getChannel($connection, 'test-channel-2');

        $this->assertTrue($channel1->hasConnections());
        $this->assertTrue($channel2->hasConnections());

        $this->pusherServer->onClose($connection);

        $this->assertFalse($channel1->hasConnections());
        $this->assertFalse($channel2->hasConnections());
    }

    /** @test */
    public function channels_can_broadcast_messages_to_all_connections()
    {
        $connection1 = $this->getConnectedWebSocketConnection(['test-channel']);
        $connection2 = $this->getConnectedWebSocketConnection(['test-channel']);

        $channel = $this->getChannel($connection1, 'test-channel');

        $channel->broadcast([
            'event' => 'broadcasted-event',
            'channel' => 'test-channel'
        ]);

        $connection1->assertSentEvent('broadcasted-event');
        $connection2->assertSentEvent('broadcasted-event');
    }

    /** @test */
    public function channels_can_broadcast_messages_to_all_connections_except_the_given_connection()
    {
        $connection1 = $this->getConnectedWebSocketConnection(['test-channel']);
        $connection2 = $this->getConnectedWebSocketConnection(['test-channel']);

        $channel = $this->getChannel($connection1, 'test-channel');

        $channel->broadcastToEveryoneExcept([
            'event' => 'broadcasted-event',
            'channel' => 'test-channel'
        ], $connection1->socketId);

        $connection1->assertNotSentEvent('broadcasted-event');
        $connection2->assertSentEvent('broadcasted-event');
    }
}