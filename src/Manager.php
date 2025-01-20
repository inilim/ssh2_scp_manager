<?php

namespace Ssh2TcpManager;

final class Manager
{
    /** @var string */
    protected $host;
    /** @var int */
    protected $port;
    /** @var string */
    protected $username;
    /** @var string */
    protected $password;
    /** @var null|mixed[] */
    protected $methods;
    /** @var null|mixed[] */
    protected $callbacks;
    /** @var ?resource */
    protected $connect = null;

    /**
     * @param null|mixed[] $methods
     * @param null|mixed[] $callbacks
     */
    function __construct(
        string $host,
        string $username,
        string $password,
        int $port         = 22,
        ?array $methods   = null,
        ?array $callbacks = null
    ) {
        $this->host      = $host;
        $this->port      = $port;
        $this->username  = $username;
        $this->password  = $password;
        $this->methods   = $methods;
        $this->callbacks = $callbacks;
    }

    /**
     * @return bool
     */
    function send(
        string $localFile,
        string $remoteFile,
        int $createMode = 0644
    ) {
        $this->init();
        return \ssh2_scp_send($this->connect, $localFile, $remoteFile, $createMode);
    }

    /**
     * @return bool
     */
    function get(
        string $remoteFile,
        string $localFile
    ) {
        $this->init();
        return \ssh2_scp_recv($this->connect, $remoteFile, $localFile);
    }

    /**
     * @return void
     * @throws \Exception
     * @phpstan-assert resource $this->connect
     */
    function init()
    {
        if ($this->connect) return;
        $this->connect();
        $this->auth();
    }

    /**
     * @return bool
     */
    function disconnect()
    {
        if ($this->connect === null) return true;
        $status = \ssh2_disconnect($this->connect);
        $this->connect = null;
        return $status;
    }

    function __destruct()
    {
        $this->disconnect();
    }

    // ------------------------------------------------------------------
    // 
    // ------------------------------------------------------------------

    /**
     * @return void
     * @throws \Exception
     */
    protected function connect()
    {
        $resource = \ssh2_connect($this->host, $this->port, $this->methods, $this->callbacks);
        if ($resource === false) {
            throw new \Exception('Connection failed');
        }
        $this->connect = $resource;
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function auth()
    {
        if ($this->connect === null) {
            throw new \Exception('Authentication cannot be done before connection');
        }
        $status = \ssh2_auth_password($this->connect, $this->username, $this->password);
        if (!$status) {
            throw new \Exception('Authentication failed');
        }
    }
}
