<?php
/**
 * This file is a part of Woketo package.
 *
 * (c) Nekland <dev@nekland.fr>
 *
 * For the full license, take a look to the LICENSE file
 * on the root directory of this project
 */

namespace Nekland\Woketo\Rfc6455\MessageHandler;


use Nekland\Woketo\Rfc6455\Frame;
use Nekland\Woketo\Rfc6455\Message;
use Nekland\Woketo\Rfc6455\MessageProcessor;
use React\Socket\ConnectionInterface;

class RsvCheckFrameHandler implements Rfc6455MessageHandlerInterface
{
    public function supports(Message $message)
    {
        foreach ($message->getFrames() as $frame) {
            if ($frame->getRsv1() || $frame->getRsv2() || $frame->getRsv3()) {
                return true;
            }
        }

        return false;
    }

    public function process(Message $message, MessageProcessor $messageProcessor, ConnectionInterface $socket)
    {
        $messageProcessor->write($messageProcessor->getFrameFactory()->createCloseFrame(Frame::CLOSE_PROTOCOL_ERROR), $socket);
        $socket->end();
    }
}
