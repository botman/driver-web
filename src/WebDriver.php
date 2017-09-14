<?php

namespace BotMan\Drivers\Web;

use BotMan\BotMan\Users\User;
use Illuminate\Support\Collection;
use BotMan\BotMan\Drivers\HttpDriver;
use BotMan\BotMan\Interfaces\WebAccess;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Question;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use BotMan\BotMan\Messages\Outgoing\OutgoingMessage;

class WebDriver extends HttpDriver
{
    const DRIVER_NAME = 'Web';

    /** @var OutgoingMessage[] */
    protected $replies = [];

    /** @var int */
    protected $replyStatusCode = 200;

    /** @var string */
    protected $errorMessage = '';

    /** @var array */
    protected $messages = [];

    /**
     * @param Request $request
     */
    public function buildPayload(Request $request)
    {
        $this->payload = $request->request->all();
        $this->event = Collection::make($this->payload);
        $this->config = Collection::make($this->config->get('web', []));
    }

    /**
     * @param IncomingMessage $matchingMessage
     * @return \BotMan\BotMan\Users\User
     */
    public function getUser(IncomingMessage $matchingMessage)
    {
        return new User($matchingMessage->getSender());
    }

    /**
     * Determine if the request is for this driver.
     *
     * @return bool
     */
    public function matchesRequest()
    {
        return Collection::make($this->config->get('matchingData'))->diffAssoc($this->event)->isEmpty();
    }

    /**
     * @param  IncomingMessage $message
     * @return \BotMan\BotMan\Messages\Incoming\Answer
     */
    public function getConversationAnswer(IncomingMessage $message)
    {
        return Answer::create($message->getText())->setMessage($message);
    }

    /**
     * Retrieve the chat message.
     *
     * @return array
     */
    public function getMessages()
    {
        if (empty($this->messages)) {
            $message = $this->event->get('message');
            $userId = $this->event->get('userId');
            $this->messages = [new IncomingMessage($message, $userId, $userId, $this->payload)];
        }

        return $this->messages;
    }

    /**
     * @return bool
     */
    public function isBot()
    {
        return false;
    }

    /**
     * @param string|Question|OutgoingMessage $message
     * @param IncomingMessage $matchingMessage
     * @param array $additionalParameters
     * @return Response
     */
    public function buildServicePayload($message, $matchingMessage, $additionalParameters = [])
    {
        if (! $message instanceof WebAccess && ! $message instanceof OutgoingMessage) {
            $this->errorMessage = 'Unsupported message type.';
            $this->replyStatusCode = 500;
        }

        return $message;
    }

    /**
     * @param mixed $payload
     * @return Response
     */
    public function sendPayload($payload)
    {
        $this->replies[] = $payload;
    }

    /**
     * @param $messages
     * @return array
     */
    protected function buildReply($messages)
    {
        $replyData = Collection::make($messages)->transform(function ($message) {
            $reply = [];

            if ($message instanceof WebAccess) {
                $reply = $message->toWebDriver();
            } elseif ($message instanceof OutgoingMessage) {
                $attachmentData = (is_null($message->getAttachment())) ? null : $message->getAttachment()->toWebDriver();
                $reply = [
                    'type' => 'text',
                    'text' => $message->getText(),
                    'attachment' => $attachmentData,
                ];
            }

            return $reply;
        })->toArray();

        return $replyData;
    }

    /**
     * Send out message response.
     */
    public function messagesHandled()
    {
        $messages = $this->buildReply($this->replies);

        // Reset replies
        $this->replies = [];

        Response::create(json_encode([
            'status' => $this->replyStatusCode,
            'messages' => $messages,
        ]), $this->replyStatusCode, [
            'Content-Type' => 'application/json',
            'Access-Control-Allow-Credentials' => true,
            'Access-Control-Allow-Origin' => '*',
        ])->send();
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        return false;
    }

    /**
     * Low-level method to perform driver specific API requests.
     *
     * @param string $endpoint
     * @param array $parameters
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage $matchingMessage
     * @return void
     */
    public function sendRequest($endpoint, array $parameters, IncomingMessage $matchingMessage)
    {
        // Not available with the web driver.
    }
}
