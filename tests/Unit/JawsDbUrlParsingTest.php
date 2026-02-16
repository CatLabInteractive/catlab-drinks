<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class JawsDbUrlParsingTest extends TestCase
{
    /**
     * Parse a JAWSDB_URL the same way config/database.php does.
     */
    private function parseJawsDbUrl(string $url): array
    {
        $parsedUrl = parse_url($url);
        return [
            'host' => $parsedUrl['host'] ?? '127.0.0.1',
            'port' => (string)($parsedUrl['port'] ?? '3306'),
            'database' => ltrim($parsedUrl['path'] ?? '/forge', '/'),
            'username' => urldecode($parsedUrl['user'] ?? 'forge'),
            'password' => urldecode($parsedUrl['pass'] ?? ''),
        ];
    }

    /**
     * Test that JAWSDB_URL is correctly parsed into database connection parameters.
     *
     * @return void
     */
    public function testJawsDbUrlParsing()
    {
        $jawsDb = $this->parseJawsDbUrl('mysql://testuser:testpass@example-host.com:3307/testdb');

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
        $jawsDb = $this->parseJawsDbUrl('mysql://user:pass@host.com/mydb');

        $this->assertEquals('host.com', $jawsDb['host']);
        $this->assertEquals('3306', $jawsDb['port']);
        $this->assertEquals('mydb', $jawsDb['database']);
        $this->assertEquals('user', $jawsDb['username']);
        $this->assertEquals('pass', $jawsDb['password']);
    }

    /**
     * Test that URL-encoded special characters in password are decoded.
     *
     * @return void
     */
    public function testJawsDbUrlWithSpecialCharsInPassword()
    {
        $jawsDb = $this->parseJawsDbUrl('mysql://user:p%40ss%23word@host.com:3306/db');

        $this->assertEquals('host.com', $jawsDb['host']);
        $this->assertEquals('db', $jawsDb['database']);
        $this->assertEquals('user', $jawsDb['username']);
        $this->assertEquals('p@ss#word', $jawsDb['password']);
    }
}
