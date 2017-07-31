<?php

namespace Tests;

use Mockery as m;
use BotMan\BotMan\Http\Curl;
use PHPUnit_Framework_TestCase;
use BotMan\Drivers\Web\WebDriver;
use BotMan\BotMan\Messages\Attachments\Image;
use BotMan\BotMan\Messages\Outgoing\Question;
use Symfony\Component\HttpFoundation\Request;
use BotMan\Drivers\Facebook\Extensions\Element;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use BotMan\BotMan\Messages\Outgoing\OutgoingMessage;
use BotMan\Drivers\Facebook\Extensions\ListTemplate;
use BotMan\Drivers\Facebook\Extensions\ElementButton;
use BotMan\Drivers\Facebook\Extensions\ButtonTemplate;
use BotMan\Drivers\Facebook\Extensions\ReceiptAddress;
use BotMan\Drivers\Facebook\Extensions\ReceiptElement;
use BotMan\Drivers\Facebook\Extensions\ReceiptSummary;
use BotMan\Drivers\Facebook\Extensions\GenericTemplate;
use BotMan\Drivers\Facebook\Extensions\ReceiptTemplate;
use BotMan\Drivers\Facebook\Extensions\ReceiptAdjustment;

class WebDriverTest extends PHPUnit_Framework_TestCase
{
    /**
     * @param $responseData
     * @param null $htmlInterface
     * @return WebDriver
     */
    private function getDriver($responseData, $htmlInterface = null)
    {
        $request = Request::create('', 'POST', $responseData);
        if ($htmlInterface === null) {
            $htmlInterface = m::mock(Curl::class);
        }

        return new WebDriver($request, [], $htmlInterface);
    }

    /** @test */
    public function it_returns_the_driver_name()
    {
        $driver = $this->getDriver([]);
        $this->assertSame('Web', $driver->getName());
    }

    /** @test */
    public function it_matches_the_request()
    {
        $driver = $this->getDriver([
            'driver' => 'api',
            'message' => 'Hi Julia',
            'userId' => '12345',
        ]);
        $this->assertFalse($driver->matchesRequest());

        $driver = $this->getDriver([
            'driver' => 'web',
            'message' => 'Hi Julia',
            'userId' => '12345',
        ]);
        $this->assertTrue($driver->matchesRequest());
    }

    /** @test */
    public function it_returns_the_message_object()
    {
        $driver = $this->getDriver([
            'driver' => 'web',
            'message' => 'Hi Julia',
            'userId' => '12345',
        ]);
        $this->assertTrue(is_array($driver->getMessages()));
    }

    /** @test */
    public function it_returns_the_message_text()
    {
        $driver = $this->getDriver([
            'driver' => 'web',
            'message' => 'Hi Julia',
            'userId' => '12345',
        ]);
        $this->assertSame('Hi Julia', $driver->getMessages()[0]->getText());
    }

    /** @test */
    public function it_returns_the_user_id()
    {
        $driver = $this->getDriver([
            'driver' => 'web',
            'message' => 'Hi Julia',
            'userId' => '12345',
        ]);
        $this->assertSame('12345', $driver->getMessages()[0]->getSender());
    }

    /** @test */
    public function it_can_reply_string_messages()
    {
        $driver = $this->getDriver([
            'driver' => 'web',
            'message' => 'Hi Julia',
            'userId' => '12345',
        ]);

        $message = new IncomingMessage('', '', '1234567890');

        $payload = $driver->buildServicePayload(new OutgoingMessage('Test one From API'), $message);
        $driver->sendPayload($payload);
        $payload = $driver->buildServicePayload(new OutgoingMessage('Test two From API'), $message);
        $driver->sendPayload($payload);
        $driver->messagesHandled();

        $this->expectOutputString('{"status":200,"messages":[{"type":"text","text":"Test one From API","attachment":null},{"type":"text","text":"Test two From API","attachment":null}]}');
    }

    /** @test */
    public function it_can_message_attachments()
    {
        $driver = $this->getDriver([
            'driver' => 'web',
            'message' => 'Hi Julia',
            'userId' => '12345',
        ]);

        $message = new IncomingMessage('', '', '1234567890');

        $outgoing = OutgoingMessage::create('Test one From API')->withAttachment(Image::url('some-image'));
        $payload = $driver->buildServicePayload($outgoing, $message);
        $driver->sendPayload($payload);
        $driver->messagesHandled();

        $this->expectOutputString('{"status":200,"messages":[{"type":"text","text":"Test one From API","attachment":{"url":"some-image","title":null}}]}');
    }

    /**
     * @test
     **/
    public function it_replies_to_question_object()
    {
        $driver = $this->getDriver([
            'driver' => 'web',
            'message' => 'Hi Julia',
            'userId' => '12345',
        ]);

        $message = new IncomingMessage('', '', '1234567890');
        $question = Question::create('What do want to do?')
            ->addButton(Button::create('Stay')->image('https://test.com/image.png')->value('stay'))
            ->addButton(Button::create('Leave'));
        $payload = $driver->buildServicePayload($question, $message);
        $driver->sendPayload($payload);
        $driver->messagesHandled();

        $json = $question->toWebDriver();
        $this->expectOutputString('{"status":200,"messages":['.json_encode($json).']}');
    }

    /**
     * @test
     **/
    public function it_replies_to_facebook_button_template()
    {
        $driver = $this->getDriver([
            'driver' => 'web',
            'message' => 'Hi Julia',
            'userId' => '12345',
        ]);

        $message = new IncomingMessage('', '', '1234567890');
        $template = ButtonTemplate::create('How do you like BotMan so far?')
            ->addButton(ElementButton::create('Quite good')->type('postback')->payload('good'))
            ->addButton(ElementButton::create('Love it!')->url('https://test.at'));

        $payload = $driver->buildServicePayload($template, $message);
        $driver->sendPayload($payload);
        $driver->messagesHandled();

        $json = $template->toWebDriver();
        $this->expectOutputString('{"status":200,"messages":['.json_encode($json).']}');
    }

    /**
     * @test
     **/
    public function it_replies_to_facebook_list_template()
    {
        $driver = $this->getDriver([
            'driver' => 'web',
            'message' => 'Hi Julia',
            'userId' => '12345',
        ]);

        $message = new IncomingMessage('', '', '1234567890');
        $template = ListTemplate::create()
            ->useCompactView()
            ->addGlobalButton(
                ElementButton::create('view more')
                    ->url('http://test.at'))
            ->addElement(
                Element::create('BotMan Documentation')
                    ->subtitle('All about BotMan')
                    ->image('http://botman.io/img/botman-body.png')
                    ->addButton(ElementButton::create('tell me more')->payload('tellmemore')->type('postback')))
            ->addElement(
                Element::create('BotMan Laravel Starter')
                    ->image('http://botman.io/img/botman-body.png')
                    ->addButton(ElementButton::create('visit')->url('https://github.com/mpociot/botman-laravel-starter'))
            );

        $payload = $driver->buildServicePayload($template, $message);
        $driver->sendPayload($payload);
        $driver->messagesHandled();

        $json = $template->toWebDriver();
        $this->expectOutputString('{"status":200,"messages":['.json_encode($json).']}');
    }

    /**
     * @test
     **/
    public function it_replies_to_facebook_generic_template()
    {
        $driver = $this->getDriver([
            'driver' => 'web',
            'message' => 'Hi Julia',
            'userId' => '12345',
        ]);

        $message = new IncomingMessage('', '', '1234567890');
        $template = GenericTemplate::create()
            ->addElements([
                Element::create('BotMan Documentation')
                    ->itemUrl('http://botman.io/')
                    ->image('http://screenshots.nomoreencore.com/botman2.png')
                    ->subtitle('All about BotMan')
                    ->addButton(ElementButton::create('visit')->url('http://botman1.io'))
                    ->addButton(ElementButton::create('tell me more')->payload('tellmemore')->type('postback')),
                Element::create('BotMan Laravel Starter')
                    ->itemUrl('https://github.com/mpociot/botman-laravel-starter')
                    ->image('http://screenshots.nomoreencore.com/botman.png')
                    ->subtitle('This is the best way to start with Laravel and BotMan')
                    ->addButton(ElementButton::create('visit')->url('https://github.com/mpociot/botman-laravel-starter')),
            ]);

        $payload = $driver->buildServicePayload($template, $message);
        $driver->sendPayload($payload);
        $driver->messagesHandled();

        $json = $template->toWebDriver();
        $this->expectOutputString('{"status":200,"messages":['.json_encode($json).']}');
    }

    /**
     * @test
     **/
    public function it_replies_to_facebook_receipt_template()
    {
        $driver = $this->getDriver([
            'driver' => 'web',
            'message' => 'Hi Julia',
            'userId' => '12345',
        ]);

        $message = new IncomingMessage('', '', '1234567890');
        $template = ReceiptTemplate::create()
            ->recipientName('Christoph Rumpel')
            ->merchantName('BotMan GmbH')
            ->orderNumber('342343434343')
            ->timestamp('1428444852')
            ->orderUrl('http://test.at')
            ->currency('USD')
            ->paymentMethod('VISA')
            ->addElement(ReceiptElement::create('T-Shirt Small')->price(15.99)->image('http://botman.io/img/botman-body.png')->quantity(2)->subtitle('v1')->currency('USD'))
            ->addElement(ReceiptElement::create('Sticker')->price(2.99)->image('http://botman.io/img/botman-body.png')->subtitle('Logo 1')->currency('USD'))
            ->addAddress(ReceiptAddress::create()->street1('Watsonstreet 12')->city('Bot City')->postalCode(100000)->state('Washington AI')->country('Botmanland'))
            ->addSummary(ReceiptSummary::create()->subtotal(18.98)->shippingCost(10)->totalTax(15)->totalCost(23.98))
            ->addAdjustment(ReceiptAdjustment::create('Laravel Bonus')->amount(5));

        $payload = $driver->buildServicePayload($template, $message);
        $driver->sendPayload($payload);
        $driver->messagesHandled();

        $json = $template->toWebDriver();
        $this->expectOutputString('{"status":200,"messages":['.json_encode($json).']}');
    }
}
