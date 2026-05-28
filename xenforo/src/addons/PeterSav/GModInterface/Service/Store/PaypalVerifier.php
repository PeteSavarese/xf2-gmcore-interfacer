<?php

namespace PeterSav\GModInterface\Service\Store;

use XF\Service\AbstractService;

class PaypalVerifier extends AbstractService {
	protected array $config;

	public function __construct(\XF\App $app, array $config) {
		parent::__construct($app);
		$this->config = $config;
	}

	public function getAccessToken(): array {
		$client = $this->app->http()->client();
		$resp = $client->post($this->config['token_url'], [
			'auth' => [$this->config['client_id'], $this->config['secret']],
			'form_params' => ['grant_type' => 'client_credentials'],
			'headers' => ['Accept' => 'application/json'],
			'http_errors' => false,
		]);

		$json = json_decode((string)$resp->getBody(), true) ?: [];
		$json['_status'] = $resp->getStatusCode();

		return $json;
	}

	public function fetchOrder(string $orderId, string $accessToken): array {
		$client = $this->app->http()->client();
		$resp = $client->get($this->config['order_url_base'] . $orderId, [
			'headers' => [
				'Authorization' => 'Bearer ' . $accessToken,
				'Accept' => 'application/json'
			],
			'http_errors' => false,
		]);

		$json = json_decode((string)$resp->getBody(), true) ?: [];
		$json['_status'] = $resp->getStatusCode();

		return $json;
	}

	public function createOrder(array $orderData, string $accessToken): array {
		$client = $this->app->http()->client();
		$resp = $client->post($this->config['order_url_base'], [
			'headers' => [
				'Authorization' => 'Bearer ' . $accessToken,
				'Content-Type' => 'application/json',
				'Accept' => 'application/json'
			],
			'json' => $orderData,
			'http_errors' => false,
		]);

		$json = json_decode((string)$resp->getBody(), true) ?: [];
		$json['_status'] = $resp->getStatusCode();

		return $json;
	}

	public function captureOrder(string $orderId, string $accessToken): array {
		$client = $this->app->http()->client();
		$resp = $client->post($this->config['order_url_base'] . $orderId . '/capture', [
			'headers' => [
				'Authorization' => 'Bearer ' . $accessToken,
				'Content-Type' => 'application/json',
				'Accept' => 'application/json'
			],
			'http_errors' => false,
		]);

		$json = json_decode((string)$resp->getBody(), true) ?: [];
		$json['_status'] = $resp->getStatusCode();

		return $json;
	}

	public function fetchTransactionInfo(string $transactionId, string $accessToken): array {
		$client = $this->app->http()->client();
		// Try capture endpoint first (typical for capture IDs)
		$captureBase = str_replace('/checkout/orders/', '/payments/captures/', $this->config['order_url_base']);
		$captureUrl = $captureBase . $transactionId;
		$resp = $client->get($captureUrl, [
			'headers' => [
				'Authorization' => 'Bearer ' . $accessToken,
				'Accept' => 'application/json'
			],
			'http_errors' => false,
		]);
		$json = json_decode((string)$resp->getBody(), true) ?: [];
		$json['_status'] = $resp->getStatusCode();

		if ($resp->getStatusCode() === 200 && !isset($json['name'])) {
			return $json;
		}

		$resp = $client->get($this->config['order_url_base'] . $transactionId, [
			'headers' => [
				'Authorization' => 'Bearer ' . $accessToken,
				'Accept' => 'application/json'
			],
			'http_errors' => false,
		]);

		$json = json_decode((string)$resp->getBody(), true) ?: [];
		$json['_status'] = $resp->getStatusCode();

		return $json;
	}
}
