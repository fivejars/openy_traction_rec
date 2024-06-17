<?php

declare(strict_types=1);

namespace Drupal\openy_traction_rec;

/**
 * TractionRec Client interface.
 */
interface TractionRecClientInterface {

  /**
   * Retrieves the access token.
   *
   * @return string
   *   Loaded access token.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getAccessToken(): string;

  /**
   * Make request to Traction Rec.
   *
   * @param string $query
   *   SOQL query.
   *
   * @return array
   *   Retrieved results from Traction Rec.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \Drupal\openy_traction_rec\InvalidTokenException
   */
  public function executeQuery(string $query): array;

  /**
   * Sends Traction Rec request.
   *
   * @param string $method
   *   Request method.
   * @param string $url
   *   The URL.
   * @param array $options
   *   The array of request options.
   *
   * @return array|mixed
   *   The array with a response data.
   *
   * @throws \Drupal\openy_traction_rec\InvalidTokenException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function send(string $method, string $url, array $options = []): mixed;

}
