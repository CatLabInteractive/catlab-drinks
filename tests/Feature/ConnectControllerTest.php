<?php

namespace Tests\Feature;

use Tests\TestCase;

class ConnectControllerTest extends TestCase
{
	public function testConnectWithValidDataShowsOptions(): void
	{
		$connectData = base64_encode(json_encode([
			'api' => 'https://example.com',
			'token' => 'test-token-123',
		]));

		$response = $this->get('/connect?data=' . $connectData);

		$response->assertStatus(200);
		$response->assertSee('Continue in Browser');
		$response->assertSee('Open in Android App');
	}

	public function testConnectWithValidDataBuildsPosUrl(): void
	{
		$connectData = base64_encode(json_encode([
			'api' => 'https://example.com',
			'token' => 'test-token-123',
		]));

		$response = $this->get('/connect?data=' . $connectData);

		$response->assertStatus(200);
		// The POS URL should contain the connect data as a query parameter
		$response->assertSee('https://example.com/pos/?connect=' . urlencode($connectData));
	}

	public function testConnectWithoutDataShowsError(): void
	{
		$response = $this->get('/connect');

		$response->assertStatus(200);
		$response->assertSee('No connection data provided');
	}

	public function testConnectWithInvalidBase64ShowsError(): void
	{
		$response = $this->get('/connect?data=not-valid-base64!!!');

		$response->assertStatus(200);
		$response->assertSee('Invalid connection data');
	}

	public function testConnectWithInvalidJsonShowsError(): void
	{
		// Valid base64 but not valid JSON
		$connectData = base64_encode('not json');

		$response = $this->get('/connect?data=' . $connectData);

		$response->assertStatus(200);
		$response->assertSee('Invalid connection data');
	}

	public function testConnectWithMissingApiFieldShowsError(): void
	{
		$connectData = base64_encode(json_encode([
			'token' => 'test-token-123',
		]));

		$response = $this->get('/connect?data=' . $connectData);

		$response->assertStatus(200);
		$response->assertSee('Invalid connection data');
	}

	public function testConnectWithMissingTokenFieldShowsError(): void
	{
		$connectData = base64_encode(json_encode([
			'api' => 'https://example.com',
		]));

		$response = $this->get('/connect?data=' . $connectData);

		$response->assertStatus(200);
		$response->assertSee('Invalid connection data');
	}
}
