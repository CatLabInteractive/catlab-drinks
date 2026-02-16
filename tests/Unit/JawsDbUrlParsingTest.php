<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class JawsDbUrlParsingTest extends TestCase
{
    /**
     * Test that JAWSDB_URL is correctly parsed into database connection parameters.
     *
     * @return void
     */
    public function testJawsDbUrlParsing()
    {
        $url = 'mysql://testuser:testpass@example-host.com:3307/testdb';
        $parsedUrl = parse_url($url);

        $jawsDb = [
            'host' => $parsedUrl['host'] ?? '127.0.0.1',
            'port' => (string)($parsedUrl['port'] ?? '3306'),
            'database' => ltrim($parsedUrl['path'] ?? '/forge', '/'),
            'username' => $parsedUrl['user'] ?? 'forge',
            'password' => $parsedUrl['pass'] ?? '',
        ];

        $this->assertEquals('example-host.com', $jawsDb['host']);
        $this->assertEquals('3307', $jawsDb['port']);
        $this->assertEquals('testdb', $jawsDb['database']);
        $this->assertEquals('testuser', $jawsDb['username']);
        $this->assertEquals('testpass', $jawsDb['password']);
    }

    /**
     * Test parsing with default port (no port in URL).
     *
     * @return void
     */
    public function testJawsDbUrlWithDefaultPort()
    {
        $url = 'mysql://user:pass@host.com/mydb';
        $parsedUrl = parse_url($url);

        $jawsDb = [
            'host' => $parsedUrl['host'] ?? '127.0.0.1',
            'port' => (string)($parsedUrl['port'] ?? '3306'),
            'database' => ltrim($parsedUrl['path'] ?? '/forge', '/'),
            'username' => $parsedUrl['user'] ?? 'forge',
            'password' => $parsedUrl['pass'] ?? '',
        ];

        $this->assertEquals('host.com', $jawsDb['host']);
        $this->assertEquals('3306', $jawsDb['port']);
        $this->assertEquals('mydb', $jawsDb['database']);
        $this->assertEquals('user', $jawsDb['username']);
        $this->assertEquals('pass', $jawsDb['password']);
    }

    /**
     * Test that special characters in password are handled.
     *
     * @return void
     */
    public function testJawsDbUrlWithSpecialCharsInPassword()
    {
        $url = 'mysql://user:p%40ss%23word@host.com:3306/db';
        $parsedUrl = parse_url($url);

        $jawsDb = [
            'host' => $parsedUrl['host'] ?? '127.0.0.1',
            'port' => (string)($parsedUrl['port'] ?? '3306'),
            'database' => ltrim($parsedUrl['path'] ?? '/forge', '/'),
            'username' => $parsedUrl['user'] ?? 'forge',
            'password' => $parsedUrl['pass'] ?? '',
        ];

        $this->assertEquals('host.com', $jawsDb['host']);
        $this->assertEquals('db', $jawsDb['database']);
        $this->assertEquals('user', $jawsDb['username']);
        $this->assertEquals('p%40ss%23word', $jawsDb['password']);
    }
}
