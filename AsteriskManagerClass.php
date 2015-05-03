<?php
namespace FW\Air\AsteriskBundle\Classes;

/**
 * @author    Antonisin Max <antonisin.maxim@gmail.com>
 * @copyright 2014-2015
 */
class AsteriskManagerClass
{
    /**
     * The Asterisk server IP
     * [IP]:[PORT]
     *
     * @access private
     * @var string $host server IP
     */
    private $host;

    /**
     * The Asterisk server PORT
     * [IP]:[PORT]
     *
     * @access private
     * @var integer $port by default 5038
     */
    private $port = 5038;

    /**
     * The Asterisk opened Socket
     *
     * @access private
     * @var mixed $socket opened socket
     */
    private $socket;

    /**
     * The socket connect error code
     *
     * @access private
     * @var integer $errno by default 0
     */
    private $errno = 0;

    /**
     * The socket connect error string
     *
     * @access private
     * @var string $errstr by default ''
     */
    private $errstr = '';

    /**
     * The socket connect timeout
     *
     * @access private
     * @var integer connect timeout, by default (10)
     */
    private $timeout = 10;

    /**
     * The Asterisk access UserName
     *
     * @access private
     * @var string asterisk username
     */
    private $userName;

    /**
     * The Asterisk secret
     *
     * @access private
     * @var string asterisk secret
     */
    private $secret;

    /**
     * The Response context
     *
     * @access private
     * @var string Response context
     */
    private $status;

    /**
     * Set $host option
     *
     * @param string $input server IP
     *
     * @access private
     */
    private function setHost($input)
    {
        $this->host = $input;
    }

    /**
     *  Get $host option
     *
     * @access private
     * @return string
     */
    private function getHost()
    {
        return $this->host;
    }

    /**
     * Set $port option
     *
     * @param integer $input server port
     *
     * @access private
     */
    private function setPort($input)
    {
        $this->port = $input;
    }

    /**
     *  Get $port option
     *
     * @access private
     * @return integer
     */
    private function getPort()
    {
        return $this->port;
    }

    /**
     * Set $socket option
     *
     * @param mixed $input opened socket
     *
     * @access private
     */
    private function setSocket($input)
    {
        $this->socket = $input;
    }

    /**
     *  Get $socket option
     *
     * @param none null
     *
     * @access private
     * @return mixed
     */
    private function getSocket()
    {
        return $this->socket;
    }

    /**
     * Set $errno option
     *
     * @param integer $input error code
     *
     * @access private
     */
    private function setErrno($input)
    {
        $this->errno = $input;
    }

    /**
     *  Get $errno option
     *
     * @access private
     * @return integer
     */
    private function getErrno()
    {
        return $this->errno;
    }

    /**
     * Set $errstr option
     *
     * @param string $input error string
     *
     * @access private
     */
    private function setErrstr($input)
    {
        $this->errstr = $input;
    }

    /**
     *  Get $errstr option
     *
     * @access private
     * @return string
     */
    private function getErrstr()
    {
        return $this->errstr;
    }

    /**
     * Set $timeout option
     *
     * @param integer $input timeout
     *
     * @access private
     */
    private function setTimeout($input)
    {
        $this->timeout = $input;
    }

    /**
     *  Get $timeout option
     *
     * @access private
     * @return integer
     */
    private function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * Set $userName option
     *
     * @param string $input userName
     *
     * @access private
     */
    private function setUserName($input)
    {
        $this->userName = $input;
    }

    /**
     *  Get $userName option
     *
     * @access private
     * @return string
     */
    private function getUserName()
    {
        return $this->userName;
    }

    /**
     * Set $secret option
     *
     * @param string $input secret
     *
     * @access private
     */
    private function setSecret($input)
    {
        $this->secret = $input;
    }

    /**
     *  Get $secret option
     *
     * @access private
     * @return string
     */
    private function getSecret()
    {
        return $this->secret;
    }

    /**
     * Set $status option
     *
     * @param string $input status
     *
     * @access private
     */
    private function setStatus($input)
    {
        $this->status = $input;
    }

    /**
     * Get $status option
     *
     * @access public
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * The AsteriskManagerHelper __construct
     * Set all needed options
     * Array = [0 => IP, 1 => PORT, 2 => userName, 3 => secret]
     *
     * @param array $array setting array
     *
     * @access private
     */
    public function setOptions($array)
    {
        $this->setHost($array[0]);
        $this->setPort($array[1]);
        $this->setUserName($array[2]);
        $this->setSecret($array[3]);
    }

    /**
     * Set and connect socket
     * fsockopen ($host, $port, $errno, $errstr, $timeout)
     * $host    - Asterisk IP address
     * $port    - Asterisk PORT
     * $errno   - Error code
     * $errstr  - Error string
     * $timeout - connect timeout
     *
     * @access public
     */
    public function connect()
    {
        $this->setSocket(pfsockopen($this->getHost(), $this->getPort(), $errno, $errstr));
        stream_set_blocking($this->getSocket(), 1);
        $this->loginAction();
    }

    /**
     * Login access to AMI
     * Action  : Login
     * UserName: $userName
     * Secret  : $secret
     *
     * @access private
     */
    private function loginAction()
    {
        fputs($this->getSocket(), "Action: Login\r\n");
        fputs($this->getSocket(), "UserName:".$this->getUserName()."\r\n");
        fputs($this->getSocket(), "Secret:".$this->getSecret()."\r\n\r\n");
    }

    /**
     * Originate Listen Action
     * Action: Originate
     * Channel: SIP/{supervisor}
     * Application: ChanSpy
     * Data: SIP/{user} - with parameter S(hangup when channel end)
     * Async: True/False
     *
     * @access public
     *
     * @param string $superVisor supervisor name
     * @param string $agent      agent name
     */
    public function listen($superVisor, $agent)
    {
        fputs($this->getSocket(), "Action: Originate\r\n");
        fputs($this->getSocket(), "Channel: SIP/".$superVisor."\r\n");
        fputs($this->getSocket(), "Application: ChanSpy\r\n");
        fputs($this->getSocket(), "Data: SIP/".$agent.",S\r\n");
        fputs($this->getSocket(), "Async: True\r\n\r\n");
        fclose($this->getSocket());
    }

    /**
     * Originate Whisper Action
     * Action: Originate
     * Channel: SIP/{supervisor}
     * Application: ChanSpy
     * Data: SIP/{user},W - with parameters W(Whisper channel) and S(hangup when channel end)
     * Async: True/False
     *
     * @access public
     *
     * @param string $superVisor supervisor name
     * @param string $agent      agent name
     */
    public function whisper($superVisor, $agent)
    {
        fputs($this->getSocket(), "Action: Originate\r\n");
        fputs($this->getSocket(), "Channel: SIP/".$superVisor."\r\n");
        fputs($this->getSocket(), "Application: ChanSpy\r\n");
        fputs($this->getSocket(), "Data: SIP/".$agent.",WS\r\n");
        fputs($this->getSocket(), "Async: True\r\n\r\n");
        fclose($this->getSocket());
    }

    /**
     * Originate Barge Action
     * Action: Originate
     * Channel: SIP/{supervisor}
     * Application: ChanSpy
     * Data: SIP/{user},B - with parameters B(Barge channel) and S
     * Async: True/False
     *
     * @access public
     *
     * @param string $superVisor supervisor name
     * @param string $agent      agent name
     */
    public function barge($superVisor, $agent)
    {
        fputs($this->getSocket(), "Action: Originate\r\n");
        fputs($this->getSocket(), "Channel: SIP/".$superVisor."\r\n");
        fputs($this->getSocket(), "Application: ChanSpy\r\n");
        fputs($this->getSocket(), "Data: SIP/".$agent.",BS\r\n");
        fputs($this->getSocket(), "Async: True\r\n\r\n");
        fclose($this->getSocket());
    }

    /**
     * Hangup Action
     * Action: Hangup
     * Channel: SIP/{channel}  (ex RegEx: /^SIP/agent-.*$/)
     *
     * @access public
     *
     * @param  string $agent agent name
     */
    public function hangup($agent)
    {
        fputs($this->getSocket(), "Action:Hangup\r\n");
        fputs($this->getSocket(), "Channel:/^SIP/".$agent."-.*$/\r\n\r\n");
        fclose($this->getSocket());
    }

    /**
     * Agent Call Client
     *
     * @access public
     *
     * @param string $client  client phone number
     * @param string $agent   agent context
     * @param string $context asterisk context
     */
    public function call($client, $agent, $context = 'default')
    {
        fputs($this->getSocket(), "Action:Originate\r\n");
        fputs($this->getSocket(), "Channel:SIP/".$agent."\r\n");
        fputs($this->getSocket(), "Exten:".$client."\r\n");
        fputs($this->getSocket(), "Context:".$context."\r\n");
        fputs($this->getSocket(), "Priority:1\r\n\r\n");
        sleep(10);
        fclose($this->getSocket());
    }
}
