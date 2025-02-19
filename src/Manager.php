<?php

namespace Inilim\Ssh2ScpManager;

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
    /** @var ?resource */
    protected $sftp = null;

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
     * @return string[]
     */
    function scandir(string $remoteDir)
    {
        $this->init();
        $this->sftp();

        $remoteDir = \rtrim($remoteDir, '/\\');

        $files = @\scandir('ssh2.sftp://' . $this->sftp . $remoteDir);

        if ($files === false) {
            throw new \Exception('Remote dir not found');
        }

        foreach ($files as $idx => $file) {
            if ($file === '.' || $file === '..') {
                unset($files[$idx]);
            } else {
                $files[$idx] = $remoteDir . '/' . $file;
            }
        }

        return \array_values($files);
    }

    /**
     * @return bool
     */
    function existsFile(string $pathToFile)
    {
        $this->init();
        $this->sftp();

        return \file_exists('ssh2.sftp://' . $this->sftp . $pathToFile);
    }

    /**
     * @return bool
     */
    function unlink(string $remoteFile)
    {
        $this->init();
        $this->sftp();

        return \ssh2_sftp_unlink($this->sftp, $remoteFile);
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
        if (!$this->connect) return true;
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
     * @phpstan-assert resource $this->sftp
     */
    protected function sftp()
    {
        if ($this->sftp) return;
        if (!$this->connect) {
            throw new \Exception('Authentication cannot be done before connection');
        }
        $sftp = \ssh2_sftp($this->connect);
        if (!$sftp) {
            throw new \Exception('Unable to create SFTP connection.');
        }
        $this->sftp = $sftp;
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function connect()
    {
        // @phpstan-ignore-next-line
        $resource = \ssh2_connect($this->host, $this->port, $this->methods, $this->callbacks);
        if (!$resource) {
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
        if (!$this->connect) {
            throw new \Exception('Authentication cannot be done before connection');
        }
        $status = \ssh2_auth_password($this->connect, $this->username, $this->password);
        if (!$status) {
            throw new \Exception('Authentication failed');
        }
    }
}
