<?php
/**
 * This file is a part of Woketo package.
 *
 * (c) Ci-tron <dev@ci-tron.org>
 *
 * For the full license, take a look to the LICENSE file
 * on the root directory of this project
 */

namespace Nekland\Woketo\Rfc6455;

use Nekland\Woketo\Exception\LimitationException;
use Nekland\Woketo\Rfc6455\MessageHandler\Rfc6455MessageHandlerInterface;
use Nekland\Woketo\Utils\BitManipulation;
use React\Socket\ConnectionInterface;

/**
 * Class MessageProcessor
 *
 * This class is only a helper for Connection to avoid having so much instances of classes in memory.
 * Using it like that allow us to have only one instance of MessageProcessor.
 */
class MessageProcessor
{
    /**
     * @var FrameFactory
     */
    private $frameFactory;

    /**
     * @var Rfc6455MessageHandlerInterface[]
     */
    private $handlers;

    public function __construct(FrameFactory $factory = null)
    {
        $this->frameFactory = $factory ?: new FrameFactory();
        $this->handlers = [];
    }

    /**
     * @param string              $data
     * @param ConnectionInterface $socket
     * @param Message|null        $message
     * @return Message|null
     */
    public function onData($data, ConnectionInterface $socket, Message $message = null)
    {
        if (null === $message) {
            $message = new Message();
        }

        try {
            $message->addData($data);
            if ($message->isComplete()) {
                foreach ($this->handlers as $handler) {
                    if ($handler->supports($message)) {
                        $handler->process($message, $this, $socket);
                        return null;
                    }
                }
            }

            return $message;
        } catch (LimitationException $e) {
            $this->write($this->frameFactory->createCloseFrame(Frame::CLOSE_TOO_BIG_TO_PROCESS), $socket);
            $socket->end();
        }

        return null;
    }

    /**
     * @param Rfc6455MessageHandlerInterface $handler
     * @return self
     */
    public function addHandler(Rfc6455MessageHandlerInterface $handler)
    {
        $this->handlers[] = $handler;

        return $this;
    }

    /**
     * @param Frame|string        $frame
     * @param ConnectionInterface $socket
     */
    public function write($frame, ConnectionInterface $socket)
    {
        if (!$frame instanceof Frame) {
            $data = $frame;
            $frame = new Frame();
            $frame->setPayload($data);
            $frame->setOpcode(Frame::OP_TEXT);
        }

        $socket->write($frame->getRawData());
    }

    /**
     * @return FrameFactory
     */
    public function getFrameFactory(): FrameFactory
    {
        return $this->frameFactory;
    }

    /**
     * @param ConnectionInterface $socket
     */
    public function timeout(ConnectionInterface $socket)
    {
        $this->write($this->frameFactory->createCloseFrame(Frame::CLOSE_PROTOCOL_ERROR), $socket);
        $socket->close();
    }

    /**
     * @param ConnectionInterface $socket
     */
    public function close(ConnectionInterface $socket)
    {
        $this->write($this->frameFactory->createCloseFrame(), $socket);
    }
}